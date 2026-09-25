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

        // INC-011: solo bloquear si ya es acceso de panel / otra entidad o administración.
        if (ContactEmailRegistry::isPanelAuthTaken($panelEmail)) {
            throw new \InvalidArgumentException('Este correo ya está en uso como acceso al panel de otra administración o entidad.');
        }

        $entityData = array_merge($entityInformation, [
            'administration_id' => is_object($administration) ? $administration->id : ($administration['id'] ?? null),
            'status' => null,
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


    /**
     * Actualiza una entidad ya creada en el asistente (mismo id de sesión).
     */
    public function updateWizardEntity(Entity $entity, $administration, array $entityInformation): Entity
    {
        $panelEmail = trim((string) ($entityInformation['email'] ?? ''));
        if ($panelEmail === '' || ! filter_var($panelEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('La entidad debe tener un email de acceso al panel válido.');
        }

        $panelUser = $this->findPanelUser($entity);
        if (ContactEmailRegistry::isPanelAuthTaken(
            $panelEmail,
            $panelUser?->id,
            null,
            $entity->id
        )) {
            throw new \InvalidArgumentException('Este correo ya está en uso como acceso al panel de otra administración o entidad.');
        }

        $entityData = array_merge($entityInformation, [
            'administration_id' => is_object($administration) ? $administration->id : ($administration['id'] ?? null),
        ]);
        unset($entityData['panel_password'], $entityData['remove_image'], $entityData['id'], $entityData['status']);

        $allowed = (new Entity)->getFillable();
        $entityData = array_intersect_key($entityData, array_flip($allowed));

        if (($entityData['client_type'] ?? null) === Entity::CLIENT_TYPE_NATURAL_ORGANIZER) {
            $entityData['nif_cif'] = null;
            $entityData['signer_is_primary_manager'] = true;
        }

        $entity->update($entityData);
        $entity = $entity->fresh();

        if (! $this->findPanelUser($entity)) {
            $this->provisionPanelAccess($entity, $entityInformation, sendWelcome: false);
        } elseif ($panelUser && strcasecmp((string) $panelUser->email, $panelEmail) !== 0) {
            $panelUser->update([
                'email' => $panelEmail,
                'name' => trim((string) ($entityInformation['name'] ?? '')) ?: $panelUser->name,
                'phone' => $entityInformation['phone'] ?? $panelUser->phone,
                'nif_cif' => $entityInformation['nif_cif'] ?? $panelUser->nif_cif,
            ]);
        }

        return $entity->fresh();
    }

    public function createPanelUser(Entity $entity, array $entityInformation): array
    {
        $email = trim((string) ($entityInformation['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('La entidad debe tener un email de acceso al panel válido.');
        }

        $existing = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [ContactEmailRegistry::normalize($email)])
            ->first();

        if ($existing) {
            if ($existing->isPanelAccount()) {
                if ($existing->panel_account_type === 'entity'
                    && (int) $existing->panel_account_id === (int) $entity->id) {
                    return [$existing, null];
                }

                throw new \InvalidArgumentException('Este correo ya está en uso como cuenta de acceso al panel.');
            }

            // Email de usuario ordinario: no convertir automáticamente (INC-011).
            Log::info('Panel entidad '.$entity->id.': email ya pertenece al usuario '.$existing->id.'; se aplaza la cuenta panel dedicada.');

            return [null, null];
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

    /**
     * Revoca la cuenta de acceso al panel de la entidad (R2-INC-002).
     * Debe llamarse antes de borrar la fila de entidad.
     */
    public function revokePanelAccess(Entity $entity): void
    {
        $panelUser = $this->findPanelUser($entity);
        if (! $panelUser) {
            return;
        }

        Manager::query()
            ->where('user_id', $panelUser->id)
            ->where('entity_id', $entity->id)
            ->delete();

        $stillLinkedElsewhere = Manager::query()
            ->where('user_id', $panelUser->id)
            ->exists();

        $dedicatedToThisEntity = $panelUser->panel_account_type === 'entity'
            && (int) $panelUser->panel_account_id === (int) $entity->id;

        if ($dedicatedToThisEntity && ! $stillLinkedElsewhere) {
            $panelUser->delete();

            return;
        }

        $panelUser->forceFill([
            'panel_account_type' => null,
            'panel_account_id' => null,
        ])->save();
    }


    /**
     * Asegura cuenta panel: crea una nueva o vincula al gestor principal aceptado si comparte email.
     */
    public function ensurePanelAccess(Entity $entity): ?User
    {
        $panelUser = $this->findPanelUser($entity);
        if ($panelUser) {
            return $panelUser;
        }

        $email = ContactEmailRegistry::normalize((string) $entity->email);
        if ($email === '') {
            return null;
        }

        $existing = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if ($existing && ! $existing->isPanelAccount()) {
            $isPrimaryManager = Manager::query()
                ->where('entity_id', $entity->id)
                ->where('user_id', $existing->id)
                ->where('is_primary', true)
                ->where('status', 1)
                ->exists();

            if ($isPrimaryManager) {
                $existing->update([
                    'name' => trim((string) $entity->name) ?: $existing->name,
                    'role' => User::ROLE_ENTITY,
                    'panel_account_type' => 'entity',
                    'panel_account_id' => $entity->id,
                    'phone' => $entity->phone ?? $existing->phone,
                    'nif_cif' => $entity->nif_cif ?? $existing->nif_cif,
                ]);

                return $existing->fresh();
            }

            return null;
        }

        if ($existing && $existing->isPanelAccount()) {
            return null;
        }

        [$panelUser] = $this->createPanelUser($entity, [
            'email' => $entity->email,
            'name' => $entity->name,
            'phone' => $entity->phone,
            'nif_cif' => $entity->nif_cif,
        ]);

        return $panelUser;
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
        $panelUser = $this->ensurePanelAccess($entity);
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

    public function provisionPanelAccess(Entity $entity, array $entityInformation, bool $sendWelcome = false): ?User
    {
        [$panelUser, $plainPassword] = $this->createPanelUser($entity, $entityInformation);
        if ($panelUser && $sendWelcome && $plainPassword) {
            $this->sendWelcomeEmail($entity, $panelUser, $plainPassword);
        }

        return $panelUser;
    }
}
