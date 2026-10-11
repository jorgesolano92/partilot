<?php

namespace App\Services;

use App\Http\Controllers\ApiController;
use App\Models\LegalAcceptance;
use App\Models\Participation;
use App\Models\ParticipationGift;
use App\Models\ParticipationCollection;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function __construct(
        private readonly LegalAcceptanceService $legalAcceptance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function statusForUser(User $user): array
    {
        $cfg = config('legal.account_deletion', []);
        $pendingPrizes = $this->countPendingPrizeParticipations($user);
        $sellerBlock = $this->sellerPendingObligations($user);

        return [
            'can_request' => $pendingPrizes === 0 && $sellerBlock === null && ! $this->isDeletionScheduled($user),
            'pending_prize_count' => $pendingPrizes,
            'seller_block_message' => $sellerBlock,
            'deletion_requested_at' => optional($user->deletion_requested_at)->toIso8601String(),
            'deletion_scheduled_at' => optional($user->deletion_scheduled_at)->toIso8601String(),
            'deletion_status' => $user->deletion_status,
            'ui' => [
                'title' => $cfg['title'] ?? 'Eliminar cuenta',
                'main_warning' => $cfg['main_warning'] ?? '',
                'prizes_warning' => $cfg['prizes_warning'] ?? '',
                'blocked_message' => $cfg['blocked_message'] ?? '',
                'email_confirm_label' => $cfg['email_confirm_label'] ?? 'Escribe tu email para confirmar',
                'confirm_button' => $cfg['confirm_button'] ?? 'Eliminar mi cuenta',
                'cancel_button' => $cfg['cancel_button'] ?? 'Cancelar',
                'scheduled_notice' => str_replace(
                    ':days',
                    (string) ($cfg['grace_days'] ?? 30),
                    $cfg['scheduled_notice'] ?? ''
                ),
            ],
        ];
    }

    public function isDeletionScheduled(User $user): bool
    {
        return in_array((string) $user->deletion_status, ['scheduled', 'pending_review'], true)
            && $user->deletion_requested_at !== null;
    }

    /**
     * Un vendedor no puede darse de baja con papeletas en mano o importes sin liquidar.
     */
    public function sellerPendingObligations(User $user): ?string
    {
        $sellerIds = Seller::where('user_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($sellerIds === []) {
            return null;
        }

        $inHand = Participation::whereIn('seller_id', $sellerIds)->where('status', 'asignada')->count();
        $debt = array_sum(app(SellerLiquidationService::class)->getPendingLiquidationBySellers($sellerIds));

        if ($inHand === 0 && $debt < 0.01) {
            return null;
        }

        $parts = [];
        if ($inHand > 0) {
            $parts[] = $inHand.' participación(es) asignada(s) sin vender ni devolver';
        }
        if ($debt >= 0.01) {
            $parts[] = number_format($debt, 2, ',', '.').' € pendientes de liquidar';
        }

        return 'No puedes eliminar la cuenta mientras como vendedor tengas '.implode(' y ', $parts)
            .'. Devuelve o liquida con tu entidad antes de solicitar la baja.';
    }

    public function countPendingPrizeParticipations(User $user): int
    {
        $userId = (string) $user->id;
        $apiController = app(ApiController::class);
        $reservedIds = ParticipationCollection::reservedParticipationIds();
        $count = 0;

        $participations = Participation::where('buyer_name', $userId)
            ->whereNull('collected_at')
            ->whereNull('donated_at')
            ->when(! empty($reservedIds), fn ($q) => $q->whereNotIn('id', $reservedIds))
            ->with(['set.reserve.lottery', 'set.entity', 'gift'])
            ->get();

        foreach ($participations as $participation) {
            if ($participation->isWalletStorage()) {
                continue;
            }
            if ($participation->relationLoaded('gift') && $participation->gift
                && in_array($participation->gift->status, [ParticipationGift::STATUS_PENDING, ParticipationGift::STATUS_ACCEPTED], true)) {
                continue;
            }
            if (app(ParticipationWalletValidityService::class)->isParticipationWalletExpired($participation)) {
                continue;
            }

            $ref = $this->referenceFromParticipation($participation);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            if (! ($prizeInfo['has_won'] ?? false) || (float) ($prizeInfo['prize_amount'] ?? 0) <= 0) {
                continue;
            }

            $gate = app(EntityLotteryPrizePaymentService::class)->evaluateOnlineCollection(
                $participation,
                (float) $prizeInfo['prize_amount']
            );
            if (! ($gate['cobrable'] ?? false)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    protected function referenceFromParticipation(Participation $participation): string
    {
        if (! $participation->set) {
            return '';
        }

        return $participation->set->referenceForParticipationNumber((int) $participation->participation_number) ?? '';
    }

    /**
     * @return array{success: bool, message: string, status?: array<string, mixed>}
     */
    public function requestDeletion(User $user, Request $request, string $emailConfirm): array
    {
        if ($this->isDeletionScheduled($user)) {
            return [
                'success' => false,
                'message' => 'Ya existe una solicitud de baja en curso para esta cuenta.',
            ];
        }

        $pendingPrizes = $this->countPendingPrizeParticipations($user);
        $cfg = config('legal.account_deletion', []);

        if ($pendingPrizes > 0) {
            $this->legalAcceptance->recordFromRequest(
                action: LegalAcceptance::ACTION_SOLICITUD_BAJA_CUENTA,
                request: $request,
                user: $user,
                result: LegalAcceptance::RESULT_RECHAZADO,
                version: (string) ($cfg['version'] ?? '3'),
                textHash: (string) ($cfg['hash'] ?? 'l9_baja_cuenta_v3'),
                context: [
                    'estado_baja' => 'BLOQUEADA_POR_PENDIENTES',
                    'pending_prize_count' => $pendingPrizes,
                ],
            );

            return [
                'success' => false,
                'message' => $cfg['blocked_message'] ?? 'Tienes participaciones premiadas pendientes de cobro.',
                'status' => $this->statusForUser($user),
            ];
        }

        if ($sellerBlock = $this->sellerPendingObligations($user)) {
            $this->legalAcceptance->recordFromRequest(
                action: LegalAcceptance::ACTION_SOLICITUD_BAJA_CUENTA,
                request: $request,
                user: $user,
                result: LegalAcceptance::RESULT_RECHAZADO,
                version: (string) ($cfg['version'] ?? '3'),
                textHash: (string) ($cfg['hash'] ?? 'l9_baja_cuenta_v3'),
                context: ['estado_baja' => 'BLOQUEADA_VENDEDOR_PENDIENTE'],
            );

            return [
                'success' => false,
                'message' => $sellerBlock,
                'status' => $this->statusForUser($user),
            ];
        }

        if (strcasecmp(trim($emailConfirm), trim((string) $user->email)) !== 0) {
            return [
                'success' => false,
                'message' => 'El email de confirmación no coincide con el registrado.',
            ];
        }

        $graceDays = (int) ($cfg['grace_days'] ?? 30);

        DB::transaction(function () use ($user, $graceDays) {
            $user->update([
                'status' => false,
                'deletion_requested_at' => now(),
                'deletion_scheduled_at' => now()->addDays($graceDays),
                'deletion_status' => 'scheduled',
            ]);
            UserFcmToken::where('user_id', $user->id)->delete();
        });

        $user->refresh();

        try {
            app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: (string) $user->email,
                recipientRole: 'usuario',
                recipientUser: $user,
                messageType: 'account_deletion_requested',
                templateKey: null,
                mailClass: \App\Mail\AccountDeletionRequestedMail::class,
                mailPayload: ['user_id' => $user->id],
                context: ['source' => 'api'],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Email de solicitud de baja: '.$e->getMessage());
        }

        $this->legalAcceptance->recordFromRequest(
            action: LegalAcceptance::ACTION_SOLICITUD_BAJA_CUENTA,
            request: $request,
            user: $user,
            version: (string) ($cfg['version'] ?? '3'),
            textHash: (string) ($cfg['hash'] ?? 'l9_baja_cuenta_v3'),
            context: [
                'estado_baja' => 'PENDIENTE_REVISION',
                'email_confirmacion' => $emailConfirm,
                'deletion_scheduled_at' => optional($user->deletion_scheduled_at)->toIso8601String(),
            ],
        );

        return [
            'success' => true,
            'message' => str_replace(':days', (string) $graceDays, $cfg['scheduled_notice'] ?? 'Cuenta desactivada.'),
            'status' => $this->statusForUser($user),
        ];
    }

    public function canBeReactivated(User $user): bool
    {
        return $user->deletion_status === 'scheduled'
            && $user->deletion_requested_at !== null
            && $user->deletion_scheduled_at !== null
            && $user->deletion_scheduled_at->isFuture();
    }

    /**
     * Anula una solicitud de baja dentro del periodo de gracia.
     */
    public function cancelDeletion(User $user, Request $request): bool
    {
        if (! $this->canBeReactivated($user)) {
            return false;
        }

        $user->update([
            'status' => true,
            'deletion_requested_at' => null,
            'deletion_scheduled_at' => null,
            'deletion_status' => null,
        ]);

        $cfg = config('legal.account_deletion', []);
        $this->legalAcceptance->recordFromRequest(
            action: LegalAcceptance::ACTION_SOLICITUD_BAJA_CUENTA,
            request: $request,
            user: $user,
            version: (string) ($cfg['version'] ?? '3'),
            textHash: (string) ($cfg['hash'] ?? 'l9_baja_cuenta_v3'),
            context: ['estado_baja' => 'ANULADA_POR_USUARIO'],
        );

        return true;
    }
}
