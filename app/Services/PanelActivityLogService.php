<?php

namespace App\Services;

use App\Models\AdministrationAuditLog;
use App\Models\Entity;
use App\Models\LegalAcceptance;
use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Filas de actividad real para Configuración → Logs de actividad (sin datos mock).
 */
class PanelActivityLogService
{
    public function rowsForScope(
        string $logTab,
        User $viewer,
        ?int $administrationId = null,
        ?Entity $entity = null,
        ?Manager $manager = null,
        ?Seller $seller = null,
        ?User $targetUser = null,
        int $limit = 100
    ): array {
        $limit = max(1, min($limit, 200));

        return match ($logTab) {
            'partilot' => $this->rowsForPartilot($viewer, $limit),
            'administracion' => $administrationId
                ? $this->rowsForAdministration($administrationId, $limit)
                : [],
            'entidades' => $entity
                ? $this->rowsForEntity($entity, $manager, $limit)
                : [],
            'vendedores' => ($entity && $seller)
                ? $this->rowsForSeller($entity, $seller, $limit)
                : [],
            'usuarios' => $targetUser
                ? $this->rowsForAppUser($targetUser, $limit)
                : [],
            default => [],
        };
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rowsForPartilot(User $viewer, int $limit): array
    {
        if (! $viewer->isSuperAdmin()) {
            return [];
        }

        $legal = LegalAcceptance::query()
            ->with('user:id,name,email,role')
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get();

        $audit = AdministrationAuditLog::query()
            ->with('user:id,name,email,role')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->mergeSortedRows(
            $this->mapLegalRows($legal),
            $this->mapAdminAuditRows($audit),
            $limit
        );
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rowsForAdministration(int $administrationId, int $limit): array
    {
        $legal = LegalAcceptance::query()
            ->with('user:id,name,email,role')
            ->where('administration_id', $administrationId)
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get();

        $audit = AdministrationAuditLog::query()
            ->with('user:id,name,email,role')
            ->where('administration_id', $administrationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->mergeSortedRows(
            $this->mapLegalRows($legal),
            $this->mapAdminAuditRows($audit),
            $limit
        );
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rowsForEntity(Entity $entity, ?Manager $manager, int $limit): array
    {
        $q = LegalAcceptance::query()
            ->with('user:id,name,email,role')
            ->where('entity_id', $entity->id);

        if ($manager?->user_id) {
            $q->where('user_id', (int) $manager->user_id);
        }

        return $this->mapLegalRows(
            $q->orderByDesc('accepted_at')->limit($limit)->get()
        );
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rowsForSeller(Entity $entity, Seller $seller, int $limit): array
    {
        $q = LegalAcceptance::query()
            ->with('user:id,name,email,role')
            ->where(function ($inner) use ($seller, $entity) {
                $inner->where('entity_id', $entity->id)
                    ->where('context->seller_id', $seller->id);
                if ((int) $seller->user_id > 0) {
                    $inner->orWhere('user_id', (int) $seller->user_id);
                }
            });

        return $this->mapLegalRows(
            $q->orderByDesc('accepted_at')->limit($limit)->get()
        );
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rowsForAppUser(User $targetUser, int $limit): array
    {
        return $this->mapLegalRows(
            LegalAcceptance::query()
                ->with('user:id,name,email,role')
                ->where('user_id', $targetUser->id)
                ->orderByDesc('accepted_at')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * @param  Collection<int, LegalAcceptance>  $rows
     * @return list<array<string, string>>
     */
    protected function mapLegalRows(Collection $rows): array
    {
        return $rows->map(function (LegalAcceptance $la) {
            $at = $la->accepted_at;

            return [
                'sort_at' => $at?->timestamp ?? 0,
                'fecha' => $at ? $at->format('d/m/Y') : '—',
                'hora' => $at ? $at->format('H:i').'h' : '—',
                'usuario' => $la->user?->email ?? ($la->user?->name ?? '—'),
                'rol' => $this->roleLabel($la->user?->role),
                'accion' => $this->actionLabel($la->action),
                'objeto' => $this->objectLabel($la),
                'detalle' => $this->resultLabel($la->result),
                'ip' => $la->ip_address ?: '—',
                'dispositivo' => Str::limit((string) ($la->user_agent ?: '—'), 48),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, AdministrationAuditLog>  $rows
     * @return list<array<string, string>>
     */
    protected function mapAdminAuditRows(Collection $rows): array
    {
        return $rows->map(function (AdministrationAuditLog $log) {
            $at = $log->created_at;

            return [
                'sort_at' => $at?->timestamp ?? 0,
                'fecha' => $at ? $at->format('d/m/Y') : '—',
                'hora' => $at ? $at->format('H:i').'h' : '—',
                'usuario' => $log->user?->email ?? ($log->user?->name ?? '—'),
                'rol' => $this->roleLabel($log->user?->role),
                'accion' => 'Cambio configuración',
                'objeto' => (string) $log->field,
                'detalle' => trim(((string) ($log->old_value ?? '')).' → '.((string) ($log->new_value ?? ''))),
                'ip' => $log->ip ?: '—',
                'dispositivo' => Str::limit((string) ($log->user_agent ?: '—'), 48),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $a
     * @param  list<array<string, mixed>>  $b
     * @return list<array<string, string>>
     */
    protected function mergeSortedRows(array $a, array $b, int $limit): array
    {
        $merged = array_merge($a, $b);
        usort($merged, fn ($x, $y) => ($y['sort_at'] ?? 0) <=> ($x['sort_at'] ?? 0));
        $merged = array_slice($merged, 0, $limit);

        return array_map(function (array $row) {
            unset($row['sort_at']);

            return $row;
        }, $merged);
    }

    protected function roleLabel(?string $role): string
    {
        return match ($role) {
            User::ROLE_SUPER_ADMIN => 'Superadmin',
            User::ROLE_ADMINISTRATION => 'Administración',
            User::ROLE_ENTITY => 'Gestor',
            User::ROLE_SELLER => 'Vendedor',
            User::ROLE_CLIENT => 'Usuario',
            default => $role ? ucfirst($role) : '—',
        };
    }

    protected function actionLabel(string $action): string
    {
        return match ($action) {
            LegalAcceptance::ACTION_ACEPTACION_ROL_VENDEDOR => 'Invitación vendedor',
            LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR => 'Invitación gestor',
            LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE => 'Invitación gestor responsable',
            LegalAcceptance::ACTION_COBRO_PREMIO_CONFIRMADO => 'Cobro premio',
            LegalAcceptance::ACTION_DONACION_PREMIO_CONFIRMADA => 'Donación premio',
            LegalAcceptance::ACTION_REGISTRO_ACEPTACION_TCU => 'Registro / TCU',
            LegalAcceptance::ACTION_PANEL_ACEPTACION_MARCO_LEGAL => 'Marco legal panel',
            default => Str::headline(strtolower(str_replace('_', ' ', $action))),
        };
    }

    protected function resultLabel(?string $result): string
    {
        return match ($result) {
            LegalAcceptance::RESULT_ACEPTADO => 'Aceptado',
            LegalAcceptance::RESULT_RECHAZADO => 'Rechazado',
            default => $result ?: '—',
        };
    }

    protected function objectLabel(LegalAcceptance $la): string
    {
        $ctx = is_array($la->context) ? $la->context : [];
        if (! empty($ctx['seller_id'])) {
            return 'Vendedor #'.(int) $ctx['seller_id'];
        }
        if (! empty($ctx['manager_id'])) {
            return 'Gestor #'.(int) $ctx['manager_id'];
        }
        if ($la->entity_id) {
            return 'Entidad #'.(int) $la->entity_id;
        }
        if ($la->administration_id) {
            return 'Administración #'.(int) $la->administration_id;
        }

        return '—';
    }
}
