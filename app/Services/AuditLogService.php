<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\AdministrationAuditLog;
use App\Models\Manager;
use App\Models\ManagerPermissionAudit;
use App\Models\User;
use Illuminate\Http\Request;

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
