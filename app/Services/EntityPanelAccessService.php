<?php

namespace App\Services;

use App\Mail\EntityWelcomeMail;
use App\Models\EmailCommunicationLog;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\User;
use App\Support\ContactEmailRegistry;
use Illuminate\Support\Facades\Log;

class EntityPanelAccessService
{
    public function __construct(
        private readonly ProvisionalPasswordService $provisionalPasswords,
    ) {}

    public function createEntityWithPanelAccess($administration, array $entityInformation): Entity
    {
        $panelEmail = trim((string) ($entityInformation['email'] ?? ''));
        if ($panelEmail === '' || ! filter_var($panelEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('La entidad debe tener un email de acceso al panel válido.');
        }

        if (ContactEmailRegistry::isTaken($panelEmail)) {
            throw new \InvalidArgumentException('Este correo ya está en uso en otra administración, entidad o cuenta de usuario.');
        }

        $entityData = array_merge($entityInformation, [
            'administration_id' => is_object($administration) ? $administration->id : ($administration['id'] ?? null),
            'status' => 0,
        ]);
        unset($entityData['panel_password'], $entityData['remove_image']);

        $allowed = (new Entity)->getFillable();
        $entityData = array_intersect_key($entityData, array_flip($allowed));

        if (($entityData['client_type'] ?? null) === Entity::CLIENT_TYPE_NATURAL_ORGANIZER) {
            $entityData['nif_cif'] = null;
            $entityData['signer_is_primary_manager'] = true;
        }

        $entity = Entity::create($entityData);
        // La cuenta se crea ya, pero el correo de acceso se envía solo cuando el gestor responsable acepte.
        $this->provisionPanelAccess($entity, $entityInformation, sendWelcome: false);

        return $entity;
    }

    public function createPanelUser(Entity $entity, array $entityInformation): array
    {
        $email = trim((string) ($entityInformation['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('La entidad debe tener un email de acceso al panel válido.');
        }

        $plainPassword = $this->provisionalPasswords->generate();

        $panelUser = User::create([
            'name' => trim((string) ($entityInformation['name'] ?? '')) ?: 'Entidad',
            'email' => $email,
            'password' => $plainPassword,
            'must_change_password' => true,
            'role' => User::ROLE_ENTITY,
            'panel_account_type' => 'entity',
            'panel_account_id' => $entity->id,
            'status' => true,
            'phone' => $entityInformation['phone'] ?? null,
            'nif_cif' => $entityInformation['nif_cif'] ?? null,
        ]);

        Manager::firstOrCreate([
            'user_id' => $panelUser->id,
            'entity_id' => $entity->id,
        ], [
            'is_primary' => false,
            'permission_sellers' => true,
            'permission_design' => true,
            'permission_statistics' => true,
            'permission_payments' => true,
            'status' => 1,
        ]);

        return [$panelUser, $plainPassword];
    }

    public function findPanelUser(Entity $entity): ?User
    {
        return User::query()
            ->where('panel_account_type', 'entity')
            ->where('panel_account_id', $entity->id)
            ->first();
    }

    public function hasWelcomeBeenSent(Entity $entity): bool
    {
        return EmailCommunicationLog::query()
            ->where('message_type', 'entity_welcome')
            ->where(function ($q) use ($entity) {
                $q->where('context->entity_id', $entity->id)
                    ->orWhere('mail_payload->entity_id', $entity->id);
            })
            ->whereIn('status', [
                EmailCommunicationLog::STATUS_SENT,
                EmailCommunicationLog::STATUS_RE_SENT,
            ])
            ->exists();
    }

    /**
     * Envía (o reenvía) el acceso al panel de la entidad regenerando contraseña provisional.
     *
     * @return bool true si se envió el correo
     */
    public function sendPanelAccessEmail(Entity $entity, bool $force = false): bool
    {
        $panelUser = $this->findPanelUser($entity);
        if (! $panelUser) {
            Log::warning('No hay cuenta panel para entidad '.$entity->id.'; no se envía EntityWelcomeMail.');

            return false;
        }

        if (! $force && $this->hasWelcomeBeenSent($entity)) {
            return false;
        }

        $plainPassword = $this->provisionalPasswords->assignToUser($panelUser);
        $this->sendWelcomeEmail($entity, $panelUser, $plainPassword);

        return true;
    }

    public function sendWelcomeEmail(Entity $entity, User $panelUser, string $plainPassword): void
    {
        try {
            $log = app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: (string) $panelUser->email,
                recipientRole: 'entidad',
                recipientUser: $panelUser,
                messageType: 'entity_welcome',
                templateKey: null,
                mailClass: EntityWelcomeMail::class,
                mailPayload: [
                    'entity_id' => $entity->id,
                    'user_id' => $panelUser->id,
                    'plain_password' => $plainPassword,
                ],
                context: ['entity_id' => $entity->id],
            );

            if ($log->status !== EmailCommunicationLog::STATUS_SENT
                && $log->status !== EmailCommunicationLog::STATUS_RE_SENT) {
                throw new \RuntimeException($log->error_message ?: 'Fallo SMTP al enviar el acceso al panel.');
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar acceso panel entidad '.$entity->id.': '.$e->getMessage());
            throw new \RuntimeException('No se pudo enviar el correo de acceso al panel de la entidad: '.$e->getMessage());
        }
    }

    public function provisionPanelAccess(Entity $entity, array $entityInformation, bool $sendWelcome = false): User
    {
        [$panelUser, $plainPassword] = $this->createPanelUser($entity, $entityInformation);
        if ($sendWelcome) {
            $this->sendWelcomeEmail($entity, $panelUser, $plainPassword);
        }

        return $panelUser;
    }
}
