<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\AdministrationAuditLog;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\ManagerPermissionAudit;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserActivityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    public function logAdministrationFieldChange(
        Administration $administration,
        ?User $actor,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        Request $request
    ): void {
        $old = $this->stringifyAuditValue($oldValue);
        $new = $this->stringifyAuditValue($newValue);

        if ($old === $new) {
            return;
        }

        AdministrationAuditLog::create([
            'administration_id' => $administration->id,
            'user_id' => $actor?->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
        ]);
    }

    public function logManagerPermissionChange(
        Manager $manager,
        ?User $actor,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        Request $request
    ): void {
        $old = $this->stringifyAuditValue($oldValue);
        $new = $this->stringifyAuditValue($newValue);

        if ($old === $new) {
            return;
        }

        ManagerPermissionAudit::create([
            'entity_id' => $manager->entity_id,
            'manager_id' => $manager->id,
            'user_id' => $actor?->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
        ]);
    }

    public function logLogin(User $user, string $channel, Request $request): void
    {
        try {
            [$administrationId, $entityId] = $this->resolveUserScope($user);

            UserActivityAuditLog::create([
                'user_id' => $user->id,
                'administration_id' => $administrationId,
                'entity_id' => $entityId,
                'event' => UserActivityAuditLog::EVENT_LOGIN,
                'channel' => $channel,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar el inicio de sesión: '.$e->getMessage());
        }
    }

    public function logEntityFieldChange(
        Entity $entity,
        ?User $actor,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        Request $request
    ): void {
        $old = $this->stringifyAuditValue($oldValue);
        $new = $this->stringifyAuditValue($newValue);

        if ($old === $new) {
            return;
        }

        try {
            UserActivityAuditLog::create([
                'user_id' => $actor?->id,
                'administration_id' => $entity->administration_id,
                'entity_id' => $entity->id,
                'event' => UserActivityAuditLog::EVENT_ENTITY_UPDATED,
                'channel' => UserActivityAuditLog::CHANNEL_PANEL,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar el cambio de entidad: '.$e->getMessage());
        }
    }

    /**
     * @return array{0: ?int, 1: ?int} [administration_id, entity_id]
     */
    private function resolveUserScope(User $user): array
    {
        if ($user->isPanelAccount()) {
            if ($user->panel_account_type === 'administration') {
                return [(int) $user->panel_account_id ?: null, null];
            }
            if ($user->panel_account_type === 'entity') {
                $entity = Entity::query()->find($user->panel_account_id);

                return [$entity?->administration_id, $entity?->id];
            }
        }

        $manager = $user->managers()->orderByDesc('is_primary')->first();
        if ($manager) {
            $entityId = $manager->entity_id ? (int) $manager->entity_id : null;
            $administrationId = $manager->administration_id ? (int) $manager->administration_id : null;
            if ($entityId && ! $administrationId) {
                $administrationId = Entity::query()->whereKey($entityId)->value('administration_id');
            }

            return [$administrationId, $entityId];
        }

        $seller = Seller::query()->where('user_id', $user->id)->with('entities:id,administration_id')->first();
        $entity = $seller?->entities->first();

        return [$entity?->administration_id, $entity?->id];
    }

    private function stringifyAuditValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
