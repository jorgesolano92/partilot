<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordRules
{
    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public static function registration(bool $confirmed = true): array
    {
        $rules = ['required', 'string', Password::min(8)];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
