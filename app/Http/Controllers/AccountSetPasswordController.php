<?php

namespace App\Http\Controllers;

use App\Models\PanelAccessToken;
use App\Models\User;
use App\Support\ActiveEntityContext;
use App\Support\PanelAuthContext;
use Illuminate\Http\Request;

/**
 * Alta de contraseña por enlace de un solo uso para cuentas de usuario (gestores y vendedores),
 * en lugar de enviar una contraseña provisional en claro por correo.
 */
class AccountSetPasswordController extends Controller
{
    public function show(Request $request, string $token)
    {
        $user = $this->resolveUser($token);
        if (! $user) {
            return view('auth.account-set-password-invalid');
        }

        PanelAuthContext::forceLogoutIfNotUser($user, $request);

        return view('auth.account-set-password', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function update(Request $request, string $token)
    {
        $record = PanelAccessToken::findValidForPlain($token);
        $user = $record ? $this->eligibleUser($record->user) : null;
        if (! $record || ! $user) {
            return view('auth.account-set-password-invalid');
        }

        PanelAuthContext::forceLogoutIfNotUser($user, $request);

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'Indique una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        // El modelo User aplica cast "hashed" a password (no usar Hash::make aquí).
        $user->password = $request->input('password');
        $user->must_change_password = false;
        $user->save();

        $record->markUsed();

        $user = $user->fresh();
        if ($user->canAccessWebPanel()) {
            PanelAuthContext::switchToUser($user, $request);
            ActiveEntityContext::bootstrapSession($request, $user);

            return PanelAuthContext::redirectHome($user, 'success', 'Contraseña creada correctamente.');
        }

        return view('auth.account-set-password-done', [
            'email' => $user->email,
            'webappUrl' => config('partilot.webapp_url'),
        ]);
    }

    private function resolveUser(string $token): ?User
    {
        $record = PanelAccessToken::findValidForPlain($token);

        return $record ? $this->eligibleUser($record->user) : null;
    }

    private function eligibleUser(?User $user): ?User
    {
        if (! $user || $user->isPanelAccount() || $user->deletion_requested_at) {
            return null;
        }

        return $user;
    }
}
