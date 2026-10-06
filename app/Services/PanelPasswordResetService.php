<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class PanelPasswordResetService
{
    public function resolveByLogin(string $login): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        return User::query()
            ->where(function ($query) use ($login) {
                $query->where('email', $login)
                    ->orWhere('panel_login_username', $login);
            })
            ->first();
    }

    public function canRequestReset(User $user): bool
    {
        if ($user->deletion_requested_at || $user->isAdministrationContactOnly()) {
            return false;
        }

        if (! $user->canAccessWebPanel()) {
            return false;
        }

        $email = trim((string) ($user->email ?? ''));

        return $email !== '' && ! str_ends_with(strtolower($email), '@no-login.partilot.local');
    }

    /**
     * Envía el enlace de recuperación si la cuenta es elegible.
     * Siempre devuelve el mismo mensaje genérico para no revelar si existe la cuenta.
     */
    public function sendResetLink(string $login): void
    {
        $user = $this->resolveByLogin($login);

        if (! $user || ! $this->canRequestReset($user)) {
            return;
        }

        Password::sendResetLink(['email' => $user->email]);
    }
}
