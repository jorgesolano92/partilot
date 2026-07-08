<?php

namespace App\Http\Controllers;

use App\Models\Administration;
use App\Models\Entity;
use App\Models\PanelAccessToken;
use App\Models\User;
use Illuminate\Http\Request;

class PanelMagicLinkController extends Controller
{
    public function show(string $token)
    {
        $record = PanelAccessToken::findValidForPlain($token);
        if (! $record) {
            return view('auth.panel-magic-link-invalid');
        }

        $user = $record->user;
        if (! $user || ! $user->isPanelAccount() || ! in_array($user->panel_account_type, ['administration', 'entity'], true)) {
            return view('auth.panel-magic-link-invalid');
        }

        $loginHint = $user->panel_account_type === 'administration'
            ? ($user->panel_login_username ?? '')
            : ($user->email ?? '');

        return view('auth.panel-set-password', [
            'token' => $token,
            'panelUsername' => $loginHint,
            'accountType' => $user->panel_account_type,
        ]);
    }

    public function update(Request $request, string $token)
    {
        $record = PanelAccessToken::findValidForPlain($token);
        if (! $record) {
            return redirect()->route('login')->withErrors(['email' => 'El enlace no es válido o ha caducado. Solicite uno nuevo al administrador.']);
        }

        $user = $record->user;
        if (! $user || ! $user->isPanelAccount() || ! in_array($user->panel_account_type, ['administration', 'entity'], true)) {
            return redirect()->route('login')->withErrors(['email' => 'El enlace no es válido.']);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'Indique una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        // El modelo User aplica cast "hashed" a password (no usar Hash::make aquí).
        $user->password = $request->input('password');
        $user->save();

        $record->markUsed();

        if ($user->panel_account_type === 'administration') {
            $administration = Administration::query()->find($user->panel_account_id);
            if ($administration && ($administration->status === null || $administration->status === -1)) {
                $administration->update(['status' => 1]);
            }
        }

        if ($user->panel_account_type === 'entity') {
            $entity = Entity::query()->find($user->panel_account_id);
            if ($entity && ($entity->status === null || (int) $entity->status === -1)) {
                $entity->update(['status' => 1]);
            }
        }

        $loginHint = $user->panel_account_type === 'administration'
            ? 'su usuario de panel'
            : 'su email de acceso al panel';

        return redirect()->route('login')->with('success', 'Contraseña establecida. Ya puede iniciar sesión con '.$loginHint.' y la nueva contraseña.');
    }
}
