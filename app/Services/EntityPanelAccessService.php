<?php

namespace App\Services;

use App\Mail\EntityWelcomeMail;
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
        unset($entityData['panel_password']);

        $entity = Entity::create($entityData);
        $this->provisionPanelAccess($entity, $entityInformation);

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

    public function sendWelcomeEmail(Entity $entity, User $panelUser, string $plainPassword): void
    {
        try {
            app(CommunicationEmailService::class)->sendAndLog(
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
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar acceso panel entidad '.$entity->id.': '.$e->getMessage());
            throw new \RuntimeException('No se pudo enviar el correo de acceso al panel de la entidad.');
        }
    }

    public function provisionPanelAccess(Entity $entity, array $entityInformation): User
    {
        [$panelUser, $plainPassword] = $this->createPanelUser($entity, $entityInformation);
        $this->sendWelcomeEmail($entity, $panelUser, $plainPassword);

        return $panelUser;
    }
}
