<?php

namespace App\Support;

final class HtmlText
{
    /**
     * Convierte entidades HTML (&quot;, &#34;, etc.) a caracteres Unicode.
     * Aplica hasta dos pasadas por datos con doble escape legacy.
     */
    public static function decode(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decoded !== $value && str_contains($decoded, '&')) {
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $decoded;
    }

    /**
     * Texto plano seguro para almacenar (sin etiquetas HTML).
     */
    public static function sanitizePlainText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = self::decode($value);
        $withoutScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $decoded) ?? $decoded;

        return strip_tags($withoutScripts);
    }
}
