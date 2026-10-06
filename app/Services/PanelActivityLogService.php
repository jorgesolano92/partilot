<?php

namespace App\Services;

use App\Models\AdministrationAuditLog;
use App\Models\Entity;
use App\Models\LegalAcceptance;
use App\Models\Manager;
use App\Models\ManagerPermissionAudit;
use App\Models\ParticipationActivityLog;
use App\Models\PaymentOperationAuditLog;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

        $payments = PaymentOperationAuditLog::query()
            ->with('user:id,name,email,role')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->mergeSortedRows(
            $limit,
            $this->mapLegalRows($legal),
            $this->mapAdminAuditRows($audit),
            $this->mapPaymentRows($payments),
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

        $payments = PaymentOperationAuditLog::query()
            ->with('user:id,name,email,role')
            ->where('administration_id', $administrationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->mergeSortedRows(
            $limit,
            $this->mapLegalRows($legal),
            $this->mapAdminAuditRows($audit),
            $this->mapPaymentRows($payments),
        );
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rowsForEntity(Entity $entity, ?Manager $manager, int $limit): array
    {
        $managerUserId = $manager?->user_id ? (int) $manager->user_id : null;

        $q = LegalAcceptance::query()
            ->with('user:id,name,email,role')
            ->where('entity_id', $entity->id);

        if ($managerUserId) {
            $q->where('user_id', $managerUserId);
        }

        $participations = $this->participationActivityQuery()
            ->where('entity_id', $entity->id)
            ->when($managerUserId, fn (Builder $query) => $query->where('user_id', $managerUserId))
            ->limit($limit)
            ->get();

        $permissions = ManagerPermissionAudit::query()
            ->with(['user:id,name,email,role', 'manager.user:id,name,email'])
            ->where('entity_id', $entity->id)
            ->when($manager, fn (Builder $query) => $query->where(function (Builder $inner) use ($manager, $managerUserId) {
                $inner->where('manager_id', $manager->id);
                if ($managerUserId) {
                    $inner->orWhere('user_id', $managerUserId);
                }
            }))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $payments = PaymentOperationAuditLog::query()
            ->with('user:id,name,email,role')
            ->where('entity_id', $entity->id)
            ->when($managerUserId, fn (Builder $query) => $query->where('user_id', $managerUserId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->mergeSortedRows(
            $limit,
            $this->mapLegalRows($q->orderByDesc('accepted_at')->limit($limit)->get()),
            $this->mapParticipationRows($participations),
            $this->mapPermissionRows($permissions),
            $this->mapPaymentRows($payments),
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

        $participations = $this->participationActivityQuery()
            ->where('entity_id', $entity->id)
            ->where(function (Builder $inner) use ($seller) {
                $inner->where('seller_id', $seller->id)
                    ->orWhere('old_seller_id', $seller->id)
                    ->orWhere('new_seller_id', $seller->id);
            })
            ->limit($limit)
            ->get();

        return $this->mergeSortedRows(
            $limit,
            $this->mapLegalRows($q->orderByDesc('accepted_at')->limit($limit)->get()),
            $this->mapParticipationRows($participations),
        );
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rowsForAppUser(User $targetUser, int $limit): array
    {
        $legal = LegalAcceptance::query()
            ->with('user:id,name,email,role')
            ->where('user_id', $targetUser->id)
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get();

        $participations = $this->participationActivityQuery()
            ->where('user_id', $targetUser->id)
            ->limit($limit)
            ->get();

        $payments = PaymentOperationAuditLog::query()
            ->with('user:id,name,email,role')
            ->where('user_id', $targetUser->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->mergeSortedRows(
            $limit,
            $this->mapLegalRows($legal),
            $this->mapParticipationRows($participations),
            $this->mapPaymentRows($payments),
        );
    }

    /**
     * Actividad de participaciones sin las altas masivas al generar sets.
     */
    protected function participationActivityQuery(): Builder
    {
        return ParticipationActivityLog::query()
            ->with(['user:id,name,email,role', 'participation:id,participation_code,participation_number'])
            ->where('activity_type', '!=', 'created')
            ->orderByDesc('created_at');
    }

    /**
     * @param  Collection<int, ParticipationActivityLog>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapParticipationRows(Collection $rows): array
    {
        return $rows->map(function (ParticipationActivityLog $log) {
            $at = $log->created_at;
            $code = $log->participation?->participation_code ?: ($log->participation_id ? '#'.$log->participation_id : '—');

            return [
                'sort_at' => $at?->timestamp ?? 0,
                'fecha' => $at ? $at->format('d/m/Y') : '—',
                'hora' => $at ? $at->format('H:i').'h' : '—',
                'usuario' => $log->user?->email ?? ($log->user?->name ?? 'Sistema'),
                'rol' => $log->user ? $this->roleLabel($log->user->role) : '—',
                'accion' => (string) $log->activity_type_text,
                'objeto' => 'Participación '.$code,
                'detalle' => Str::limit((string) ($log->description ?: trim(($log->old_status ?? '').' → '.($log->new_status ?? ''), ' →')), 120) ?: '—',
                'ip' => $log->ip_address ?: '—',
                'dispositivo' => Str::limit((string) ($log->user_agent ?: '—'), 48),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, ManagerPermissionAudit>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapPermissionRows(Collection $rows): array
    {
        return $rows->map(function (ManagerPermissionAudit $log) {
            $at = $log->created_at;
            $managerLabel = $log->manager?->user?->email ?: ($log->manager_id ? 'Gestor #'.$log->manager_id : '—');

            return [
                'sort_at' => $at?->timestamp ?? 0,
                'fecha' => $at ? $at->format('d/m/Y') : '—',
                'hora' => $at ? $at->format('H:i').'h' : '—',
                'usuario' => $log->user?->email ?? ($log->user?->name ?? '—'),
                'rol' => $this->roleLabel($log->user?->role),
                'accion' => 'Cambio permisos gestor',
                'objeto' => $managerLabel.' · '.(string) $log->field,
                'detalle' => trim(((string) ($log->old_value ?? '')).' → '.((string) ($log->new_value ?? ''))),
                'ip' => $log->ip ?: '—',
                'dispositivo' => Str::limit((string) ($log->user_agent ?: '—'), 48),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, PaymentOperationAuditLog>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapPaymentRows(Collection $rows): array
    {
        return $rows->map(function (PaymentOperationAuditLog $log) {
            $at = $log->created_at;

            return [
                'sort_at' => $at?->timestamp ?? 0,
                'fecha' => $at ? $at->format('d/m/Y') : '—',
                'hora' => $at ? $at->format('H:i').'h' : '—',
                'usuario' => $log->user?->email ?? ($log->user?->name ?? '—'),
                'rol' => $this->roleLabel($log->user?->role),
                'accion' => match ($log->operation_type) {
                    PaymentOperationAuditLog::OP_COLLECTION_REQUESTED => 'Solicitud de cobro',
                    PaymentOperationAuditLog::OP_COLLECTION_VERIFIED => 'Cobro verificado',
                    PaymentOperationAuditLog::OP_COLLECTION_CANCELLED => 'Cobro cancelado',
                    PaymentOperationAuditLog::OP_COLLECTION_FAILED => 'Cobro fallido',
                    PaymentOperationAuditLog::OP_DONATION => 'Donación',
                    default => Str::headline((string) $log->operation_type),
                },
                'objeto' => $log->reference_type
                    ? class_basename((string) $log->reference_type).' #'.(int) $log->reference_id
                    : '—',
                'detalle' => $log->amount !== null ? number_format((float) $log->amount, 2, ',', '.').' €' : '—',
                'ip' => $log->ip_address ?: '—',
                'dispositivo' => Str::limit((string) ($log->user_agent ?: '—'), 48),
            ];
        })->values()->all();
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
     * @param  list<array<string, mixed>>  ...$sources
     * @return list<array<string, string>>
     */
    protected function mergeSortedRows(int $limit, array ...$sources): array
    {
        $merged = array_merge(...$sources);
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
