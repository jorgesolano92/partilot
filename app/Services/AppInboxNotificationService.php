<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\Lottery;
use App\Models\Manager;
use App\Models\Notification;
use App\Models\Participation;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Notificaciones persistidas para la bandeja de la app móvil + push FCM con notification_id.
 */
class AppInboxNotificationService
{
    public function __construct(
        protected FirebaseServiceModern $firebase
    ) {}

    /**
     * Resuelve un usuario emisor válido para FK sender_id (gestor de entidad o primer superadmin).
     */
    public function resolveSenderIdForEntity(int $entityId): ?int
    {
        $uid = Manager::query()->where('entity_id', $entityId)->orderBy('id')->value('user_id');
        if ($uid) {
            return (int) $uid;
        }

        return User::query()->where('role', User::ROLE_SUPER_ADMIN)->orderBy('id')->value('id');
    }

    /**
     * IDs de usuarios con participaciones en un sorteo (cartera digital y/o venta vinculada a usuario vendedor).
     *
     * @return list<int>
     */
    public function recipientUserIdsForLottery(Lottery $lottery): array
    {
        $ids = [];

        $participations = Participation::query()
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lottery->id))
            ->with(['seller:id,user_id'])
            ->get(['id', 'buyer_name', 'seller_id']);

        foreach ($participations as $p) {
            $bn = $p->buyer_name;
            if ($bn !== null && $bn !== '' && ctype_digit((string) $bn)) {
                $ids[] = (int) $bn;
            }
            if ($p->seller_id && $p->seller && (int) $p->seller->user_id > 0) {
                $ids[] = (int) $p->seller->user_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Notificación dirigida a un usuario concreto (p. ej. vendedor con cuenta SIPART).
     */
    public function notifyUser(
        int $recipientUserId,
        ?int $entityId,
        ?int $administrationId,
        int $senderId,
        string $kind,
        string $title,
        string $message,
        array $meta = [],
        bool $sendPush = true
    ): Notification {
        $notification = Notification::create([
            'recipient_user_id' => $recipientUserId,
            'entity_id' => $entityId,
            'administration_id' => $administrationId,
            'sender_id' => $senderId,
            'title' => $title,
            'message' => $message,
            'kind' => $kind,
            'meta' => $meta,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        if ($sendPush) {
            $this->sendPushForNotification($notification);
        }

        return $notification;
    }

    /**
     * Tras asignar participaciones a un vendedor con usuario vinculado.
     */
    /**
     * Invitación de vendedor PARTILOT: una notificación accionable por seller+entidad mientras esté pendiente.
     */
    public function notifySellerInvitation(Seller $seller, int $entityId, bool $sendPush = true): ?Notification
    {
        $seller->loadMissing(['entities', 'user']);
        if ($seller->seller_type !== 'partilot' || (int) $seller->status !== Seller::STATUS_PENDING) {
            return null;
        }

        $entity = Entity::query()->find($entityId);
        if (! $entity instanceof Entity) {
            return null;
        }

        $recipientUserId = $this->resolveRecipientUserIdForSeller($seller);
        if ($recipientUserId <= 0) {
            return null;
        }

        $senderId = $this->resolveSenderIdForEntity($entityId) ?? $recipientUserId;
        $assignmentState = $seller->invitationAssignmentState() ?? 'pending';
        if ($assignmentState === 'expired') {
            $this->closeSellerInvitationNotifications($seller, $entityId, 'expired');

            return null;
        }

        $roleKey = 'seller-'.$seller->id;
        $title = $entity->name;
        $message = 'Te han invitado como vendedor. Puedes aceptar o rechazar desde la app.';
        $meta = [
            'seller_id' => (int) $seller->id,
            'entity_id' => $entityId,
            'rol_context' => 'vendedor',
            'role_invitation_key' => $roleKey,
            'assignment_state' => $assignmentState,
            'actionable' => in_array($assignmentState, ['sent', 'pending'], true),
            'deep_link' => 'usuario/notificaciones?invitation='.$roleKey,
            'entidad_nombre' => $entity->name,
        ];

        $notification = Notification::query()
            ->where('recipient_user_id', $recipientUserId)
            ->where('kind', 'invitacion_vendedor')
            ->where('entity_id', $entityId)
            ->where('meta->seller_id', (int) $seller->id)
            ->orderByDesc('id')
            ->first();

        if ($notification) {
            $notification->update([
                'title' => $title,
                'message' => $message,
                'meta' => array_merge(is_array($notification->meta) ? $notification->meta : [], $meta),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } else {
            $notification = Notification::create([
                'recipient_user_id' => $recipientUserId,
                'entity_id' => $entityId,
                'administration_id' => $entity->administration_id ? (int) $entity->administration_id : null,
                'sender_id' => $senderId,
                'title' => $title,
                'message' => $message,
                'kind' => 'invitacion_vendedor',
                'meta' => $meta,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        if ($sendPush) {
            $this->sendPushForNotification($notification->fresh());
        }

        return $notification;
    }

    public function closeSellerInvitationNotifications(Seller $seller, ?int $entityId, string $assignmentState): void
    {
        $q = Notification::query()
            ->where('kind', 'invitacion_vendedor')
            ->where('meta->seller_id', (int) $seller->id);

        if ($entityId) {
            $q->where('entity_id', $entityId);
        }

        $q->get()->each(function (Notification $n) use ($assignmentState) {
            $meta = is_array($n->meta) ? $n->meta : [];
            $meta['assignment_state'] = $assignmentState;
            $meta['actionable'] = false;
            $n->update([
                'meta' => $meta,
                'read_at' => $n->read_at ?? now(),
                'status' => 'read',
            ]);
        });
    }

    protected function resolveRecipientUserIdForSeller(Seller $seller): int
    {
        if ((int) $seller->user_id > 0) {
            return (int) $seller->user_id;
        }

        $email = trim((string) ($seller->email ?? ''));
        if ($email === '') {
            return 0;
        }

        return (int) (User::query()->where('email', $email)->value('id') ?? 0);
    }

    public function notifyParticipationAssigned(Seller $seller, int $assignedCount, ?string $lotteryHint): void
    {
        if ($assignedCount <= 0 || ! $seller->user_id) {
            return;
        }

        $seller->loadMissing('entities');
        $entity = $seller->entities->first();
        if (! $entity instanceof Entity) {
            return;
        }

        $senderId = $this->resolveSenderIdForEntity((int) $entity->id);
        if (! $senderId) {
            return;
        }

        $msg = $assignedCount === 1
            ? 'Se te ha asignado 1 nueva participación.'
            : "Se te han asignado {$assignedCount} participaciones.";
        if ($lotteryHint) {
            $msg .= ' '.$lotteryHint;
        }

        $this->notifyUser(
            (int) $seller->user_id,
            (int) $entity->id,
            $entity->administration_id ? (int) $entity->administration_id : null,
            $senderId,
            'asignacion_participaciones',
            $entity->name,
            $msg,
            [
                'rol_context' => 'vendedor',
                'seller_id' => $seller->id,
                'entity_name' => $entity->name,
            ]
        );
    }

    protected function sendPushForNotification(Notification $notification): void
    {
        if (! $notification->recipient_user_id) {
            return;
        }

        $user = User::with('fcmTokens')->find($notification->recipient_user_id);
        if (! $user || $user->shouldExcludeFromOperationalPushRecipients() || $user->fcmTokens->isEmpty()) {
            return;
        }

        $body = Str::limit(strip_tags((string) $notification->message), 180);

        foreach ($user->fcmTokens as $device) {
            try {
                $meta = is_array($notification->meta) ? $notification->meta : [];
                $payload = [
                    'type' => 'inbox_notification',
                    'notification_id' => (string) $notification->id,
                    'kind' => (string) ($notification->kind ?? ''),
                    'platform' => (string) $device->platform,
                ];
                if (! empty($meta['role_invitation_key'])) {
                    $payload['role_invitation_key'] = (string) $meta['role_invitation_key'];
                }
                if (! empty($meta['deep_link'])) {
                    $payload['deep_link'] = (string) $meta['deep_link'];
                }

                $this->firebase->sendToDevice(
                    $device->token,
                    $notification->title,
                    $body,
                    $payload
                );
            } catch (\Throwable $e) {
                \Log::warning('FCM inbox notification_id='.$notification->id.': '.$e->getMessage());
            }
        }
    }
}
