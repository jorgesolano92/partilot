<?php

namespace App\Rules;

use App\Support\UserNifRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserNif implements ValidationRule
{
    public function __construct(
        private ?int $excludeUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (UserNifRegistry::isTakenForUser(is_string($value) ? $value : null, $this->excludeUserId)) {
            $fail('Este NIF/CIF ya está registrado en otra cuenta de usuario.');
        }
    }
}
