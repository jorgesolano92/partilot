<?php

namespace App\Services;

use App\Mail\ParticipationGiftRecipientMail;
use App\Mail\ParticipationGiftSenderMail;
use App\Models\Participation;
use App\Models\ParticipationActivityLog;
use App\Models\ParticipationGift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParticipationGiftService
{
    public function __construct(
        protected AppInboxNotificationService $inbox,
        protected CommunicationEmailService $communicationEmail
    ) {}

    /**
     * @return array{gift: ParticipationGift, gifted_to_email: string}
     */
    public function createGift(User $fromUser, Participation $participation, string $email, ?string $message = null): array
    {
        $email = strtolower(trim($email));
        $destinatario = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($destinatario && (string) $destinatario->id === (string) $fromUser->id) {
            throw new \InvalidArgumentException('No puedes regalarte la participación a ti mismo.');
        }

        $gift = ParticipationGift::create([
            'participation_id' => $participation->id,
            'from_user_id' => $fromUser->id,
            'to_user_id' => $destinatario?->id,
            'to_email' => $destinatario ? null : $email,
            'status' => ParticipationGift::STATUS_PENDING,
            'message' => $message ? trim($message) : null,
            'claim_token' => $destinatario ? null : ParticipationGift::generateClaimToken(),
        ]);

        $gift->load(['fromUser', 'toUser', 'participation.set.entity']);

        $this->logGiftActivity($gift, 'gift_sent', (int) $fromUser->id, 'Regalo enviado a '.$gift->recipientEmail());
        $this->sendGiftEmails($gift);
        $this->notifyGiftReceived($gift);

        return [
            'gift' => $gift,
            'gifted_to_email' => $gift->recipientEmail(),
        ];
    }

    public function acceptGift(ParticipationGift $gift, User $user): ParticipationGift
    {
        if (! $gift->isPending()) {
            throw new \InvalidArgumentException('Este regalo ya no está pendiente de aceptación.');
        }

        if ($gift->to_user_id && (int) $gift->to_user_id !== (int) $user->id) {
            throw new \InvalidArgumentException('No puedes aceptar este regalo.');
        }

        if (! $gift->to_user_id && strtolower((string) $user->email) !== strtolower((string) $gift->to_email)) {
            throw new \InvalidArgumentException('Este regalo no está dirigido a tu cuenta.');
        }

        $accepted = DB::transaction(function () use ($gift, $user) {
            $gift = ParticipationGift::whereKey($gift->id)->lockForUpdate()->firstOrFail();
            if (! $gift->isPending()) {
                throw new \InvalidArgumentException('Este regalo ya no está pendiente de aceptación.');
            }
            $participation = Participation::whereKey($gift->participation_id)->lockForUpdate()->firstOrFail();

            $gift->to_user_id = $user->id;
            $gift->to_email = null;
            $gift->status = ParticipationGift::STATUS_ACCEPTED;
            $gift->accepted_at = now();
            $gift->save();

            $participation->buyer_name = (string) $user->id;
            $participation->save();

            return $gift->fresh(['fromUser', 'toUser', 'participation.set.entity']);
        });

        $this->logGiftActivity($accepted, 'gift_accepted', (int) $user->id, 'Regalo aceptado por '.$user->email);
        $this->notifyGiftAccepted($accepted);

        return $accepted;
    }

    /**
     * El remitente anula un regalo que el destinatario aún no ha aceptado.
     */
    public function cancelGift(ParticipationGift $gift, User $user): void
    {
        if ((int) $gift->from_user_id !== (int) $user->id) {
            throw new \InvalidArgumentException('Solo quien envió el regalo puede cancelarlo.');
        }

        $cancelled = DB::transaction(function () use ($gift) {
            $gift = ParticipationGift::whereKey($gift->id)->lockForUpdate()->firstOrFail();
            if (! $gift->isPending()) {
                throw new \InvalidArgumentException('El regalo ya no está pendiente; no se puede cancelar.');
            }

            $gift->status = ParticipationGift::STATUS_CANCELLED;
            $gift->claim_token = null;
            $gift->save();

            return $gift;
        });

        $this->logGiftActivity($cancelled, 'gift_cancelled', (int) $user->id, 'Regalo cancelado por el remitente');
    }

    public function rejectGift(ParticipationGift $gift, User $user): void
    {
        if (! $gift->isPending()) {
            throw new \InvalidArgumentException('Este regalo ya no está pendiente.');
        }

        if ($gift->to_user_id && (int) $gift->to_user_id !== (int) $user->id) {
            throw new \InvalidArgumentException('No puedes rechazar este regalo.');
        }

        if (! $gift->to_user_id && strtolower((string) $user->email) !== strtolower((string) $gift->to_email)) {
            throw new \InvalidArgumentException('Este regalo no está dirigido a tu cuenta.');
        }

        DB::transaction(function () use ($gift, $user) {
            $gift = ParticipationGift::whereKey($gift->id)->lockForUpdate()->firstOrFail();
            if (! $gift->isPending()) {
                return;
            }

            $gift->status = ParticipationGift::STATUS_REJECTED;
            $gift->rejected_at = now();
            $gift->to_user_id = $user->id;
            $gift->save();

            $this->logGiftActivity($gift, 'gift_rejected', (int) $user->id, 'Regalo rechazado por '.$user->email);
            $this->notifyGiftRejected($gift->fresh(['fromUser', 'toUser']));
        });
    }

    /**
     * Vincula regalos pendientes por email tras registro o primer login.
     */
    public function attachPendingGiftsToUser(User $user): int
    {
        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return 0;
        }

        $gifts = ParticipationGift::query()
            ->where('status', ParticipationGift::STATUS_PENDING)
            ->whereNull('to_user_id')
            ->whereRaw('LOWER(to_email) = ?', [$email])
            ->get();

        foreach ($gifts as $gift) {
            $gift->to_user_id = $user->id;
            $gift->to_email = null;
            $gift->save();
            $gift->load(['fromUser', 'participation.set.entity']);
            $this->notifyGiftReceived($gift);
        }

        return $gifts->count();
    }

    /**
     * Devuelve regalos no aceptados a partir del día anterior al sorteo
     * (incluye los enviados más tarde o que se escaparon de ejecuciones anteriores).
     */
    public function expirePendingBeforeDraw(): int
    {
        $deadline = Carbon::today()->toDateString();
        $count = 0;

        ParticipationGift::query()
            ->where('status', ParticipationGift::STATUS_PENDING)
            ->with(['participation.set.reserve.lottery'])
            ->chunkById(100, function ($gifts) use ($deadline, &$count) {
                foreach ($gifts as $gift) {
                    $drawDate = $gift->participation?->set?->reserve?->lottery?->draw_date;
                    if (! $drawDate) {
                        continue;
                    }
                    $expireOn = Carbon::parse($drawDate)->subDay()->toDateString();
                    if ($expireOn > $deadline) {
                        continue;
                    }
                    $gift->status = ParticipationGift::STATUS_EXPIRED;
                    $gift->save();
                    $this->logGiftActivity($gift, 'gift_expired', (int) $gift->from_user_id, 'Regalo caducado sin aceptar antes del sorteo');
                    $count++;
                }
            });

        return $count;
    }

    public function notifyGiftReceived(ParticipationGift $gift): void
    {
        if (! $gift->to_user_id) {
            return;
        }

        try {
            $entity = $gift->participation?->set?->entity;
            $fromName = $gift->fromUser?->name ?? 'Un usuario';
            $this->inbox->notifyUser(
                recipientUserId: (int) $gift->to_user_id,
                entityId: $entity ? (int) $entity->id : null,
                administrationId: $entity?->administration_id ? (int) $entity->administration_id : null,
                senderId: (int) $gift->from_user_id,
                kind: 'regalo_participacion',
                title: 'Participación regalada',
                message: $fromName.' te ha regalado una participación.',
                meta: [
                    'gift_id' => $gift->id,
                    'participation_id' => $gift->participation_id,
                    'from_user_id' => $gift->from_user_id,
                    'rol_context' => 'usuario',
                    'screen' => 'cartera',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Inbox regalo recibido: '.$e->getMessage());
        }
    }

    public function notifyGiftAccepted(ParticipationGift $gift): void
    {
        try {
            $entity = $gift->participation?->set?->entity;
            $accepterName = $gift->toUser?->name ?? $gift->toUser?->email ?? 'El destinatario';
            $this->inbox->notifyUser(
                recipientUserId: (int) $gift->from_user_id,
                entityId: $entity ? (int) $entity->id : null,
                administrationId: $entity?->administration_id ? (int) $entity->administration_id : null,
                senderId: (int) ($gift->to_user_id ?? $gift->from_user_id),
                kind: 'regalo_aceptado',
                title: 'Regalo aceptado',
                message: $accepterName.' ha aceptado la participación que le regalaste.',
                meta: [
                    'gift_id' => $gift->id,
                    'participation_id' => $gift->participation_id,
                    'rol_context' => 'usuario',
                    'screen' => 'cartera',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Inbox regalo aceptado: '.$e->getMessage());
        }
    }

    protected function logGiftActivity(ParticipationGift $gift, string $type, ?int $userId, string $description): void
    {
        try {
            ParticipationActivityLog::log($gift->participation_id, $type, [
                'user_id' => $userId,
                'description' => $description,
                'metadata' => [
                    'gift_id' => $gift->id,
                    'from_user_id' => $gift->from_user_id,
                    'to_user_id' => $gift->to_user_id,
                    'to_email' => $gift->to_email,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Bitácora de regalo ('.$type.'): '.$e->getMessage());
        }
    }

    public function notifyGiftRejected(ParticipationGift $gift): void
    {
        try {
            $entity = $gift->participation?->set?->entity;
            $rejecterName = $gift->toUser?->name ?? $gift->to_email ?? 'El destinatario';
            $this->inbox->notifyUser(
                recipientUserId: (int) $gift->from_user_id,
                entityId: $entity ? (int) $entity->id : null,
                administrationId: $entity?->administration_id ? (int) $entity->administration_id : null,
                senderId: (int) ($gift->to_user_id ?? $gift->from_user_id),
                kind: 'regalo_rechazado',
                title: 'Regalo rechazado',
                message: $rejecterName.' ha rechazado la participación.',
                meta: [
                    'gift_id' => $gift->id,
                    'participation_id' => $gift->participation_id,
                    'rol_context' => 'usuario',
                    'screen' => 'cartera',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Inbox regalo rechazado: '.$e->getMessage());
        }
    }

    protected function sendGiftEmails(ParticipationGift $gift): void
    {
        try {
            $recipientEmail = $gift->recipientEmail();
            if ($recipientEmail === '') {
                return;
            }

            $this->communicationEmail->sendAndLog(
                recipientEmail: $recipientEmail,
                recipientRole: 'usuario',
                recipientUser: $gift->toUser,
                messageType: 'gift_recipient_notification',
                templateKey: null,
                mailClass: ParticipationGiftRecipientMail::class,
                mailPayload: ['gift_id' => $gift->id],
                context: ['source' => 'api', 'gift_id' => $gift->id],
            );

            if ($gift->fromUser && ! empty($gift->fromUser->email)) {
                $this->communicationEmail->sendAndLog(
                    recipientEmail: (string) $gift->fromUser->email,
                    recipientRole: 'usuario',
                    recipientUser: $gift->fromUser,
                    messageType: 'gift_sender_confirmation',
                    templateKey: null,
                    mailClass: ParticipationGiftSenderMail::class,
                    mailPayload: ['gift_id' => $gift->id],
                    context: ['source' => 'api', 'gift_id' => $gift->id],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Fallo enviando emails de regalo: '.$e->getMessage());
        }
    }

    /**
     * Campos extra para ítems de cartera relacionados con un regalo.
     */
    public function walletGiftFields(?ParticipationGift $gift, string $perspective): array
    {
        if (! $gift) {
            return [];
        }

        $giftedAt = $gift->created_at?->toIso8601String();
        $fromName = $gift->fromUser?->name ?? $gift->fromUser?->email;
        $toEmail = $gift->recipientEmail();

        if ($perspective === 'sender') {
            return [
                'gift_id' => $gift->id,
                'gift_status' => $gift->status,
                'gifted_at' => $giftedAt,
                'gifted_to_email' => $toEmail,
                'gift_message' => $gift->message,
            ];
        }

        return [
            'gift_id' => $gift->id,
            'gift_status' => $gift->status,
            'gifted_at' => $giftedAt,
            'received_from_email' => $gift->fromUser?->email,
            'received_from_name' => $fromName,
            'gift_message' => $gift->message,
            'recibida_regalo' => true,
        ];
    }
}
