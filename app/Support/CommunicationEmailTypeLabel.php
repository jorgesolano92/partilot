<?php

namespace App\Support;

class CommunicationEmailTypeLabel
{
    private static ?array $map = null;

    public static function map(): array
    {
        if (self::$map === null) {
            $path = config_path('communication_email_types.json');
            if (! is_file($path)) {
                self::$map = [];

                return self::$map;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            self::$map = is_array($decoded) ? $decoded : [];
        }

        return self::$map;
    }

    public static function label(?string $messageType, ?string $templateKey = null): string
    {
        $key = trim((string) ($messageType ?: $templateKey));
        if ($key === '') {
            return '—';
        }

        return self::map()[$key] ?? self::fallbackLabel($key);
    }

    private static function fallbackLabel(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
