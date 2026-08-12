<?php

namespace App\Support;

use App\Models\Entity;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Http\Request;

class ActiveEntityContext
{
    public const SESSION_KEY = 'active_entity_id';

    /**
     * Gestor con varias entidades (no cuenta panel entidad fija).
     */
    public static function usesActiveEntityScope(?User $user): bool
    {
        if (! $user || $user->isSuperAdmin() || $user->isAdministration() || $user->isPanelAccount()) {
            return false;
        }

        if (! $user->isEntity()) {
            return false;
        }

        return count(self::allManagedEntityIds($user)) > 1;
    }

    /**
     * @return array<int>
     */
    public static function allManagedEntityIds(User $user): array
    {
        return $user->managers()
            ->whereNotNull('entity_id')
            ->where('status', 1)
            ->whereHas('entity', function ($query) {
                $query->where('status', 1);
            })
            ->pluck('entity_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function defaultEntityId(User $user): ?int
    {
        $manager = $user->managers()
            ->whereNotNull('entity_id')
            ->where('status', 1)
            ->whereHas('entity', fn ($q) => $q->where('status', 1))
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        return $manager ? (int) $manager->entity_id : null;
    }

    public static function bootstrapSession(Request $request, User $user): void
    {
        if (! self::usesActiveEntityScope($user)) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        $current = self::activeEntityId($user, $request);
        if ($current !== null) {
            return;
        }

        $defaultId = self::defaultEntityId($user);
        if ($defaultId !== null) {
            $request->session()->put(self::SESSION_KEY, $defaultId);
        }
    }

    public static function ensureValidSession(Request $request, User $user): void
    {
        if (! self::usesActiveEntityScope($user)) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        if (self::activeEntityId($user, $request) === null) {
            self::bootstrapSession($request, $user);
        }
    }

    public static function activeEntityId(?User $user = null, ?Request $request = null): ?int
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return null;
        }

        $request = $request ?? request();
        $raw = $request->session()->get(self::SESSION_KEY);
        if ($raw === null || $raw === '') {
            return null;
        }

        $entityId = (int) $raw;
        if (! in_array($entityId, self::allManagedEntityIds($user), true)) {
            return null;
        }

        return $entityId;
    }

    public static function activeManager(?User $user = null): ?Manager
    {
        $user = $user ?? auth()->user();
        $entityId = self::activeEntityId($user);
        if (! $user || ! $entityId) {
            return null;
        }

        return $user->managers()
            ->where('entity_id', $entityId)
            ->where('status', 1)
            ->first();
    }

    public static function activeEntity(?User $user = null): ?Entity
    {
        $entityId = self::activeEntityId($user);

        return $entityId ? Entity::query()->find($entityId) : null;
    }

    public static function userCanSelectEntity(User $user, int $entityId): bool
    {
        return in_array($entityId, self::allManagedEntityIds($user), true);
    }

    public static function setActiveEntity(Request $request, User $user, int $entityId): bool
    {
        if (! self::userCanSelectEntity($user, $entityId)) {
            return false;
        }

        $request->session()->put(self::SESSION_KEY, $entityId);
        $user->clearPanelScopeCache();

        return true;
    }

    /**
     * @return array<int, array{id: int, name: string, is_primary: bool, role_label: string}>
     */
    public static function switcherOptions(User $user): array
    {
        if (! self::usesActiveEntityScope($user)) {
            return [];
        }

        $managers = $user->managers()
            ->whereNotNull('entity_id')
            ->where('status', 1)
            ->whereHas('entity', fn ($q) => $q->where('status', 1))
            ->with('entity:id,name')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        $options = [];
        foreach ($managers as $manager) {
            $entityId = (int) $manager->entity_id;
            if (isset($options[$entityId])) {
                continue;
            }

            $options[$entityId] = [
                'id' => $entityId,
                'name' => trim((string) ($manager->entity?->name ?? 'Entidad')),
                'is_primary' => (bool) $manager->is_primary,
                'role_label' => $manager->is_primary ? 'Gestor responsable' : 'Gestor',
            ];
        }

        return array_values($options);
    }

    public static function headerContextLabel(?User $user = null): ?string
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return null;
        }

        if (self::usesActiveEntityScope($user)) {
            $entity = self::activeEntity($user);
            if (! $entity) {
                return null;
            }

            return trim($entity->name);
        }

        return null;
    }

    /**
     * IDs visibles en listados: entidad activa si aplica; si no, lógica heredada.
     *
     * @param  array<int>  $baseIds
     * @return array<int>
     */
    public static function scopeEntityIds(User $user, array $baseIds): array
    {
        if (! self::usesActiveEntityScope($user)) {
            return $baseIds;
        }

        $activeId = self::activeEntityId($user);

        return $activeId !== null ? [$activeId] : [];
    }
}
