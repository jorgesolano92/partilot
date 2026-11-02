<?php

namespace App\Observers;

use App\Mail\EntityManagerInvitationMail;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\PendingEntityManagerInvitation;
use App\Models\Seller;
use App\Models\User;
use App\Services\AppInboxNotificationService;
use App\Services\CommunicationEmailService;
use App\Services\PendingDigitalSaleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->attachPendingEntityManagerInvitations($user);
        $user->refresh();

        try {
            app(PendingDigitalSaleService::class)->completePendingSalesForUser($user);
        } catch (\Throwable $e) {
            Log::error('Error al completar ventas digitales pendientes para usuario '.$user->id.': '.$e->getMessage());
        }

        // Vendedores sin usuario vinculado (user_id NULL o 0 legacy) con el mismo email.
        $pendingSellers = Seller::where('email', $user->email)
            ->where(function ($q) {
                $q->whereNull('user_id')->orWhere('user_id', 0);
            })
            ->get();

        if ($pendingSellers->isNotEmpty()) {
            if (! $user->role || $user->role === User::ROLE_SUPER_ADMIN || $user->role === User::ROLE_CLIENT) {
                $user->update(['role' => User::ROLE_SELLER]);
                Log::info("Rol de seller asignado al usuario {$user->id} debido a vendedores pendientes");
            }
        }

        foreach ($pendingSellers as $seller) {
            try {
                $seller->update([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name ?? null,
                    'last_name2' => $user->last_name2 ?? null,
                    'nif_cif' => $user->nif_cif ?? null,
                    'birthday' => $user->birthday ?? null,
                    'phone' => $user->phone ?? null,
                ]);

                Log::info("Vendedor {$seller->id} vinculado automáticamente al usuario {$user->id}");

                if ((int) $seller->status === Seller::STATUS_PENDING) {
                    $seller->load('entities');
                    $entityId = (int) ($seller->entities->first()?->id ?? 0);
                    if ($entityId > 0) {
                        try {
                            app(AppInboxNotificationService::class)->notifySellerInvitation($seller, $entityId);
                        } catch (\Throwable $notifyEx) {
                            Log::warning('Inbox invitación vendedor tras registro: '.$notifyEx->getMessage());
                        }
                    }
                } elseif ((int) $seller->status === Seller::STATUS_ACTIVE
                    && (! $user->role || $user->role === User::ROLE_CLIENT)) {
                    $user->update(['role' => User::ROLE_SELLER]);
                }
            } catch (\Exception $e) {
                Log::error("Error al vincular vendedor {$seller->id} al usuario {$user->id}: ".$e->getMessage());
            }
        }
    }

    /**
     * Invitaciones a gestor de entidad antes de existir el usuario (mismo email al registrarse en web/app).
     */
    private function attachPendingEntityManagerInvitations(User $user): void
    {
        if ($user->isPanelAccount()) {
            return;
        }

        $email = PendingEntityManagerInvitation::normalizeEmail((string) $user->email);
        if ($email === '') {
            return;
        }

        $pendings = PendingEntityManagerInvitation::query()->where('email', $email)->get();
        if ($pendings->isEmpty()) {
            return;
        }

        foreach ($pendings as $pending) {
            try {
                if (Manager::query()->where('user_id', $user->id)->where('entity_id', $pending->entity_id)->exists()) {
                    $pending->delete();

                    continue;
                }

                if ($pending->is_primary) {
                    Manager::query()->where('entity_id', $pending->entity_id)->update(['is_primary' => false]);
                }

                $manager = Manager::create([
                    'user_id' => $user->id,
                    'entity_id' => $pending->entity_id,
                    'administration_id' => null,
                    'is_primary' => $pending->is_primary,
                    'permission_sellers' => $pending->permission_sellers,
                    'permission_design' => $pending->permission_design,
                    'permission_statistics' => $pending->permission_statistics,
                    'permission_payments' => $pending->permission_payments,
                    'confirmation_token' => Str::random(64),
                    'confirmation_sent_at' => now(),
                    'requires_password_setup' => false,
                    'status' => null,
                ]);

                $pending->delete();

                if ($user->role !== User::ROLE_ENTITY) {
                    $user->update(['role' => User::ROLE_ENTITY]);
                }

                $entity = Entity::query()->find($manager->entity_id);
                if ($entity && filled($user->email)) {
                    app(CommunicationEmailService::class)->sendAndLog(
                        recipientEmail: (string) $user->email,
                        recipientRole: 'gestor_entidad',
                        recipientUser: $user,
                        messageType: 'entity_manager_invitation',
                        templateKey: null,
                        mailClass: EntityManagerInvitationMail::class,
                        mailPayload: ['entity_id' => $entity->id, 'user_id' => $user->id, 'manager_id' => $manager->id],
                        context: ['entity_id' => $entity->id],
                    );
                }

                Log::info("Invitación pendiente de gestor vinculada al usuario {$user->id}, manager {$manager->id}");
            } catch (\Throwable $e) {
                Log::error('Error al vincular invitación pendiente de gestor: '.$e->getMessage());
            }
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Verificar si el status cambió
        $statusChanged = $user->wasChanged('status');

        // Sincronizar cambios del usuario a sus vendedores vinculados
        $linkedSellers = Seller::where('user_id', $user->id)->get();

        foreach ($linkedSellers as $seller) {
            try {
                $seller->update([
                    'name' => $user->name,
                    'last_name' => $user->last_name ?? null,
                    'last_name2' => $user->last_name2 ?? null,
                    'nif_cif' => $user->nif_cif ?? null,
                    'birthday' => $user->birthday ?? null,
                    'phone' => $user->phone ?? null,
                ]);

                Log::info("Datos del vendedor {$seller->id} sincronizados con usuario {$user->id}");
            } catch (\Exception $e) {
                Log::error("Error al sincronizar vendedor {$seller->id} con usuario {$user->id}: ".$e->getMessage());
            }
        }

        // Si el status cambió, sincronizar con los managers vinculados
        if ($statusChanged) {
            $linkedManagers = Manager::where('user_id', $user->id)->get();

            // Convertir el status boolean del User a integer para Manager
            // true (1) -> 1 (activo), false (0) -> 0 (inactivo)
            $managerStatus = $user->status ? 1 : 0;

            foreach ($linkedManagers as $manager) {
                try {
                    $manager->update([
                        'status' => $managerStatus,
                    ]);

                    Log::info("Status del manager {$manager->id} sincronizado con usuario {$user->id} (status: {$managerStatus})");
                } catch (\Exception $e) {
                    Log::error("Error al sincronizar status del manager {$manager->id} con usuario {$user->id}: ".$e->getMessage());
                }
            }
        }
    }
}
