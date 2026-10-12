<?php

namespace App\Rules;

use App\Models\Entity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Una entidad existe una sola vez en la plataforma: su NIF/CIF no puede repetirse,
 * aunque se dé de alta desde otra administración.
 */
class UniqueEntityDocument implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreEntityId = null)
    {
    }

    public static function normalize(?string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-\.]+/', '', (string) $value));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $document = self::normalize(is_string($value) ? $value : null);
        if ($document === '') {
            return;
        }

        $exists = Entity::query()
            ->whereNotNull('nif_cif')
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(nif_cif, ' ', ''), '-', ''), '.', '')) = ?", [$document])
            ->when($this->ignoreEntityId, fn ($q) => $q->where('id', '!=', $this->ignoreEntityId))
            ->exists();

        if ($exists) {
            $fail('Ya existe una entidad con este NIF/CIF. Una entidad solo puede estar dada de alta una vez.');
        }
    }
}
