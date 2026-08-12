<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\PrintConfiguration;
use App\Models\User;
use App\Support\PanelPassword;

class PrintShopPanelUserService
{

    public function panelUser(?PrintConfiguration $config = null): ?User
    {
        $config ??= PrintConfiguration::first();
        if (! $config) {
            return null;
        }

        return User::query()
            ->where('panel_account_type', User::PANEL_ACCOUNT_PRINT_SHOP)
            ->where('panel_account_id', $config->id)
            ->first();
    }

    /**
     * Crea o actualiza la cuenta panel de la imprenta (solo debe existir una).
     *
     * @return array{user: User, plain_password: ?string}
     */
    public function upsertPanelUser(PrintConfiguration $config, array $input): array
    {
        $email = trim((string) ($input['panel_email'] ?? $config->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Indica un email válido para el acceso de imprenta.');
        }

        $displayName = trim((string) ($config->company_name ?? ''));
        if ($displayName === '') {
            $displayName = 'Imprenta';
        }

        $existing = $this->panelUser($config);
        $username = Administration::ensureUniquePanelLoginUsername(
            $existing?->panel_login_username ?: ('imprenta-'.$config->id),
            $existing?->id
        );

        $plainPassword = null;

        if ($existing) {
            $existing->fill([
                'name' => $displayName,
                'email' => $email,
                'role' => User::ROLE_PRINT_SHOP,
                'panel_account_type' => User::PANEL_ACCOUNT_PRINT_SHOP,
                'panel_account_id' => $config->id,
                'panel_login_username' => $username,
                'status' => true,
            ]);
            if (! empty($input['panel_password'])) {
                $plainPassword = (string) $input['panel_password'];
                $existing->password = $plainPassword;
            }
            $existing->save();

            return [
                'user' => $existing->fresh(),
                'plain_password' => $plainPassword,
            ];
        }

        $plainPassword = ! empty($input['panel_password'])
            ? (string) $input['panel_password']
            : PanelPassword::generate();

        $user = User::create([
            'name' => $displayName,
            'email' => $email,
            'password' => $plainPassword,
            'role' => User::ROLE_PRINT_SHOP,
            'panel_account_type' => User::PANEL_ACCOUNT_PRINT_SHOP,
            'panel_account_id' => $config->id,
            'panel_login_username' => $username,
            'status' => true,
        ]);

        return [
            'user' => $user,
            'plain_password' => $plainPassword,
        ];
    }
}
