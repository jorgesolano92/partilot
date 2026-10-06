<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCalendarDate implements ValidationRule
{
    public function __construct(
        private ?string $min = null,
        private ?string $max = null,
    ) {}

    /**
     * Fecha de nacimiento: calendario válido, no futura y edad mínima.
     *
     * @return array<int, mixed>
     */
    public static function birthday(bool $required = true, int $minAge = 18): array
    {
        return [
            ...($required ? ['required'] : ['nullable']),
            new self('1900-01-01', now()->toDateString()),
            new MinimumAge($minAge),
        ];
    }

    /**
     * Fecha genérica (p. ej. sorteos, plazos).
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $required = true, ?string $min = '1900-01-01', ?string $max = '2100-12-31'): array
    {
        return [
            ...($required ? ['required'] : ['nullable']),
            new self($min, $max),
        ];
    }

    /**
     * Fecha de hoy o posterior (remesas, cobros).
     *
     * @return array<int, mixed>
     */
    public static function onOrAfterToday(bool $required = true): array
    {
        return self::rules($required, now()->toDateString(), '2100-12-31');
    }

    /**
     * Fecha posterior a hoy (altas con fecha futura obligatoria).
     *
     * @return array<int, mixed>
     */
    public static function afterToday(bool $required = true): array
    {
        return self::rules($required, now()->addDay()->toDateString(), '2100-12-31');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $fail('Introduce una fecha válida con año de cuatro dígitos.');

            return;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if ($year < 1000 || $year > 9999) {
            $fail('El año debe tener cuatro dígitos.');

            return;
        }

        if (! checkdate($month, $day, $year)) {
            $fail('La fecha no existe en el calendario.');

            return;
        }

        if ($this->min !== null && $value < $this->min) {
            $fail('La fecha no puede ser anterior al '.$this->formatEs($this->min).'.');

            return;
        }

        if ($this->max !== null && $value > $this->max) {
            $fail('La fecha no puede ser posterior al '.$this->formatEs($this->max).'.');

            return;
        }
    }

    private function formatEs(string $iso): string
    {
        $parts = explode('-', $iso);

        return count($parts) === 3 ? ($parts[2].'/'.$parts[1].'/'.$parts[0]) : $iso;
    }
}
