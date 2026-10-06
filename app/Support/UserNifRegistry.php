<?php

namespace App\Support;

use App\Models\Administration;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Comprueba unicidad de NIF/CIF en usuarios, permitiendo que el gestor de contacto
 * comparta documento con la administración o entidad titular (p. ej. autónomos) y que
 * la misma persona tenga cuentas organizativas distintas (panel, gestor de entidad, etc.).
 */
class UserNifRegistry
{
    public static function normalize(?string $nif): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', trim((string) $nif)));
    }

    /**
     * Cuenta vinculada al panel o a una administración/entidad (no usuario final de la app).
     */
    public static function isOrganizationalAccount(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isPanelAccount()) {
            return true;
        }

        if ($user->isAdministrationContactOnly()) {
            return true;
        }

        if ($user->managers()->exists()) {
            return true;
        }

        return in_array($user->role, [User::ROLE_ADMINISTRATION, User::ROLE_ENTITY], true);
    }

    public static function mayShareNifBetweenUsers(?User $a, User $b): bool
    {
        if ($a !== null && $a->id === $b->id) {
            return true;
        }

        if (self::isOrganizationalAccount($b) && ($a === null || self::isOrganizationalAccount($a))) {
            return true;
        }

        return false;
    }

    public static function isTakenForUser(?string $nif, ?int $excludeUserId = null): bool
    {
        $normalized = self::normalize($nif);
        if ($normalized === '') {
            return false;
        }

        $actor = $excludeUserId !== null ? User::query()->find($excludeUserId) : null;

        return self::isTakenAmongUsers($actor, $normalized, $excludeUserId);
    }

    public static function isTakenForManagerContact(
        ?string $nif,
        ?int $administrationId = null,
        ?int $entityId = null,
        ?int $excludeUserId = null
    ): bool {
        $normalized = self::normalize($nif);
        if ($normalized === '') {
            return false;
        }

        if ($administrationId) {
            $admin = Administration::query()->find($administrationId);
            if ($admin && self::normalize($admin->nif_cif) === $normalized) {
                return false;
            }
        }

        if ($entityId) {
            $entity = Entity::query()->find($entityId);
            if ($entity && self::normalize($entity->nif_cif) === $normalized) {
                return false;
            }
        }

        $actor = $excludeUserId !== null ? User::query()->find($excludeUserId) : null;

        return self::isTakenAmongUsers($actor, $normalized, $excludeUserId);
    }

    public static function isTakenForAdministrationManagerContact(
        ?string $nif,
        ?int $administrationId = null,
        ?int $excludeManagerId = null
    ): bool {
        $normalized = self::normalize($nif);
        if ($normalized === '') {
            return false;
        }

        if ($administrationId) {
            $admin = Administration::query()->find($administrationId);
            if ($admin && self::normalize($admin->nif_cif) === $normalized) {
                return false;
            }
        }

        $query = Manager::query()
            ->whereNotNull('administration_id')
            ->whereNull('entity_id')
            ->where('is_primary', true)
            ->whereNotNull('contact_nif_cif')
            ->where('contact_nif_cif', '!=', '')
            ->whereRaw('UPPER(REPLACE(REPLACE(contact_nif_cif, " ", ""), "-", "")) = ?', [$normalized]);

        if ($excludeManagerId !== null) {
            $query->where('id', '!=', $excludeManagerId);
        }

        return $query->exists();
    }

    public static function isTakenForManager(Manager $manager, ?string $nif, ?int $excludeUserId = null): bool
    {
        return self::isTakenForManagerContact(
            $nif,
            $manager->administration_id ? (int) $manager->administration_id : null,
            $manager->entity_id ? (int) $manager->entity_id : null,
            $excludeUserId
        );
    }

    /**
     * @return Collection<int, User>
     */
    private static function usersWithNormalizedNif(string $normalized, ?int $excludeUserId = null): Collection
    {
        $query = User::query()
            ->whereNotNull('nif_cif')
            ->where('nif_cif', '!=', '')
            ->whereRaw('UPPER(REPLACE(REPLACE(nif_cif, " ", ""), "-", "")) = ?', [$normalized]);

        if ($excludeUserId !== null) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->get();
    }

    private static function isTakenAmongUsers(?User $actor, string $normalized, ?int $excludeUserId = null): bool
    {
        foreach (self::usersWithNormalizedNif($normalized, $excludeUserId) as $other) {
            if (! self::mayShareNifBetweenUsers($actor, $other)) {
                return true;
            }
        }

        return false;
    }
}
