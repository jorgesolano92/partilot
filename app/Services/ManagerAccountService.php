<?php

namespace App\Services;

use App\Mail\ManagerProvisionalAccessMail;
use App\Models\User;
use App\Support\PanelPassword;

class ManagerAccountService
{
    /**
     * @param  array<string, mixed>  $context  Contexto del log de comunicaciones (p. ej. entity_id).
     */
    public function createUser(array $attributes, string $contextLabel, array $context = []): User
    {
        // Contraseña aleatoria que nadie conoce: el usuario define la suya con el enlace del correo.
        $user = User::create(array_merge($attributes, [
            'password' => PanelPassword::generate(),
        ]));

        $this->sendSetPasswordEmail($user, $contextLabel, $context);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendSetPasswordEmail(User $user, string $contextLabel, array $context = []): void
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return;
        }

        app(CommunicationEmailService::class)->sendAndLog(
            recipientEmail: $email,
            recipientRole: $user->role === User::ROLE_SELLER ? 'vendedor' : 'gestor_entidad',
            recipientUser: $user,
            messageType: 'manager_set_password',
            templateKey: null,
            mailClass: ManagerProvisionalAccessMail::class,
            mailPayload: [
                'user_id' => $user->id,
                'context_label' => $contextLabel,
            ],
            context: $context !== [] ? $context : null,
        );
    }
}
