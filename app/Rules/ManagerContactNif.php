<?php

namespace App\Rules;

use App\Support\UserNifRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ManagerContactNif implements ValidationRule
{
    public function __construct(
        private ?int $administrationId = null,
        private ?int $entityId = null,
        private ?int $excludeUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (UserNifRegistry::isTakenForManagerContact(
            is_string($value) ? $value : null,
            $this->administrationId,
            $this->entityId,
            $this->excludeUserId
        )) {
            $fail('Este NIF/CIF ya está registrado en otra cuenta de usuario.');
        }
    }
}
