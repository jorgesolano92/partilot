<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\LegalAcceptance;
use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleLegalAcceptanceService
{
    public function __construct(
        private readonly LegalAcceptanceService $legalAcceptance,
        private readonly RoleLegalNotificationService $roleNotifications,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingInvitationsForUser(User $user): array
    {
        $pending = [];

        foreach ($this->pendingManagersForUser($user) as $manager) {
            $pending[] = $this->buildManagerInvitationPayload($manager, $user);
        }

        foreach ($this->pendingSellersForUser($user) as $seller) {
            $pending[] = $this->buildSellerInvitationPayload($seller, $user);
        }

        return $pending;
    }

    public function findInvitationForUser(User $user, string $key): ?array
    {
        foreach ($this->pendingInvitationsForUser($user) as $invitation) {
            if (($invitation['key'] ?? '') === $key) {
                return $invitation;
            }
        }

        return null;
    }

    public function findManagerByToken(string $token): ?Manager
    {
        return Manager::query()
            ->where('confirmation_token', $token)
            ->whereNotNull('entity_id')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('pending_primary', true);
            })
            ->with(['entity.administration', 'user'])
            ->first();
    }

    public function findSellerByToken(string $token): ?Seller
    {
        return Seller::query()
            ->where('confirmation_token', $token)
            ->where('status', Seller::STATUS_PENDING)
            ->with(['entities', 'user'])
            ->first();
    }

    /**
     * @return array{success: bool, message: string, requires_password_setup?: bool}
     */
    public function respondManagerInvitation(
        Manager $manager,
        string $action,
        Request $request,
        ?User $actingUser = null
    ): array {
        if ($action === 'reject') {
            return $this->rejectManager($manager, $request, $actingUser);
        }

        return [
            'success' => true,
            'message' => 'Rol aceptado. Complete el proceso si se solicita contraseña.',
            'requires_password_setup' => (bool) $manager->requires_password_setup,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function finalizeManagerActivation(Manager $manager, Request $request, ?User $actingUser = null): array
    {
        $user = $actingUser ?? $manager->user;
        if (! $user) {
            return ['success' => false, 'message' => 'Usuario no encontrado.'];
        }

        DB::transaction(function () use ($manager) {
            if ($manager->pending_primary) {
                Manager::where('entity_id', $manager->entity_id)->update([
                    'is_primary' => false,
                    'pending_primary' => false,
                ]);
                $manager->is_primary = true;
            }

            $manager->update([
                'status' => 1,
                'confirmation_token' => null,
                'confirmation_sent_at' => null,
                'requires_password_setup' => false,
                'pending_primary' => false,
            ]);
        });

        $manager->refresh();

        if ($manager->is_primary && $manager->entity) {
            $manager->entity->update(['status' => 1]);

            try {
                app(EntityPanelAccessService::class)->sendPanelAccessEmail($manager->entity);
            } catch (\Throwable $e) {
                \Log::warning(
                    'Fallo enviando acceso panel entidad tras aceptación gestor responsable (entidad '
                    .$manager->entity_id.'): '.$e->getMessage()
                );
            }
        }

        $this->recordManagerAcceptance($manager, $user, $request);

        $this->roleNotifications->onManagerAccepted($manager);

        return [
            'success' => true,
            'message' => 'Invitación aceptada correctamente.',
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function respondSellerInvitation(
        Seller $seller,
        string $action,
        Request $request,
        ?User $actingUser = null
    ): array {
        if ($action === 'reject') {
            return $this->rejectSeller($seller, $request, $actingUser);
        }

        if ((int) $seller->status === Seller::STATUS_ACTIVE && ! $seller->confirmation_token) {
            return [
                'success' => true,
                'message' => 'La invitación ya estaba aceptada.',
            ];
        }

        $entityId = (int) ($seller->entities->first()?->id ?? 0);

        $user = $actingUser ?? $seller->user;
        if (! $user && filled($seller->email)) {
            $user = User::where('email', $seller->email)->first();
        }

        if ($user && ! $user->isPanelAccount()) {
            if (! $seller->isLinkedToUser() || (int) $seller->user_id !== (int) $user->id) {
                $seller->update(['user_id' => $user->id]);
            }
            if ($user->role !== User::ROLE_SELLER) {
                $user->update(['role' => User::ROLE_SELLER]);
            }
        }

        $seller->update([
            'status' => Seller::STATUS_ACTIVE,
            'confirmation_token' => null,
            'confirmation_sent_at' => null,
        ]);

        if ($user) {
            $this->recordSellerAcceptance($seller, $user, $request);
        }

        if ($entityId > 0) {
            try {
                app(AppInboxNotificationService::class)->closeSellerInvitationNotifications($seller, $entityId, 'accepted');
            } catch (\Throwable $e) {
                \Log::warning('Cerrar inbox invitación vendedor: '.$e->getMessage());
            }
        }

        $this->roleNotifications->onSellerInvitationAnswered($seller, $seller->entities->first(), accepted: true);

        return [
            'success' => true,
            'message' => 'Invitación aceptada correctamente.',
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function respondByKey(User $user, string $key, string $action, Request $request): array
    {
        if (! in_array($action, ['accept', 'reject'], true)) {
            return ['success' => false, 'message' => 'Acción no válida.'];
        }

        if (str_starts_with($key, 'manager-')) {
            $managerId = (int) substr($key, strlen('manager-'));
            $manager = $this->pendingManagersForUser($user)->firstWhere('id', $managerId);
            if (! $manager) {
                return ['success' => false, 'message' => 'Invitación no encontrada o ya procesada.'];
            }

            if ($action === 'reject') {
                return $this->rejectManager($manager, $request, $user);
            }

            $manager->loadMissing('entity');
            if (($manager->is_primary || $manager->pending_primary)
                && $manager->entity
                && $manager->entity->contract_status === Entity::CONTRACT_PENDING) {
                return [
                    'success' => false,
                    'message' => 'El representante autorizado debe firmar primero el contrato marco de la entidad antes de poder aceptar el cargo.',
                    'requires_entity_contract' => true,
                ];
            }

            $result = $this->finalizeManagerActivation($manager, $request, $user);

            return $result;
        }

        if (str_starts_with($key, 'seller-')) {
            $sellerId = (int) substr($key, strlen('seller-'));
            $seller = $this->pendingSellersForUser($user)->firstWhere('id', $sellerId);
            if (! $seller) {
                return ['success' => false, 'message' => 'Invitación no encontrada o ya procesada.'];
            }

            return $this->respondSellerInvitation($seller, $action, $request, $user);
        }

        return ['success' => false, 'message' => 'Invitación no válida.'];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Manager>
     */
    protected function pendingManagersForUser(User $user)
    {
        return Manager::query()
            ->where('user_id', $user->id)
            ->whereNotNull('confirmation_token')
            ->whereNotNull('entity_id')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('pending_primary', true);
            })
            ->with(['entity.administration'])
            ->orderByDesc('pending_primary')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Seller>
     */
    protected function pendingSellersForUser(User $user)
    {
        $email = trim((string) $user->email);

        return Seller::query()
            ->where('status', Seller::STATUS_PENDING)
            ->whereNotNull('confirmation_token')
            ->where(function ($q) use ($user, $email) {
                $q->where('user_id', $user->id);
                if ($email !== '') {
                    $q->orWhere(function ($inner) use ($email) {
                        $inner->where('user_id', 0)->where('email', $email);
                    });
                }
            })
            ->with('entities')
            ->orderBy('id')
            ->get();
    }

    protected function buildManagerInvitationPayload(Manager $manager, User $user): array
    {
        $entity = $manager->entity;
        $roleType = $manager->pending_primary || $manager->is_primary ? 'gestor_responsable' : 'gestor';
        $role = config("legal_roles.{$roleType}", []);

        return [
            'key' => 'manager-'.$manager->id,
            'type' => $roleType,
            'action' => $roleType === 'gestor_responsable'
                ? LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE
                : LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR,
            'screen_title' => $role['screen_title'] ?? 'Invitación pendiente',
            'intro_sentence' => config('legal.role_intro_sentence'),
            'accept_label' => $role['accept_label'] ?? 'Aceptar',
            'reject_label' => $role['reject_label'] ?? 'Rechazar',
            'summary_bullets' => $role['summary_bullets'] ?? [],
            'legal_document_slug' => 'terminos-y-condiciones',
            'version' => $role['version'] ?? '3',
            'text_hash' => $role['hash'] ?? 'role_v3',
            'requires_password_setup' => (bool) $manager->requires_password_setup,
            'context' => [
                'manager_id' => $manager->id,
                'entity_id' => $entity?->id,
                'entity_name' => $entity?->name,
                'administration_name' => $entity?->administration?->name,
                'invited_at' => optional($manager->confirmation_sent_at)->toIso8601String(),
                'user_name' => $user->full_name ?? $user->name,
                'user_nif' => $user->nif_cif,
            ],
        ];
    }

    protected function buildSellerInvitationPayload(Seller $seller, User $user): array
    {
        $entity = $seller->entities->first();
        $role = config('legal_roles.vendedor', []);

        return [
            'key' => 'seller-'.$seller->id,
            'type' => 'vendedor',
            'action' => LegalAcceptance::ACTION_ACEPTACION_ROL_VENDEDOR,
            'screen_title' => $role['screen_title'] ?? 'Invitación como Vendedor',
            'intro_sentence' => config('legal.role_intro_sentence'),
            'accept_label' => $role['accept_label'] ?? 'Acepto ser Vendedor',
            'reject_label' => $role['reject_label'] ?? 'Rechazar invitación',
            'summary_bullets' => $role['summary_bullets'] ?? [],
            'legal_document_slug' => 'terminos-y-condiciones',
            'version' => $role['version'] ?? '3',
            'text_hash' => $role['hash'] ?? 'role_v3',
            'requires_password_setup' => false,
            'context' => [
                'seller_id' => $seller->id,
                'entity_id' => $entity?->id,
                'entity_name' => $entity?->name,
                'invited_at' => optional($seller->confirmation_sent_at)->toIso8601String(),
                'user_name' => $user->full_name ?? $user->name,
                'user_nif' => $user->nif_cif,
            ],
        ];
    }

    protected function recordManagerAcceptance(Manager $manager, User $user, Request $request): void
    {
        $roleType = $manager->is_primary ? 'gestor_responsable' : 'gestor';
        $role = config("legal_roles.{$roleType}", []);
        $action = $roleType === 'gestor_responsable'
            ? LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE
            : LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR;

        $this->legalAcceptance->recordFromRequest(
            action: $action,
            request: $request,
            user: $user,
            version: (string) ($role['version'] ?? '3'),
            textHash: (string) ($role['hash'] ?? 'role_v3'),
            entityId: $manager->entity_id ? (int) $manager->entity_id : null,
            administrationId: $manager->entity?->administration_id ? (int) $manager->entity->administration_id : null,
            context: [
                'manager_id' => $manager->id,
                'role_type' => $roleType,
            ],
        );
    }

    protected function recordSellerAcceptance(Seller $seller, User $user, Request $request): void
    {
        $role = config('legal_roles.vendedor', []);
        $entity = $seller->entities->first();

        $this->legalAcceptance->recordFromRequest(
            action: LegalAcceptance::ACTION_ACEPTACION_ROL_VENDEDOR,
            request: $request,
            user: $user,
            version: (string) ($role['version'] ?? '3'),
            textHash: (string) ($role['hash'] ?? 'role_v3'),
            entityId: $entity?->id ? (int) $entity->id : null,
            administrationId: $entity?->administration_id ? (int) $entity->administration_id : null,
            context: [
                'seller_id' => $seller->id,
            ],
        );
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function rejectManager(Manager $manager, Request $request, ?User $actingUser): array
    {
        $manager->loadMissing(['user', 'entity']);
        $user = $actingUser ?? $manager->user;
        $isResponsible = (bool) ($manager->is_primary || $manager->pending_primary);
        $roleType = $isResponsible ? 'gestor_responsable' : 'gestor';
        $role = config("legal_roles.{$roleType}", []);
        $action = $isResponsible
            ? LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE
            : LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR;

        $managerEmail = trim((string) ($user?->email ?? $manager->contact_email ?? ''));
        $managerName = trim(implode(' ', array_filter([
            (string) ($user?->name ?? $manager->contact_name ?? ''),
            (string) ($user?->last_name ?? $manager->contact_last_name ?? ''),
            (string) ($user?->last_name2 ?? $manager->contact_last_name2 ?? ''),
        ])));

        // Guardar email/nombre en context (el user se conserva; el log sigue siendo trazable).
        $this->legalAcceptance->recordFromRequest(
            action: $action,
            request: $request,
            user: $user,
            result: LegalAcceptance::RESULT_RECHAZADO,
            version: (string) ($role['version'] ?? '3'),
            textHash: (string) ($role['hash'] ?? 'role_v3'),
            entityId: $manager->entity_id ? (int) $manager->entity_id : null,
            administrationId: $manager->entity?->administration_id ? (int) $manager->entity->administration_id : null,
            context: [
                'manager_id' => $manager->id,
                'role_type' => $roleType,
                'is_primary' => (bool) $manager->is_primary,
                'pending_primary' => (bool) $manager->pending_primary,
                'manager_email' => $managerEmail !== '' ? $managerEmail : null,
                'manager_name' => $managerName !== '' ? $managerName : null,
                'user_created_for_invitation' => (bool) $manager->user_created_for_invitation,
            ],
        );

        if ($manager->pending_primary || $manager->is_primary) {
            $this->roleNotifications->onManagerRejected($manager);
        }

        // Conservar manager + usuario (también los de solo-invitación) para poder reenviar sin reintroducir datos.
        $manager->update([
            'status' => Manager::STATUS_INVITATION_REJECTED,
            'confirmation_token' => null,
            'confirmation_sent_at' => null,
            'requires_password_setup' => false,
            // Si era “principal pendiente” (cambio de responsable), dejar de serlo; si ya era is_primary se conserva.
            'pending_primary' => false,
        ]);

        return ['success' => true, 'message' => 'Invitación rechazada.'];
    }

    protected function deleteInvitationOnlyUser(User $user, int $exceptManagerId): void
    {
        // Conservado por compatibilidad; el flujo de rechazo ya no borra usuarios temporales.
        if ($user->isPanelAccount()) {
            return;
        }

        $hasOtherManagers = Manager::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $exceptManagerId)
            ->exists();

        if ($hasOtherManagers) {
            return;
        }

        if (Seller::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $user->delete();
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function rejectSeller(Seller $seller, Request $request, ?User $actingUser): array
    {
        $user = $actingUser ?? $seller->user;
        $role = config('legal_roles.vendedor', []);
        $entity = $seller->entities->first();

        if ($user) {
            $this->legalAcceptance->recordFromRequest(
                action: LegalAcceptance::ACTION_ACEPTACION_ROL_VENDEDOR,
                request: $request,
                user: $user,
                result: LegalAcceptance::RESULT_RECHAZADO,
                version: (string) ($role['version'] ?? '3'),
                textHash: (string) ($role['hash'] ?? 'role_v3'),
                entityId: $entity?->id ? (int) $entity->id : null,
                administrationId: $entity?->administration_id ? (int) $entity->administration_id : null,
                context: ['seller_id' => $seller->id],
            );
        }

        $entityId = (int) ($entity?->id ?? 0);

        try {
            app(AppInboxNotificationService::class)->closeSellerInvitationNotifications($seller, $entityId ?: null, 'rejected');
        } catch (\Throwable $e) {
            \Log::warning('Cerrar inbox invitación vendedor (rechazo): '.$e->getMessage());
        }

        // Se conserva el vendedor y su vínculo con la entidad para que el gestor vea el rechazo y pueda reinvitar.
        $seller->update([
            'status' => Seller::STATUS_REJECTED,
            'confirmation_token' => null,
            'confirmation_sent_at' => null,
        ]);

        $this->roleNotifications->onSellerInvitationAnswered($seller, $entity, accepted: false);

        return ['success' => true, 'message' => 'Invitación rechazada.'];
    }

    public function buildWebManagerPayload(Manager $manager): array
    {
        $user = $manager->user;
        $roleType = $manager->pending_primary ? 'gestor_responsable' : 'gestor';

        return array_merge(
            $this->buildManagerInvitationPayload($manager, $user),
            ['role_type' => $roleType]
        );
    }

    public function buildWebSellerPayload(Seller $seller): array
    {
        $user = $seller->user;

        return $this->buildSellerInvitationPayload($seller, $user ?? new User);
    }
}
