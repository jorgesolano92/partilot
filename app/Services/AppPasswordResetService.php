<?php

namespace App\Services;

use App\Mail\AppPasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Recuperación de contraseña desde la app (usuarios, vendedores y gestores).
 * Usa el mismo broker que el panel; el enlace del correo abre la pantalla de la app.
 */
class AppPasswordResetService
{
    public function canRequestReset(User $user): bool
    {
        if ($user->deletion_requested_at || $user->isAdministrationContactOnly() || $user->isPanelAccount()) {
            return false;
        }

        $email = trim((string) ($user->email ?? ''));

        return $email !== '' && ! str_ends_with(strtolower($email), '@no-login.partilot.local');
    }

    /**
     * No revela si la cuenta existe: el llamante siempre responde con el mismo mensaje.
     */
    public function sendResetLink(string $email): void
    {
        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->first();

        if (! $user || ! $this->canRequestReset($user)) {
            return;
        }

        try {
            $token = Password::broker()->createToken($user);
            $resetUrl = config('partilot.webapp_url').'/restablecer-contrasena?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]);

            Mail::to($user->email)->send(new AppPasswordResetMail($user, $resetUrl));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el correo de recuperación de la app al usuario '.$user->id.': '.$e->getMessage());
        }
    }
}
