<?php

namespace App\Services;

use App\Mail\DigitalSaleRegistrationInviteMail;
use App\Models\Participation;
use App\Models\PendingDigitalSale;
use App\Models\Seller;
use App\Models\Set;
use App\Models\User;
use App\Support\PendingDigitalSaleLinkCode;
use App\Services\ParticipationWalletValidityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PendingDigitalSaleService
{
    public function __construct(
        protected ParticipationWalletValidityService $walletValidity
    ) {}

    public function validUntilFromConfig(): Carbon
    {
        return $this->walletValidity->validUntilForLottery(null);
    }

    public function validUntilForPendingSale(int $lotteryId): Carbon
    {
        $lottery = \App\Models\Lottery::find($lotteryId);

        return $this->walletValidity->validUntilForLottery($lottery);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Participation>
     */
    public function selectDigitalParticipations(
        Seller $seller,
        int $quantity,
        ?int $setId,
        ?int $entityId,
        ?int $lotteryId,
        ?int $reserveId = null
    ) {
        $this->releaseExpiredForDigitalContext($entityId, $lotteryId, $setId);

        if ($setId) {
            $set = Set::with('reserve')->findOrFail($setId);
            if (($set->digital_participations ?? 0) <= 0) {
                throw new \InvalidArgumentException('Este set no es de participaciones digitales.');
            }
            if (! $seller->entities()->where('entities.id', $set->entity_id)->exists()) {
                throw new \InvalidArgumentException('No tienes acceso a esta entidad.');
            }

            return $this->queryDigitalDisponibleForSet($setId)
                ->orderBy('participation_number')
                ->limit($quantity)
                ->get();
        }

        if (! $entityId || ! $lotteryId) {
            throw new \InvalidArgumentException('Indica set_id o entity_id + lottery_id.');
        }

        if (! $seller->entities()->where('entities.id', $entityId)->exists()) {
            throw new \InvalidArgumentException('No tienes acceso a esta entidad.');
        }

        $ids = $this->queryDigitalDisponiblePool($entityId, $lotteryId, $reserveId)
            ->select('participations.id')
            ->orderBy('participations.id')
            ->limit($quantity)
            ->pluck('participations.id');

        return Participation::with('set.reserve')->whereIn('id', $ids)->orderBy('id')->get();
    }

    public function countDigitalDisponibleForPool(int $entityId, int $lotteryId, ?int $reserveId = null): int
    {
        $this->releaseExpiredForDigitalContext($entityId, $lotteryId, null);

        return $this->queryDigitalDisponiblePool($entityId, $lotteryId, $reserveId)->count();
    }

    protected function queryDigitalDisponiblePool(int $entityId, int $lotteryId, ?int $reserveId = null)
    {
        $query = Participation::query()
            ->join('sets', 'participations.set_id', '=', 'sets.id')
            ->join('reserves', 'sets.reserve_id', '=', 'reserves.id')
            ->where('participations.entity_id', $entityId)
            ->where('reserves.lottery_id', $lotteryId)
            ->where('sets.physical_participations', '<=', 0)
            ->whereRaw('sets.digital_participations > 0')
            ->whereRaw("participations.participation_code LIKE '1D/%'")
            ->where('participations.status', 'disponible');

        if ($reserveId) {
            $query->where('sets.reserve_id', $reserveId);
        }

        return $query;
    }

    public function createPendingSale(
        Seller $seller,
        User $sellerUser,
        ?string $buyerEmail,
        int $quantity,
        ?string $paymentMethod,
        ?int $setId,
        ?int $entityId,
        ?int $lotteryId,
        ?string $buyerPhone = null,
        ?string $notifyChannel = null,
        ?int $reserveId = null
    ): PendingDigitalSale {
        $rawEmail = trim((string) ($buyerEmail ?? ''));
        $rawPhone = trim((string) ($buyerPhone ?? ''));

        if ($rawEmail !== '' && $rawPhone !== '') {
            throw new \InvalidArgumentException('Indica solo email o teléfono del comprador, no ambos.');
        }

        if ($rawEmail === '' && $rawPhone === '') {
            throw new \InvalidArgumentException('Debes indicar el email o el teléfono del comprador para enviar la venta.');
        }

        $channel = $notifyChannel ?? ($rawEmail !== '' ? 'email' : 'sms');
        if (! in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            throw new \InvalidArgumentException('Canal de notificación no válido.');
        }

        if ($channel === 'email' && $rawEmail === '') {
            throw new \InvalidArgumentException('Indica el email del comprador.');
        }

        if (in_array($channel, ['sms', 'whatsapp'], true) && $rawPhone === '') {
            throw new \InvalidArgumentException('Indica el teléfono del comprador.');
        }

        $smsService = app(DigitalSaleSmsService::class);
        $normalizedPhone = null;
        if ($rawPhone !== '') {
            $normalizedPhone = $smsService->normalizeSmsAddress($rawPhone);
            if (! $normalizedPhone) {
                throw new \InvalidArgumentException('Teléfono no válido. Usa prefijo internacional (ej. 34600111222).');
            }
            $normalizedPhone = ltrim($normalizedPhone, '+');
        }

        $sendInviteEmail = $channel === 'email';
        if ($sendInviteEmail) {
            $email = PendingDigitalSale::normalizeEmail($rawEmail);
            if (User::where('email', $email)->exists()) {
                throw new \InvalidArgumentException('El correo ya está registrado. Usa la venta directa.');
            }
        } else {
            $email = null;
        }

        $participations = $this->selectDigitalParticipations($seller, $quantity, $setId, $entityId, $lotteryId, $reserveId);
        if ($participations->count() < $quantity) {
            throw new \InvalidArgumentException(
                'No hay suficientes participaciones digitales disponibles. Disponibles: '.$participations->count()
            );
        }

        $set = $participations->first()->set;
        $resolvedLotteryId = $lotteryId ?? $set->reserve->lottery_id;
        $unitTotal = $set->pricePerParticipation();
        $saleAmount = round($participations->count() * $unitTotal, 2);

        return DB::transaction(function () use (
            $email,
            $sendInviteEmail,
            $seller,
            $sellerUser,
            $participations,
            $quantity,
            $paymentMethod,
            $setId,
            $entityId,
            $lotteryId,
            $saleAmount,
            $set,
            $resolvedLotteryId,
            $normalizedPhone,
            $channel
        ) {
            $pending = PendingDigitalSale::create([
                'email' => $email,
                'buyer_phone' => $normalizedPhone,
                'notify_channel' => $channel,
                'seller_id' => $seller->id,
                'entity_id' => $entityId ?? $set->entity_id ?? $participations->first()->entity_id,
                'lottery_id' => $lotteryId ?? $set->reserve->lottery_id,
                'set_id' => $setId,
                'quantity' => $quantity,
                'sale_amount' => $saleAmount,
                'payment_method' => $paymentMethod,
                'registration_token' => Str::random(64),
                'link_code' => PendingDigitalSaleLinkCode::generateUnique(),
                'status' => PendingDigitalSale::STATUS_PENDING,
                'valid_until' => $this->validUntilForPendingSale((int) $resolvedLotteryId),
            ]);

            foreach ($participations as $p) {
                $p->update([
                    'status' => 'reserva_venta_digital',
                    'seller_id' => $seller->id,
                ]);
                $pending->participations()->attach($p->id);
            }

            if ($sendInviteEmail && $email) {
                $pending->ensureLinkCode();
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: $email,
                    recipientRole: 'usuario',
                    recipientUser: null,
                    messageType: 'digital_sale_registration_invite',
                    templateKey: null,
                    mailClass: DigitalSaleRegistrationInviteMail::class,
                    mailPayload: ['pending_digital_sale_id' => $pending->id],
                    context: ['pending_digital_sale_id' => $pending->id, 'seller_id' => $seller->id],
                );
            }

            return $pending->fresh(['entity', 'lottery', 'seller']);
        });
    }

    /**
     * Envía el SMS inicial tras crear la venta (canal sms con httpSMS activo).
     */
    public function sendInitialSmsIfNeeded(PendingDigitalSale $pending): bool
    {
        if ($pending->notify_channel !== 'sms') {
            return false;
        }

        $sms = app(DigitalSaleSmsService::class);
        if (! $sms->isEnabled() || ! $pending->buyer_phone) {
            return false;
        }

        $sms->sendToBuyer($pending, $pending->buyer_phone);

        return true;
    }

    /**
     * Reenvía el correo de registro al email fijado en la venta.
     */
    public function resendRegistrationEmail(PendingDigitalSale $pending): void
    {
        if (! $pending->email || ! $pending->usesEmailChannel()) {
            throw new \InvalidArgumentException('Esta venta no tiene un email de comprador registrado.');
        }

        if (! $pending->isStillValid()) {
            throw new \InvalidArgumentException('Esta venta pendiente ya no está disponible o ha caducado.');
        }

        $pending->ensureLinkCode();
        app(CommunicationEmailService::class)->sendAndLog(
            recipientEmail: $pending->email,
            recipientRole: 'usuario',
            recipientUser: null,
            messageType: 'digital_sale_registration_invite',
            templateKey: null,
            mailClass: DigitalSaleRegistrationInviteMail::class,
            mailPayload: ['pending_digital_sale_id' => $pending->id],
            context: ['pending_digital_sale_id' => $pending->id, 'seller_id' => $pending->seller_id, 'resend' => true],
        );
    }

    /**
     * Al registrarse un usuario con el mismo email, completar ventas pendientes no caducadas.
     */
    public function completePendingSalesForUser(User $user): int
    {
        $email = PendingDigitalSale::normalizeEmail((string) $user->email);
        $this->releaseExpiredForEmail($email);
        $completed = 0;

        $pendings = PendingDigitalSale::query()
            ->where('email', $email)
            ->pendingNotExpired()
            ->with(['participations.set.reserve', 'seller'])
            ->get();

        foreach ($pendings as $pending) {
            try {
                $this->finalizePendingSale($pending, $user);
                $completed++;
            } catch (\Throwable $e) {
                \Log::error('Error completando venta digital pendiente #'.$pending->id.': '.$e->getMessage());
            }
        }

        return $completed;
    }

    /**
     * Vincula una venta pendiente al usuario mediante el código (email incorrecto o registro tardío).
     */
    public function claimByLinkCode(User $user, string $rawCode): PendingDigitalSale
    {
        $code = PendingDigitalSaleLinkCode::normalizeInput($rawCode);
        if (! PendingDigitalSaleLinkCode::isValidFormat($code)) {
            throw new \InvalidArgumentException('Código no válido. Comprueba que tenga 5–6 caracteres.');
        }

        $pending = PendingDigitalSale::query()
            ->where('status', PendingDigitalSale::STATUS_PENDING)
            ->whereNotNull('link_code')
            ->whereRaw('LOWER(link_code) = ?', [mb_strtolower($code, 'UTF-8')])
            ->first();

        if (! $pending) {
            throw new \InvalidArgumentException('No hay ninguna venta pendiente con ese código o ya ha sido utilizada.');
        }

        if ($pending->isExpired()) {
            $this->releasePendingSale($pending, PendingDigitalSale::STATUS_EXPIRED);
            $months = (int) config('digital_sale.wallet_validity_months_after_draw', 3);
            throw new \InvalidArgumentException(
                "El código ha caducado. La vinculación era válida hasta {$months} meses después de la fecha del sorteo."
            );
        }

        if (! $pending->isStillValid()) {
            throw new \InvalidArgumentException('Esta venta pendiente ya no está disponible.');
        }

        $this->finalizePendingSale($pending, $user);

        return $pending->fresh(['entity', 'lottery', 'participations']);
    }

    public function finalizePendingSale(PendingDigitalSale $pending, User $buyer): void
    {
        if ($pending->status !== PendingDigitalSale::STATUS_PENDING) {
            return;
        }

        if ($pending->isExpired()) {
            $this->releasePendingSale($pending, PendingDigitalSale::STATUS_EXPIRED);

            return;
        }

        DB::transaction(function () use ($pending, $buyer) {
            $pending->load(['participations.set.reserve', 'seller']);
            $seller = $pending->seller;
            if (! $seller) {
                throw new \RuntimeException('Vendedor no encontrado.');
            }

            $participations = $pending->participations;
            $set = $participations->first()?->set;
            $pricePer = $participations->count() > 0
                ? ((float) $pending->sale_amount / $participations->count())
                : 0;

            foreach ($participations as $p) {
                if ($p->status !== 'reserva_venta_digital') {
                    continue;
                }
                $p->markAsSold($seller->id, $pricePer, [
                    'user_id' => $buyer->id,
                    'email' => $buyer->email,
                ], $pending->payment_method);
            }

            if ($participations->isNotEmpty() && $set) {
                app(SellerSettlementFromSaleService::class)->recordIfNeeded(
                    $seller,
                    $participations,
                    $set,
                    (float) $pending->sale_amount,
                    $pending->payment_method,
                    (int) ($seller->user_id ?? $buyer->id)
                );
            }

            $pending->update([
                'status' => PendingDigitalSale::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_user_id' => $buyer->id,
            ]);
        });

        $pending->refresh();
        app(DigitalParticipationNotificationService::class)->sendPendingClaimed($buyer, $pending);
    }

    public function releasePendingSale(PendingDigitalSale $pending, string $status = PendingDigitalSale::STATUS_EXPIRED): void
    {
        DB::transaction(function () use ($pending, $status) {
            $pending->load('participations');
            $restoreStatus = $pending->set_id ? 'asignada' : 'disponible';
            foreach ($pending->participations as $p) {
                if ($p->status === 'reserva_venta_digital') {
                    Participation::withoutEvents(function () use ($p, $restoreStatus) {
                        $p->update([
                            'status' => $restoreStatus,
                            'seller_id' => null,
                        ]);
                    });
                }
            }
            $pending->update(['status' => $status]);
        });
    }

    /**
     * Caducidad pasiva: libera reservas vencidas (valid_until) del pool o set consultado.
     * No requiere cron; se invoca al vender, consultar stock o abrir registro.
     */
    public function releaseExpiredForDigitalContext(?int $entityId, ?int $lotteryId, ?int $setId = null): int
    {
        if (! $setId && (! $entityId || ! $lotteryId)) {
            return 0;
        }

        // Gracia: no caducar ventas recién creadas (evita carrera con loadTotalDigitalAvailable / SMS).
        $query = PendingDigitalSale::query()
            ->where('status', PendingDigitalSale::STATUS_PENDING)
            ->where('valid_until', '<', now())
            ->where('created_at', '<', now()->subMinutes(10));

        if ($setId) {
            $query->where('set_id', $setId);
        } else {
            $query->where('entity_id', $entityId)->where('lottery_id', $lotteryId);
        }

        return $this->releasePendingQuery($query);
    }

    /** Libera ventas pendientes caducadas de un email (p. ej. al registrarse). */
    public function releaseExpiredForEmail(string $email): int
    {
        $email = PendingDigitalSale::normalizeEmail($email);

        $query = PendingDigitalSale::query()
            ->where('email', $email)
            ->where('status', PendingDigitalSale::STATUS_PENDING)
            ->where('valid_until', '<', now())
            ->where('created_at', '<', now()->subMinutes(10));

        return $this->releasePendingQuery($query);
    }

    private function releasePendingQuery($query): int
    {
        $released = 0;
        $query->orderBy('id')->chunkById(50, function ($rows) use (&$released) {
            foreach ($rows as $pending) {
                $this->releasePendingSale($pending, PendingDigitalSale::STATUS_EXPIRED);
                $released++;
            }
        });

        return $released;
    }

    public function findValidByToken(string $token): ?PendingDigitalSale
    {
        $pending = PendingDigitalSale::query()
            ->where('registration_token', $token)
            ->where('status', PendingDigitalSale::STATUS_PENDING)
            ->with(['entity', 'lottery'])
            ->first();

        if (! $pending) {
            return null;
        }

        // No marcar expired en un GET (preview del enlace en el SMS); solo comprobar validez.
        if ($pending->isExpired()) {
            return null;
        }

        return $pending;
    }

    /**
     * Participaciones digitales vendibles de un set (sin asignación a vendedor).
     */
    public function queryDigitalDisponibleForSet(int $setId)
    {
        return Participation::query()
            ->where('set_id', $setId)
            ->whereRaw("participation_code LIKE '1D/%'")
            ->where('status', 'disponible');
    }

    public function countDigitalDisponibleForSet(int $setId): int
    {
        return (int) $this->queryDigitalDisponibleForSet($setId)->count();
    }
}
