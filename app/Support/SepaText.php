<?php

namespace App\Support;

/**
 * Texto libre para ficheros SEPA: juego de caracteres latino básico de EPC
 * (a-z A-Z 0-9 / - ? : ( ) . , ' + espacio).
 */
final class SepaText
{
    private const MAP = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'ñ' => 'n', 'Ñ' => 'N', 'ç' => 'c', 'Ç' => 'C',
        'ª' => 'a', 'º' => 'o', '&' => 'Y', '€' => 'EUR',
    ];

    public static function clean(?string $value, int $maxLength): string
    {
        $text = strtr((string) $value, self::MAP);
        $text = preg_replace("/[^A-Za-z0-9\\/\\-?:().,'+ ]/", ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return substr($text, 0, $maxLength);
    }
}
