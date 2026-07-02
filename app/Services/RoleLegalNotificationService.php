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
