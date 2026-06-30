<?php

namespace App\Services;

use App\Models\AdministrationLotteryScrutiny;
use App\Models\Entity;
use App\Models\EntityLotteryPrizeActivationLog;
use App\Models\EntityLotteryPrizeSetting;
use App\Models\Participation;
use App\Models\ParticipationCollection;
use App\Models\ParticipationCollectionItem;
use App\Models\ScrutinyDetailedResult;
use App\Models\User;
class EntityLotteryPrizePaymentService
{
    /**
     * Entidades con reserva confirmada que aún no han cerrado devolución entidad→administración.
     *
     * @return \Illuminate\Support\Collection<int, Entity>
     */
    public function entitiesPendingAdminDevolutionClosure(int $administrationId, int $lotteryId): \Illuminate\Support\Collection
    {
        return Entity::query()
            ->where('administration_id', $administrationId)
            ->whereHas('reserves', fn ($q) => $q->where('lottery_id', $lotteryId)->where('status', 1))
            ->whereDoesntHave('lotteryPrizeSettings', fn ($q) => $q
                ->where('lottery_id', $lotteryId)
                ->whereNotNull('mode_locked_at'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function administrationCanRunScrutiny(int $administrationId, int $lotteryId): bool
    {
        return $this->entitiesPendingAdminDevolutionClosure($administrationId, $lotteryId)->isEmpty();
    }

    public function administrationScrutinyBlockedMessage(int $administrationId, int $lotteryId): ?string
    {
        $pending = $this->entitiesPendingAdminDevolutionClosure($administrationId, $lotteryId);
        if ($pending->isEmpty()) {
            return null;
        }

        $names = $pending->pluck('name')->filter()->implode(', ');

        return 'No se puede escrutinar hasta que todas las entidades hayan confirmado la devolución a la administración'
            .' (liquidación con modalidad de pago de premios).'
            .($names !== '' ? ' Pendientes: '.$names.'.' : '');
    }

    /**
     * Bloquea la modalidad de pago al cerrar devolución entidad→administración.
     */
    public function lockModeFromDevolution(
        int $entityId,
        int $lotteryId,
        string $mode,
        int $userId,
        ?string $onlinePayer = null
    ): EntityLotteryPrizeSetting {
        if (! in_array($mode, [EntityLotteryPrizeSetting::MODE_PRESENCIAL, EntityLotteryPrizeSetting::MODE_ONLINE], true)) {
            throw new \InvalidArgumentException('Modalidad de pago de premios no válida.');
        }

        $resolvedPayer = $mode === EntityLotteryPrizeSetting::MODE_ONLINE
            ? ($onlinePayer ?? EntityLotteryPrizeSetting::PAYER_PARTILOT)
            : null;

        if ($resolvedPayer !== null && ! in_array($resolvedPayer, [EntityLotteryPrizeSetting::PAYER_PARTILOT, EntityLotteryPrizeSetting::PAYER_ENTITY], true)) {
            throw new \InvalidArgumentException('Pagador online no válido.');
        }

        $entity = Entity::query()->findOrFail($entityId);
        $hasSoldDigital = $this->entityHasSoldDigitalParticipations($entityId, $lotteryId);

        $fundsStatus = EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
        $contractStatus = EntityLotteryPrizeSetting::CONTRACT_NOT_REQUIRED;
        $onlineEnabled = false;
        $presencialEnabled = false;

        if ($mode === EntityLotteryPrizeSetting::MODE_ONLINE && $resolvedPayer === EntityLotteryPrizeSetting::PAYER_ENTITY) {
            // Legacy: la entidad paga online desde su panel; sin bloqueo PARTILOT.
        } elseif ($mode === EntityLotteryPrizeSetting::MODE_ONLINE) {
            $fundsStatus = EntityLotteryPrizeSetting::FUNDS_PENDING;
            $contractStatus = EntityLotteryPrizeSetting::CONTRACT_PENDING;
        } elseif ($hasSoldDigital) {
            $fundsStatus = EntityLotteryPrizeSetting::FUNDS_PENDING;
            $contractStatus = EntityLotteryPrizeSetting::CONTRACT_PENDING;
        }

        $defaults = $this->defaultContactFromEntity($entity);

        $setting = EntityLotteryPrizeSetting::query()->updateOrCreate(
            [
                'entity_id' => $entityId,
                'lottery_id' => $lotteryId,
            ],
            array_merge($defaults, [
                'prize_payment_mode' => $mode,
                'online_payer' => $resolvedPayer,
                'mode_locked_at' => now(),
                'mode_locked_by_user_id' => $userId,
                'has_sold_digital_participations' => $hasSoldDigital,
                'funds_status' => $fundsStatus,
                'contract_status' => $contractStatus,
                'online_payments_enabled' => $onlineEnabled,
                'presencial_payments_enabled' => $presencialEnabled,
                'blocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_BLOCKED_MESSAGE,
                'unlocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE,
            ])
        );

        $this->log($setting, 'mode_selected', [
            'mode' => $mode,
            'online_payer' => $resolvedPayer,
            'has_sold_digital_participations' => $hasSoldDigital,
        ], $userId);

        $this->syncSettingIfSavedScrutinyExists($setting);

        return $setting->fresh();
    }

    /**
     * Tras publicar el escrutinio: recalcula deuda y flags de habilitación.
     */
    public function syncAfterScrutinySaved(AdministrationLotteryScrutiny $scrutiny): void
    {
        $settings = EntityLotteryPrizeSetting::query()
            ->where('lottery_id', $scrutiny->lottery_id)
            ->whereHas('entity', fn ($q) => $q->where('administration_id', $scrutiny->administration_id))
            ->get();

        foreach ($settings as $setting) {
            $this->syncSettingAfterScrutiny($setting, $scrutiny);
        }

        \App\Jobs\NotifyWalletStorageAfterScrutinyJob::dispatch($scrutiny->id);
    }

    public function getSettings(int $entityId, int $lotteryId): ?EntityLotteryPrizeSetting
    {
        return EntityLotteryPrizeSetting::query()
            ->where('entity_id', $entityId)
            ->where('lottery_id', $lotteryId)
            ->first();
    }

    public function refreshFundsFromSavedScrutiny(EntityLotteryPrizeSetting $setting): EntityLotteryPrizeSetting
    {
        $this->syncSettingIfSavedScrutinyExists($setting);

        return $setting->fresh();
    }

    /**
     * @return array{cobrable: bool, payment_blocked: bool, block_reason: ?string, user_message: ?string}
     */
    public function evaluateOnlineCollection(Participation $participation, ?float $prizeAmount = null): array
    {
        $lotteryId = $this->resolveLotteryId($participation);
        $entityId = (int) ($participation->entity_id ?? $participation->set?->entity_id ?? 0);

        if (! $lotteryId || ! $entityId) {
            return $this->blocked('missing_context', null, $prizeAmount);
        }

        $setting = $this->getSettings($entityId, $lotteryId);
        if (! $setting) {
            return $this->blocked('no_settings', null, $prizeAmount);
        }

        if ($this->isCollectedOrReservedOnline($participation)) {
            return $this->blocked('already_collected', EntityLotteryPrizeSetting::LOPD_ALREADY_MANAGED_MESSAGE, $prizeAmount);
        }

        if (! $setting->isModeOnline()) {
            if ($this->canCollectDigitalUnderPresencialMode($participation, $setting)) {
                return $this->evaluateDigitalOnlineUnderPresencialMode($setting, $prizeAmount);
            }

            return $this->blocked('mode_presencial', $this->presencialContactMessage($setting), $prizeAmount);
        }

        if ($setting->isOnlinePayerEntity()) {
            if (! $setting->online_payments_enabled) {
                return $this->blocked('awaiting_scrutiny', $setting->blocked_user_message, $prizeAmount);
            }

            return [
                'cobrable' => true,
                'payment_blocked' => false,
                'block_reason' => null,
                'user_message' => $this->formatUserMessage($setting->unlocked_user_message, $prizeAmount),
            ];
        }

        if (! $setting->online_payments_enabled) {
            return $this->blocked('not_activated', $setting->blocked_user_message, $prizeAmount);
        }

        if (! $setting->fundsAreConfirmed()) {
            return $this->blocked('funds_pending', $setting->blocked_user_message, $prizeAmount);
        }

        if (! $setting->contractIsSatisfied()) {
            return $this->blocked('contract_pending', $setting->blocked_user_message, $prizeAmount);
        }

        return [
            'cobrable' => true,
            'payment_blocked' => false,
            'block_reason' => null,
            'user_message' => $this->formatUserMessage($setting->unlocked_user_message, $prizeAmount),
        ];
    }

    /**
     * @return array{allowed: bool, reason: ?string, message: ?string}
     */
    public function evaluatePresencialPayment(Participation $participation, ?float $prizeAmount = null): array
    {
        if ($participation->requiresOnlinePrizeCollection()) {
            return ['allowed' => false, 'reason' => 'online_only', 'message' => 'Esta participación solo se cobra online (digital o digitalizada).'];
        }

        $lotteryId = $this->resolveLotteryId($participation);
        $entityId = (int) ($participation->entity_id ?? $participation->set?->entity_id ?? 0);

        if (! $lotteryId || ! $entityId) {
            return ['allowed' => false, 'reason' => 'missing_context', 'message' => 'No se puede validar el pago presencial.'];
        }

        if ($this->isCollectedOrReservedOnline($participation)) {
            return [
                'allowed' => false,
                'reason' => 'collected_online',
                'message' => EntityLotteryPrizeSetting::LOPD_ALREADY_MANAGED_MESSAGE,
            ];
        }

        if ($participation->status === 'pagada' || $participation->collected_at) {
            return ['allowed' => false, 'reason' => 'already_paid', 'message' => 'Esta participación ya está pagada.'];
        }

        $setting = $this->getSettings($entityId, $lotteryId);
        if (! $setting) {
            return ['allowed' => false, 'reason' => 'no_settings', 'message' => 'El cobro de premios aún no está configurado para esta entidad.'];
        }

        if (! $setting->isModePresencial()) {
            return ['allowed' => false, 'reason' => 'mode_online', 'message' => 'Esta entidad gestiona el cobro de premios online a través de PARTILOT.'];
        }

        if ($setting->has_sold_digital_participations) {
            if (! $setting->fundsAreConfirmed() || ! $setting->contractIsSatisfied()) {
                return ['allowed' => false, 'reason' => 'digital_block', 'message' => 'El pago presencial está bloqueado hasta completar el ingreso de participaciones digitales y la firma del contrato.'];
            }
        }

        if (! $setting->presencial_payments_enabled) {
            return ['allowed' => false, 'reason' => 'not_activated', 'message' => 'El pago presencial aún no está habilitado para esta entidad.'];
        }

        return ['allowed' => true, 'reason' => null, 'message' => null];
    }

    /**
     * @param  array<int>  $participationIds
     */
    public function canGroupMultientityTransfer(array $participationIds): array
    {
        $participations = Participation::query()
            ->whereIn('id', $participationIds)
            ->with('set.reserve')
            ->get();

        if ($participations->isEmpty()) {
            return ['allowed' => false, 'message' => 'Ninguna participación válida.'];
        }

        $entityIds = $participations->map(fn (Participation $p) => (int) ($p->entity_id ?? $p->set?->entity_id ?? 0))
            ->filter()
            ->unique()
            ->values();

        if ($entityIds->count() < 2) {
            return ['allowed' => true, 'message' => null];
        }

        foreach ($participations as $participation) {
            $evaluation = $this->evaluateOnlineCollection($participation);
            if (! $evaluation['cobrable']) {
                return [
                    'allowed' => false,
                    'message' => 'No puedes agrupar participaciones de varias entidades hasta que todas estén habilitadas para cobro online.',
                ];
            }
        }

        return ['allowed' => true, 'message' => null];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, EntityLotteryPrizeSetting>
     */
    public function listForSuperAdmin(?string $fundsStatus = null, ?string $mode = null)
    {
        $settings = EntityLotteryPrizeSetting::query()
            ->with(['entity.administration', 'lottery'])
            ->whereNotNull('prize_payment_mode')
            ->when($fundsStatus, fn ($q) => $q->where('funds_status', $fundsStatus))
            ->when($mode, fn ($q) => $q->where('prize_payment_mode', $mode))
            ->orderByDesc('updated_at')
            ->get();

        foreach ($settings as $setting) {
            $this->syncSettingIfSavedScrutinyExists($setting);
        }

        if ($settings->isEmpty()) {
            return $settings;
        }

        return EntityLotteryPrizeSetting::query()
            ->with(['entity.administration', 'lottery'])
            ->whereIn('id', $settings->pluck('id'))
            ->orderByDesc('updated_at')
            ->get();
    }

    public function confirmFunds(
        EntityLotteryPrizeSetting $setting,
        int $userId,
        ?float $depositedAmount = null
    ): EntityLotteryPrizeSetting {
        if ($setting->funds_status === EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED) {
            throw new \InvalidArgumentException('Esta entidad no requiere confirmación de fondos.');
        }

        $amount = $depositedAmount ?? (float) $setting->funds_required_amount;

        $setting->update([
            'funds_deposited_amount' => $amount,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_CONFIRMED,
            'funds_confirmed_at' => now(),
            'funds_confirmed_by_user_id' => $userId,
        ]);

        $this->log($setting, 'funds_confirmed', [
            'funds_deposited_amount' => $amount,
        ], $userId);

        return $setting->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function presencialContactPayload(EntityLotteryPrizeSetting $setting): array
    {
        return [
            'text' => $setting->presencial_contact_text,
            'address' => $setting->presencial_contact_address,
            'city' => $setting->presencial_contact_city,
            'province' => $setting->presencial_contact_province,
            'schedule' => $setting->presencial_contact_schedule,
            'phone' => $setting->presencial_contact_phone,
            'email' => $setting->presencial_contact_email,
            'notes' => $setting->presencial_contact_notes,
            'formatted' => $this->presencialContactMessage($setting),
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function updatePresencialContact(
        EntityLotteryPrizeSetting $setting,
        array $data,
        int $userId
    ): EntityLotteryPrizeSetting {
        if (! $setting->isModePresencial()) {
            throw new \InvalidArgumentException('El contacto presencial solo aplica a modalidad presencial.');
        }

        $setting->update(array_filter([
            'presencial_contact_text' => $data['presencial_contact_text'] ?? null,
            'presencial_contact_address' => $data['presencial_contact_address'] ?? null,
            'presencial_contact_city' => $data['presencial_contact_city'] ?? null,
            'presencial_contact_province' => $data['presencial_contact_province'] ?? null,
            'presencial_contact_schedule' => $data['presencial_contact_schedule'] ?? null,
            'presencial_contact_phone' => $data['presencial_contact_phone'] ?? null,
            'presencial_contact_email' => $data['presencial_contact_email'] ?? null,
            'presencial_contact_notes' => $data['presencial_contact_notes'] ?? null,
        ], fn ($v) => $v !== null));

        $this->log($setting, 'presencial_contact_updated', $data, $userId);

        return $setting->fresh();
    }

    public function entityCanFundOnlinePayout(int $entityId, int $lotteryId): bool
    {
        $setting = $this->getSettings($entityId, $lotteryId);
        if (! $setting) {
            return false;
        }

        if ($setting->isModeOnline()) {
            if ($setting->isOnlinePayerEntity()) {
                return $setting->online_payments_enabled;
            }

            return $setting->online_payments_enabled
                && $setting->fundsAreConfirmed()
                && $setting->contractIsSatisfied();
        }

        if ($setting->isModePresencial() && $setting->has_sold_digital_participations) {
            return $setting->online_payments_enabled
                && $setting->fundsAreConfirmed()
                && $setting->contractIsSatisfied();
        }

        return false;
    }

    /**
     * @return array{allowed: bool, message: ?string}
     */
    public function validateCollectionForSepaExport(ParticipationCollection $collection): array
    {
        $collection->loadMissing(['items.participation.set.reserve', 'items.participation.entity']);

        $checked = [];
        foreach ($collection->items as $item) {
            $participation = $item->participation;
            if (! $participation) {
                continue;
            }

            $entityId = (int) ($item->entity_id ?? $participation->entity_id ?? $participation->set?->entity_id ?? 0);
            $lotteryId = (int) ($participation->set?->reserve?->lottery_id ?? 0);
            if (! $entityId || ! $lotteryId) {
                return [
                    'allowed' => false,
                    'message' => 'No se puede verificar la solvencia de una participación del lote.',
                ];
            }

            $key = $entityId.':'.$lotteryId;
            if (isset($checked[$key])) {
                continue;
            }

            if (! $this->entityCanFundOnlinePayout($entityId, $lotteryId)) {
                $entityName = $participation->entity?->name ?? 'Entidad';
                $setting = $this->getSettings($entityId, $lotteryId);
                $message = $setting && $setting->isOnlinePayerEntity()
                    ? "«{$entityName}» aún no tiene habilitado el cobro online para este sorteo."
                    : "«{$entityName}» no tiene fondos confirmados y cobro online activo para este sorteo.";

                return [
                    'allowed' => false,
                    'message' => $message,
                ];
            }

            $checked[$key] = true;
        }

        return ['allowed' => true, 'message' => null];
    }

    protected function canCollectDigitalUnderPresencialMode(
        Participation $participation,
        EntityLotteryPrizeSetting $setting
    ): bool {
        return $setting->isModePresencial()
            && $setting->has_sold_digital_participations
            && $participation->requiresOnlinePrizeCollection();
    }

    /**
     * @return array{cobrable: bool, payment_blocked: bool, block_reason: ?string, user_message: ?string}
     */
    protected function evaluateDigitalOnlineUnderPresencialMode(
        EntityLotteryPrizeSetting $setting,
        ?float $prizeAmount
    ): array {
        if (! $setting->fundsAreConfirmed()) {
            return $this->blocked('funds_pending', $setting->blocked_user_message, $prizeAmount);
        }

        if (! $setting->contractIsSatisfied()) {
            return $this->blocked('contract_pending', $setting->blocked_user_message, $prizeAmount);
        }

        if (! $setting->online_payments_enabled) {
            return $this->blocked('not_activated', $setting->blocked_user_message, $prizeAmount);
        }

        return [
            'cobrable' => true,
            'payment_blocked' => false,
            'block_reason' => null,
            'user_message' => $this->formatUserMessage($setting->unlocked_user_message, $prizeAmount),
        ];
    }

    public function markContractSigned(EntityLotteryPrizeSetting $setting, int $userId): EntityLotteryPrizeSetting
    {
        if ($setting->contract_status === EntityLotteryPrizeSetting::CONTRACT_NOT_REQUIRED) {
            throw new \InvalidArgumentException('No se requiere contrato para esta configuración.');
        }

        $setting->update([
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_SIGNED,
            'contract_signed_at' => now(),
            'contract_token' => null,
        ]);

        $this->log($setting, 'contract_signed', ['via' => 'superadmin_manual'], $userId);

        return $setting->fresh();
    }

    public function activateOnlinePayments(
        EntityLotteryPrizeSetting $setting,
        int $userId,
        ?string $unlockedMessage = null
    ): EntityLotteryPrizeSetting {
        if (! $setting->isModeOnline()) {
            throw new \InvalidArgumentException('La modalidad configurada no es pago online.');
        }

        if ($setting->isOnlinePayerEntity()) {
            throw new \InvalidArgumentException('El cobro online lo gestiona la entidad; no requiere activación PARTILOT.');
        }

        if ($setting->funds_status === EntityLotteryPrizeSetting::FUNDS_PENDING) {
            throw new \InvalidArgumentException('Debes confirmar el ingreso de fondos antes de activar el cobro online.');
        }

        if (! $setting->contractIsSatisfied()) {
            throw new \InvalidArgumentException('Debes registrar la firma del contrato antes de activar el cobro online.');
        }

        $setting->update([
            'online_payments_enabled' => true,
            'unlocked_user_message' => $unlockedMessage ?: $setting->unlocked_user_message ?: EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE,
        ]);

        $this->log($setting, 'online_activated', [
            'unlocked_user_message' => $setting->unlocked_user_message,
        ], $userId);

        $this->notifyUsersPrizePaymentsUnlocked($setting, 'online');

        return $setting->fresh();
    }

    public function activatePresencialPayments(EntityLotteryPrizeSetting $setting, int $userId): EntityLotteryPrizeSetting
    {
        if (! $setting->isModePresencial()) {
            throw new \InvalidArgumentException('La modalidad configurada no es pago presencial.');
        }

        if ($setting->has_sold_digital_participations) {
            if ($setting->funds_status === EntityLotteryPrizeSetting::FUNDS_PENDING) {
                throw new \InvalidArgumentException('Debes confirmar el ingreso de fondos de participaciones digitales.');
            }
            if (! $setting->contractIsSatisfied()) {
                throw new \InvalidArgumentException('Debes registrar la firma del contrato antes de activar el pago presencial.');
            }
        }

        $setting->update([
            'presencial_payments_enabled' => true,
        ]);

        if ($setting->has_sold_digital_participations) {
            $setting->update(['online_payments_enabled' => true]);
            $this->notifyUsersPrizePaymentsUnlocked($setting->fresh(), 'online');
        }

        $this->log($setting, 'presencial_activated', [], $userId);

        return $setting->fresh();
    }

    public function updateAdminMessages(
        EntityLotteryPrizeSetting $setting,
        int $userId,
        ?string $blockedMessage,
        ?string $unlockedMessage
    ): EntityLotteryPrizeSetting {
        $setting->update(array_filter([
            'blocked_user_message' => $blockedMessage,
            'unlocked_user_message' => $unlockedMessage,
        ], fn ($v) => $v !== null));

        $this->log($setting, 'messages_updated', [
            'blocked_user_message' => $blockedMessage,
            'unlocked_user_message' => $unlockedMessage,
        ], $userId);

        return $setting->fresh();
    }

    public function sendContractInvitation(EntityLotteryPrizeSetting $setting, int $userId): EntityLotteryPrizeSetting
    {
        if ($setting->contract_status !== EntityLotteryPrizeSetting::CONTRACT_PENDING) {
            throw new \InvalidArgumentException('No se requiere envío de contrato para esta configuración.');
        }

        $setting->loadMissing('entity');
        $entity = $setting->entity;
        $recipientEmail = $entity?->email;
        if (! $recipientEmail) {
            throw new \InvalidArgumentException('La entidad no tiene email configurado para enviar el contrato.');
        }

        $token = \Illuminate\Support\Str::random(64);
        $setting->update([
            'contract_token' => $token,
            'contract_sent_at' => now(),
        ]);

        $this->log($setting, 'contract_sent', ['recipient_email' => $recipientEmail], $userId);

        try {
            \Illuminate\Support\Facades\Mail::to($recipientEmail)->send(
                new \App\Mail\PrizePaymentContractMail($setting->fresh(['entity', 'lottery']), $token)
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando contrato de premios: '.$e->getMessage());
            throw new \RuntimeException('No se pudo enviar el email del contrato.');
        }

        return $setting->fresh();
    }

    public function signContractByToken(
        string $token,
        string $signerName,
        ?int $userId = null,
        ?string $ip = null
    ): EntityLotteryPrizeSetting {
        $setting = EntityLotteryPrizeSetting::query()
            ->where('contract_token', $token)
            ->first();

        if (! $setting || $setting->contract_status !== EntityLotteryPrizeSetting::CONTRACT_PENDING) {
            throw new \InvalidArgumentException('El enlace de firma no es válido o el contrato ya fue gestionado.');
        }

        $setting->update([
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_SIGNED,
            'contract_signed_at' => now(),
            'contract_token' => null,
            'contract_signed_by_user_id' => $userId,
            'contract_signer_name' => $signerName,
        ]);

        $this->log($setting, 'contract_signed', [
            'signer_name' => $signerName,
            'ip' => $ip,
            'via' => 'token',
        ], $userId);

        return $setting->fresh();
    }

    public function changeModeBySuperAdmin(
        EntityLotteryPrizeSetting $setting,
        string $mode,
        int $userId,
        ?string $onlinePayer = null
    ): EntityLotteryPrizeSetting {
        if (! in_array($mode, [EntityLotteryPrizeSetting::MODE_PRESENCIAL, EntityLotteryPrizeSetting::MODE_ONLINE], true)) {
            throw new \InvalidArgumentException('Modalidad no válida.');
        }

        $resolvedPayer = $mode === EntityLotteryPrizeSetting::MODE_ONLINE
            ? ($onlinePayer ?? EntityLotteryPrizeSetting::PAYER_PARTILOT)
            : null;

        $previous = $setting->prize_payment_mode;
        $hasDigital = $setting->has_sold_digital_participations;

        $fundsStatus = EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
        $contractStatus = EntityLotteryPrizeSetting::CONTRACT_NOT_REQUIRED;

        if ($mode === EntityLotteryPrizeSetting::MODE_ONLINE && $resolvedPayer === EntityLotteryPrizeSetting::PAYER_ENTITY) {
            // Sin requisitos PARTILOT.
        } elseif ($mode === EntityLotteryPrizeSetting::MODE_ONLINE) {
            $fundsStatus = EntityLotteryPrizeSetting::FUNDS_PENDING;
            $contractStatus = EntityLotteryPrizeSetting::CONTRACT_PENDING;
        } elseif ($hasDigital) {
            $fundsStatus = EntityLotteryPrizeSetting::FUNDS_PENDING;
            $contractStatus = EntityLotteryPrizeSetting::CONTRACT_PENDING;
        }

        $setting->update([
            'prize_payment_mode' => $mode,
            'online_payer' => $resolvedPayer,
            'funds_status' => $fundsStatus,
            'contract_status' => $contractStatus,
            'online_payments_enabled' => false,
            'presencial_payments_enabled' => $mode === EntityLotteryPrizeSetting::MODE_PRESENCIAL && ! $hasDigital,
            'contract_token' => null,
            'contract_sent_at' => null,
        ]);

        $this->log($setting, 'mode_changed_by_superadmin', [
            'from' => $previous,
            'to' => $mode,
            'online_payer' => $resolvedPayer,
        ], $userId);

        $this->syncSettingIfSavedScrutinyExists($setting);

        return $setting->fresh();
    }

    public function blockPaymentsBySuperAdmin(EntityLotteryPrizeSetting $setting, int $userId): EntityLotteryPrizeSetting
    {
        $setting->update([
            'online_payments_enabled' => false,
            'presencial_payments_enabled' => false,
        ]);

        $this->log($setting, 'online_blocked', [], $userId);

        return $setting->fresh();
    }

    /**
     * @return list<int>
     */
    public function winningWalletUserIdsForEntityLottery(int $entityId, int $lotteryId): array
    {
        $apiController = app(\App\Http\Controllers\ApiController::class);
        $userIds = [];

        $participations = Participation::query()
            ->where('entity_id', $entityId)
            ->whereNotNull('buyer_name')
            ->whereNull('collected_at')
            ->whereNull('donated_at')
            ->where('status', '!=', 'pagada')
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
            ->with('set.reserve.lottery')
            ->get();

        foreach ($participations as $participation) {
            $buyer = (string) $participation->buyer_name;
            if ($buyer === '' || ! ctype_digit($buyer)) {
                continue;
            }

            $ref = $this->buildReferenceFromParticipation($participation);
            if ($ref === '') {
                continue;
            }

            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            if (! ($prizeInfo['has_won'] ?? false) || (float) ($prizeInfo['prize_amount'] ?? 0) <= 0) {
                continue;
            }

            $userIds[] = (int) $buyer;
        }

        return array_values(array_unique($userIds));
    }

    protected function notifyUsersPrizePaymentsUnlocked(EntityLotteryPrizeSetting $setting, string $channel): void
    {
        $setting->loadMissing(['entity', 'lottery']);
        $entity = $setting->entity;
        $lottery = $setting->lottery;
        if (! $entity || ! $lottery) {
            return;
        }

        $userIds = $this->winningWalletUserIdsForEntityLottery((int) $setting->entity_id, (int) $setting->lottery_id);
        if (empty($userIds)) {
            return;
        }

        $inbox = app(AppInboxNotificationService::class);
        $senderId = $inbox->resolveSenderIdForEntity((int) $setting->entity_id) ?? auth()->id();
        if (! $senderId) {
            return;
        }

        $title = 'Cobro de premio disponible';
        $message = $channel === 'online'
            ? "Ya puedes gestionar el cobro de tus participaciones premiadas de {$entity->name} en el sorteo {$lottery->name}."
            : "El cobro presencial de premios de {$entity->name} está habilitado para el sorteo {$lottery->name}.";

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            $inbox->notifyUser(
                recipientUserId: $userId,
                entityId: (int) $setting->entity_id,
                administrationId: (int) $entity->administration_id,
                senderId: (int) $senderId,
                kind: 'premio_cobro_activado',
                title: $title,
                message: $message,
                meta: [
                    'entity_lottery_prize_setting_id' => $setting->id,
                    'lottery_id' => $setting->lottery_id,
                    'channel' => $channel,
                ],
                sendPush: true
            );

            if ($user->email) {
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(
                        new \App\Mail\PrizePaymentActivatedMail($user, $entity, $lottery, $message)
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Fallo enviando email de activación de cobro de premios: '.$e->getMessage());
                }
            }
        }
    }

    protected function buildReferenceFromParticipation(Participation $participation): string
    {
        $participation->loadMissing('set');
        $set = $participation->set;
        if (! $set || ! is_array($set->tickets)) {
            return '';
        }

        foreach ($set->tickets as $ticket) {
            if (isset($ticket['n']) && (int) $ticket['n'] === (int) $participation->participation_number) {
                return (string) ($ticket['r'] ?? '');
            }
        }

        return '';
    }

    public function entityHasSoldDigitalParticipations(int $entityId, int $lotteryId): bool
    {
        return Participation::query()
            ->where('entity_id', $entityId)
            ->sold()
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
            ->where(function ($query) {
                $query->whereRaw("participation_code LIKE '1D/%'")
                    ->orWhere('wallet_mode', Participation::WALLET_MODE_DIGITAL);
            })
            ->exists();
    }

    protected function syncSettingIfSavedScrutinyExists(EntityLotteryPrizeSetting $setting): void
    {
        $entity = $setting->relationLoaded('entity')
            ? $setting->entity
            : Entity::query()->find($setting->entity_id);

        if (! $entity?->administration_id) {
            return;
        }

        $scrutiny = AdministrationLotteryScrutiny::query()
            ->where('lottery_id', $setting->lottery_id)
            ->where('administration_id', $entity->administration_id)
            ->where('is_saved', true)
            ->first();

        if ($scrutiny) {
            $this->syncSettingAfterScrutiny($setting, $scrutiny);
        }
    }

    protected function syncSettingAfterScrutiny(EntityLotteryPrizeSetting $setting, AdministrationLotteryScrutiny $scrutiny): void
    {
        $fundsRequired = $this->calculateFundsRequiredAmount($setting, $scrutiny);
        $wasOnlineEnabled = $setting->online_payments_enabled;

        $updates = [
            'funds_required_amount' => $fundsRequired,
        ];

        $hasSoldOnlineCollectible = $this->entityHasSoldDigitalParticipations(
            (int) $setting->entity_id,
            (int) $setting->lottery_id
        );
        if ($hasSoldOnlineCollectible !== (bool) $setting->has_sold_digital_participations) {
            $updates['has_sold_digital_participations'] = $hasSoldOnlineCollectible;
        }

        if ($setting->funds_status === EntityLotteryPrizeSetting::FUNDS_CONFIRMED) {
            if ($fundsRequired > (float) $setting->funds_deposited_amount) {
                $updates['funds_status'] = EntityLotteryPrizeSetting::FUNDS_PENDING;
            }
        } else {
            $resolvedFundsStatus = $this->resolveFundsStatusAfterScrutiny($setting, $fundsRequired);
            if ($resolvedFundsStatus !== $setting->funds_status) {
                $updates['funds_status'] = $resolvedFundsStatus;
            }
        }

        if ($setting->isModePresencial()
            && ! $setting->has_sold_digital_participations
            && ! $setting->presencial_payments_enabled) {
            $updates['presencial_payments_enabled'] = true;
            $updates['funds_status'] = EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
        }

        if ($setting->isModeOnline()
            && $setting->isOnlinePayerEntity()
            && ! $setting->online_payments_enabled) {
            $updates['online_payments_enabled'] = true;
            $updates['funds_status'] = EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
        }

        $hasChanges = false;
        foreach ($updates as $field => $value) {
            $current = $setting->{$field};
            if (in_array($field, ['funds_required_amount', 'funds_deposited_amount'], true)) {
                if ((float) $current !== (float) $value) {
                    $hasChanges = true;
                    break;
                }
            } elseif ((bool) $current !== (bool) $value || $current !== $value) {
                $hasChanges = true;
                break;
            }
        }

        if (! $hasChanges) {
            return;
        }

        $setting->update($updates);

        if ($setting->isOnlinePayerEntity()
            && ($updates['online_payments_enabled'] ?? false)
            && ! $wasOnlineEnabled) {
            $this->notifyUsersPrizePaymentsUnlocked($setting->fresh(), 'online');
        }
    }

    protected function resolveFundsStatusAfterScrutiny(
        EntityLotteryPrizeSetting $setting,
        float $fundsRequired
    ): string {
        if ($setting->isModeOnline()) {
            if ($setting->isOnlinePayerEntity()) {
                return EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
            }

            return $fundsRequired > 0
                ? EntityLotteryPrizeSetting::FUNDS_PENDING
                : EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
        }

        if ($setting->isModePresencial()) {
            if ($setting->has_sold_digital_participations) {
                return $fundsRequired > 0
                    ? EntityLotteryPrizeSetting::FUNDS_PENDING
                    : EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
            }

            return EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED;
        }

        return $setting->funds_status;
    }

    protected function calculateFundsRequiredAmount(
        EntityLotteryPrizeSetting $setting,
        AdministrationLotteryScrutiny $scrutiny
    ): float {
        $query = ScrutinyDetailedResult::query()
            ->where('scrutiny_id', $scrutiny->id)
            ->where('entity_id', $setting->entity_id);

        if ($setting->isModeOnline()) {
            if ($setting->isOnlinePayerEntity()) {
                return 0.0;
            }

            return (float) $query->sum('premio_total');
        }

        if ($setting->isModePresencial() && $setting->has_sold_digital_participations) {
            return $this->sumDigitalPrizeAmountForEntity($setting->entity_id, $setting->lottery_id, $scrutiny);
        }

        return 0.0;
    }

    protected function sumDigitalPrizeAmountForEntity(
        int $entityId,
        int $lotteryId,
        AdministrationLotteryScrutiny $scrutiny
    ): float {
        $participations = Participation::query()
            ->where('entity_id', $entityId)
            ->sold()
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
            ->get()
            ->filter(fn (Participation $participation) => $participation->requiresOnlinePrizeCollection());

        if ($participations->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        $nativeDigitalSetIds = $participations
            ->filter(fn (Participation $participation) => str_starts_with((string) $participation->participation_code, '1D/'))
            ->pluck('set_id')
            ->unique()
            ->all();

        if (! empty($nativeDigitalSetIds)) {
            $total += (float) ScrutinyDetailedResult::query()
                ->where('scrutiny_id', $scrutiny->id)
                ->where('entity_id', $entityId)
                ->whereIn('set_id', $nativeDigitalSetIds)
                ->sum('premio_total');
        }

        $digitalizedPhysical = $participations
            ->filter(fn (Participation $participation) => ! str_starts_with((string) $participation->participation_code, '1D/'));

        foreach ($digitalizedPhysical->groupBy('set_id') as $setId => $group) {
            $count = $group->count();
            $results = ScrutinyDetailedResult::query()
                ->where('scrutiny_id', $scrutiny->id)
                ->where('entity_id', $entityId)
                ->where('set_id', $setId)
                ->where('total_participations', '>', 0)
                ->get();

            foreach ($results as $result) {
                $total += $count * (float) $result->premio_por_participacion;
            }
        }

        return $total;
    }

    protected function resolveLotteryId(Participation $participation): ?int
    {
        $participation->loadMissing('set.reserve');

        return $participation->set?->reserve?->lottery_id
            ? (int) $participation->set->reserve->lottery_id
            : null;
    }

    protected function isNativeDigitalParticipation(Participation $participation): bool
    {
        $code = (string) ($participation->participation_code ?? '');
        if (str_starts_with($code, '1D/')) {
            return true;
        }

        $participation->loadMissing('set');

        return ($participation->set?->digital_participations ?? 0) > 0
            && (int) ($participation->set?->physical_participations ?? 0) <= 0;
    }

    protected function isCollectedOrReservedOnline(Participation $participation): bool
    {
        if ($participation->collected_at || $participation->donated_at || $participation->status === 'pagada') {
            return true;
        }

        $reservedIds = ParticipationCollection::reservedParticipationIds();

        if (in_array($participation->id, $reservedIds, true)) {
            return true;
        }

        return ParticipationCollectionItem::query()
            ->where('participation_id', $participation->id)
            ->whereHas('collection', fn ($q) => $q->where('status', ParticipationCollection::STATUS_VERIFIED))
            ->exists();
    }

    /**
     * @return array{cobrable: bool, payment_blocked: bool, block_reason: ?string, user_message: ?string}
     */
    protected function blocked(?string $reason, ?string $messageTemplate, ?float $prizeAmount): array
    {
        $message = $messageTemplate ?? EntityLotteryPrizeSetting::DEFAULT_BLOCKED_MESSAGE;

        return [
            'cobrable' => false,
            'payment_blocked' => true,
            'block_reason' => $reason,
            'user_message' => $this->formatUserMessage($message, $prizeAmount),
        ];
    }

    protected function formatUserMessage(?string $template, ?float $prizeAmount): ?string
    {
        if ($template === null || $template === '') {
            return null;
        }

        $amount = $prizeAmount !== null
            ? number_format($prizeAmount, 2, ',', '.')
            : '—';

        return str_replace('{amount}', $amount, $template);
    }

    protected function presencialContactMessage(EntityLotteryPrizeSetting $setting): ?string
    {
        if ($setting->presencial_contact_text) {
            return $setting->presencial_contact_text;
        }

        $parts = array_filter([
            $setting->presencial_contact_address,
            $setting->presencial_contact_city,
            $setting->presencial_contact_province,
            $setting->presencial_contact_schedule ? 'Horario: '.$setting->presencial_contact_schedule : null,
            $setting->presencial_contact_phone ? 'Tel: '.$setting->presencial_contact_phone : null,
            $setting->presencial_contact_email ? 'Email: '.$setting->presencial_contact_email : null,
            $setting->presencial_contact_notes,
        ]);

        if (empty($parts)) {
            return 'Póngase en contacto con la entidad para cobrar su premio en sus instalaciones.';
        }

        return implode("\n", $parts);
    }

    /**
     * @return array<string, string|null>
     */
    protected function defaultContactFromEntity(Entity $entity): array
    {
        return [
            'presencial_contact_address' => $entity->address,
            'presencial_contact_city' => $entity->city,
            'presencial_contact_province' => $entity->province,
            'presencial_contact_phone' => $entity->phone,
            'presencial_contact_email' => $entity->email,
            'presencial_contact_notes' => $entity->comments,
        ];
    }

    protected function log(
        EntityLotteryPrizeSetting $setting,
        string $event,
        array $payload = [],
        ?int $userId = null
    ): void {
        EntityLotteryPrizeActivationLog::query()->create([
            'entity_lottery_prize_setting_id' => $setting->id,
            'event' => $event,
            'payload' => $payload,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
