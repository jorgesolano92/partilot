<?php

namespace App\Support;

use App\Models\Administration;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\User;

/**
 * Comprueba unicidad de NIF/CIF en usuarios, permitiendo que el gestor de contacto
 * comparta documento con la administración o entidad titular (p. ej. autónomos).
 */
class UserNifRegistry
{
    public static function normalize(?string $nif): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', trim((string) $nif)));
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

        $query = User::query()
            ->whereNotNull('nif_cif')
            ->where('nif_cif', '!=', '')
            ->whereRaw('UPPER(REPLACE(REPLACE(nif_cif, " ", ""), "-", "")) = ?', [$normalized]);

        if ($excludeUserId !== null) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
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
}
