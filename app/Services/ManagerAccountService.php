<?php

namespace App\Services;

use App\Mail\ManagerProvisionalAccessMail;
use App\Models\User;
use App\Support\PanelPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ManagerAccountService
{
    public function createUser(array $attributes, string $contextLabel): User
    {
        $plainPassword = PanelPassword::generate();

        $user = User::create(array_merge($attributes, [
            'password' => $plainPassword,
        ]));

        $this->sendProvisionalPasswordEmail($user, $plainPassword, $contextLabel);

        return $user;
    }

    public function sendProvisionalPasswordEmail(User $user, string $plainPassword, string $contextLabel): void
    {
        try {
            Mail::to($user->email)->send(new ManagerProvisionalAccessMail($user, $plainPassword, $contextLabel));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar contraseña provisional de gestor: '.$e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }
    }
}
