<?php

namespace App\Services;

use App\Mail\ManagerResponsibleAcceptedUserMail;
use App\Mail\ManagerResponsibleRejectedAdminMail;
use App\Mail\RoleInvitationReminderMail;
use App\Mail\RoleManagerAcceptedUserMail;
use App\Models\Administration;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RoleLegalNotificationService
{
    public function onManagerAccepted(Manager $manager): void
    {
        $manager->loadMissing(['user', 'entity.administration']);
        $user = $manager->user;
        $entity = $manager->entity;

        if (! $user || ! $entity) {
            return;
        }

        try {
            if ($manager->is_primary) {
                Mail::to($user->email)->send(new ManagerResponsibleAcceptedUserMail($entity, $user));
            } else {
                Mail::to($user->email)->send(new RoleManagerAcceptedUserMail($entity, $user));
            }
        } catch (\Throwable $e) {
            Log::warning('G2/G4 email aceptación gestor: '.$e->getMessage());
        }

        if ($manager->is_primary) {
            $this->notifyAdministrationManagerRejectedOrAccepted($entity, $user, accepted: true);
        }
    }

    public function onManagerRejected(Manager $manager): void
    {
        $manager->loadMissing(['user', 'entity.administration']);

        $entity = $manager->entity;
        $user = $manager->user;
        if (! $entity) {
            return;
        }

        $this->notifyAdministrationManagerRejectedOrAccepted($entity, $user, accepted: false);
    }

    /**
     * Aviso a los gestores de la entidad (responsable y con permiso de vendedores) cuando un vendedor
     * PARTILOT acepta o rechaza la invitación: bandeja del panel + email.
     */
    public function onSellerInvitationAnswered(Seller $seller, ?Entity $entity, bool $accepted): void
    {
        if (! $entity) {
            return;
        }

        $sellerName = trim((string) $seller->full_name);
        if ($sellerName === '' || $sellerName === 'Sin nombre') {
            $sellerName = (string) ($seller->email ?? 'El vendedor');
        }

        $title = $accepted ? 'Vendedor ha aceptado la invitación' : 'Vendedor ha rechazado la invitación';
        $message = $accepted
            ? "{$sellerName} ha aceptado ser vendedor de {$entity->name}. Ya puedes asignarle participaciones."
            : "{$sellerName} ha rechazado la invitación como vendedor de {$entity->name}. Puedes reenviarle la invitación desde su ficha.";

        $managerUsers = Manager::query()
            ->where('entity_id', $entity->id)
            ->where('status', Manager::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->where('is_primary', true)->orWhere('permission_sellers', true);
            })
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($u) => $u instanceof User && filled($u->email))
            ->unique('id');

        $inbox = app(AppInboxNotificationService::class);
        $senderId = $inbox->resolveSenderIdForEntity((int) $entity->id);

        foreach ($managerUsers as $managerUser) {
            if ($senderId) {
                try {
                    $inbox->notifyUser(
                        (int) $managerUser->id,
                        (int) $entity->id,
                        $entity->administration_id ? (int) $entity->administration_id : null,
                        (int) $senderId,
                        $accepted ? 'vendedor_invitacion_aceptada' : 'vendedor_invitacion_rechazada',
                        $title,
                        $message,
                        ['seller_id' => (int) $seller->id, 'entity_id' => (int) $entity->id],
                        sendPush: false,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Bandeja gestor respuesta invitación vendedor: '.$e->getMessage());
                }
            }

            try {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $managerUser->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $managerUser,
                    messageType: $accepted ? 'seller_invitation_accepted' : 'seller_invitation_rejected',
                    templateKey: null,
                    mailClass: \App\Mail\SellerInvitationAnsweredToEntityManagerMail::class,
                    mailPayload: [
                        'seller_id' => (int) $seller->id,
                        'entity_id' => (int) $entity->id,
                        'manager_user_id' => (int) $managerUser->id,
                        'accepted' => $accepted,
                    ],
                    context: ['seller_id' => (int) $seller->id, 'entity_id' => (int) $entity->id],
                );
            } catch (\Throwable $e) {
                Log::warning('Email gestor respuesta invitación vendedor: '.$e->getMessage());
            }
        }
    }

    protected function notifyAdministrationManagerRejectedOrAccepted(Entity $entity, ?User $managerUser, bool $accepted): void
    {
        $entity->loadMissing('administration');
        $administration = $entity->administration;
        if (! $administration) {
            return;
        }

        $recipient = $this->administrationContactEmail($administration);
        if ($recipient === '') {
            return;
        }

        try {
            if ($accepted) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: $recipient,
                    recipientRole: 'administracion',
                    recipientUser: null,
                    messageType: 'entity_responsible_manager_confirmed',
                    templateKey: null,
                    mailClass: \App\Mail\EntityResponsibleManagerConfirmedMail::class,
                    mailPayload: [
                        'entity_id' => $entity->id,
                        'responsible_manager_user_id' => $managerUser?->id ?? 0,
                    ],
                    context: ['entity_id' => $entity->id],
                );
            } else {
                Mail::to($recipient)->send(new ManagerResponsibleRejectedAdminMail($entity, $managerUser));
            }
        } catch (\Throwable $e) {
            Log::warning('G2/G3 email administración: '.$e->getMessage());
        }
    }

    public function sendManagerInvitationReminder(Manager $manager): void
    {
        $manager->loadMissing(['user', 'entity.administration']);
        $user = $manager->user;
        if (! $user || empty($user->email)) {
            return;
        }

        $roleType = $manager->pending_primary ? 'gestor_responsable' : 'gestor';

        try {
            Mail::to($user->email)->send(new RoleInvitationReminderMail(
                entity: $manager->entity,
                invitedUser: $user,
                roleType: $roleType,
                acceptUrl: route('entity-managers.confirm-accept', ['token' => $manager->confirmation_token]),
            ));
            $manager->update(['role_invitation_reminder_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('G1b recordatorio gestor: '.$e->getMessage());
        }
    }

    public function sendSellerInvitationReminder(Seller $seller): void
    {
        $seller->loadMissing(['user', 'entities']);
        $email = $seller->user?->email ?: $seller->email;
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new RoleInvitationReminderMail(
                entity: $seller->entities->first(),
                invitedUser: $seller->user ?? new User(['name' => $seller->name, 'email' => $email]),
                roleType: 'vendedor',
                acceptUrl: route('sellers.confirm-accept', ['token' => $seller->confirmation_token]),
            ));
            $seller->update(['role_invitation_reminder_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('G1b recordatorio vendedor: '.$e->getMessage());
        }
    }

    protected function administrationContactEmail(Administration $administration): string
    {
        $panelUser = User::query()
            ->where('panel_account_type', 'administration')
            ->where('panel_account_id', $administration->id)
            ->whereNotNull('email')
            ->value('email');

        return (string) ($panelUser ?: $administration->email ?? '');
    }
}
