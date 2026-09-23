<?php

namespace App\Support;

use App\Models\Administration;
use App\Models\Entity;
use App\Models\User;

/**
 * Unifica la comprobación de correos usados para acceso al panel y datos de contacto
 * en administraciones y entidades (evita duplicados entre tablas y cuentas de usuario).
 */
class ContactEmailRegistry
{
    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Correo ya usado como autenticación de panel (cuenta panel, admin o entidad).
     * No considera usuarios ordinarios (gestores/vendedores) ni contactos sin login.
     *
     * @param  int|null  $excludeUserId  Usuario (p. ej. cuenta panel) a excluir al editar
     * @param  int|null  $excludeAdministrationId  Fila de administración a excluir
     * @param  int|null  $excludeEntityId  Fila de entidad a excluir
     */
    public static function isPanelAuthTaken(
        ?string $email,
        ?int $excludeUserId = null,
        ?int $excludeAdministrationId = null,
        ?int $excludeEntityId = null
    ): bool {
        return self::isTaken($email, $excludeUserId, $excludeAdministrationId, $excludeEntityId, false);
    }

    /**
     * @param  int|null  $excludeUserId  Usuario (p. ej. cuenta panel) a excluir al editar
     * @param  int|null  $excludeAdministrationId  Fila de administración a excluir
     * @param  int|null  $excludeEntityId  Fila de entidad a excluir
     * @param  bool  $includeRegularUsers  Si false, solo cuentas de acceso al panel (INC-011)
     */
    public static function isTaken(
        ?string $email,
        ?int $excludeUserId = null,
        ?int $excludeAdministrationId = null,
        ?int $excludeEntityId = null,
        bool $includeRegularUsers = true
    ): bool {
        $norm = self::normalize($email);
        if ($norm === '' || ! filter_var($norm, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $qUser = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$norm]);
        if ($excludeUserId !== null) {
            $qUser->where('id', '!=', $excludeUserId);
        }
        if (! $includeRegularUsers) {
            $qUser->whereNotNull('panel_account_type')
                ->where('panel_account_type', '!=', '');
        }
        if ($qUser->exists()) {
            return true;
        }

        $qAdmin = Administration::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$norm])
            ->whereNotNull('email')
            ->where('email', '!=', '');
        if ($excludeAdministrationId !== null) {
            $qAdmin->where('id', '!=', $excludeAdministrationId);
        }
        if ($qAdmin->exists()) {
            return true;
        }

        $qEntity = Entity::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$norm])
            ->whereNotNull('email')
            ->where('email', '!=', '');
        if ($excludeEntityId !== null) {
            $qEntity->where('id', '!=', $excludeEntityId);
        }

        return $qEntity->exists();
    }
}
