<?php

namespace App\Services;

use App\Http\Controllers\ApiController;
use App\Models\LegalAcceptance;
use App\Models\Participation;
use App\Models\ParticipationGift;
use App\Models\ParticipationCollection;
use App\Models\User;
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

        return [
            'can_request' => $pendingPrizes === 0 && ! $this->isDeletionScheduled($user),
            'pending_prize_count' => $pendingPrizes,
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
        if (! $participation->set || ! is_array($participation->set->tickets)) {
            return '';
        }

        foreach ($participation->set->tickets as $ticket) {
            if (isset($ticket['n']) && $ticket['n'] == $participation->participation_number) {
                return $ticket['r'] ?? '';
            }
        }

        return '';
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
        });

        $user->refresh();

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
}
