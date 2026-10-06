<?php

namespace App\Services;

use App\Mail\ManagerProvisionalAccessMail;
use App\Models\PanelAccessToken;
use App\Models\User;
use App\Support\PanelPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ManagerAccountService
{
    public function createUser(array $attributes, string $contextLabel): User
    {
        // Contraseña aleatoria que nadie conoce: el usuario define la suya con el enlace del correo.
        $user = User::create(array_merge($attributes, [
            'password' => PanelPassword::generate(),
        ]));

        $this->sendSetPasswordEmail($user, $contextLabel);

        return $user;
    }

    public function sendSetPasswordEmail(User $user, string $contextLabel): void
    {
        try {
            $days = max(1, (int) config('partilot.panel_magic_link_ttl_days', 7));
            $token = PanelAccessToken::issueForUser($user, now()->addDays($days));
            $url = route('account.set-password', ['token' => $token]);

            Mail::to($user->email)->send(new ManagerProvisionalAccessMail($user, $url, $contextLabel, $days));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el enlace de alta de contraseña: '.$e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }
    }
}
