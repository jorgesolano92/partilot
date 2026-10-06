<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Referencia numérica de participación (21 dígitos) para tickets/QR.
 *
 * Estructura:
 *   [1-4]   Segmento entidad (derivado del ID completo, no truncado a 9999)
 *   [5-8]   Segmento reserva (derivado del ID completo)
 *   [9-18]  Bloque aleatorio de 10 dígitos
 *   [19-20] Extensión aleatoria (2 dígitos)
 *   [21]    Dígito de control (suma ponderada 2-1 + módulo 10, como en NIF/CIF)
 *
 * El número de participación dentro del set (campo tickets.n) no se incluye en la
 * referencia para evitar secuencias adivinables (…001, …002).
 */
class ParticipationTicketReference
{
    public const LENGTH = 21;

    public const ENTITY_LEN = 4;

    public const RESERVE_LEN = 4;

    public const RANDOM_BLOCK_LEN = 10;

    public const RANDOM_EXT_LEN = 2;

    private const MAX_GENERATION_ATTEMPTS = 50;

    /**
     * Genera una referencia válida de 21 dígitos.
     */
    public static function generate(int $entityId, int $reserveId): string
    {
        [$entityPart, $reservePart] = self::encodeScopeParts($entityId, $reserveId);

        $payload = $entityPart
            . $reservePart
            . self::randomDigits(self::RANDOM_BLOCK_LEN)
            . self::randomDigits(self::RANDOM_EXT_LEN);

        return $payload.(string) self::computeCheckDigit($payload);
    }

    /**
     * Genera una referencia única comprobando colisiones con un callback opcional.
     *
     * @param  callable(string): bool  $exists  Devuelve true si la referencia ya existe.
     */
    public static function generateUnique(int $entityId, int $reserveId, ?callable $exists = null): string
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $reference = self::generate($entityId, $reserveId);
            if ($exists === null || ! $exists($reference)) {
                return $reference;
            }
        }

        throw new RuntimeException('No se pudo generar una referencia de participación única.');
    }

    /**
     * Valida formato y dígito de control.
     */
    public static function isValid(string $reference): bool
    {
        $reference = self::normalize($reference);
        if ($reference === null || strlen($reference) !== self::LENGTH) {
            return false;
        }

        if (! ctype_digit($reference)) {
            return false;
        }

        $payload = substr($reference, 0, self::LENGTH - 1);
        $check = (int) substr($reference, self::LENGTH - 1, 1);

        return self::computeCheckDigit($payload) === $check;
    }

    /**
     * Normaliza entrada (solo dígitos). Devuelve null si queda vacío.
     */
    public static function normalize(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($reference));

        return ($digits === '' || $digits === null) ? null : $digits;
    }

    /**
     * Firma HMAC (8 hex) para URLs QR — misma idea que DesignFormat::buildTacoRef.
     */
    public static function signature(string $reference): string
    {
        $reference = self::normalize($reference);
        if ($reference === null || ! self::isValid($reference)) {
            throw new InvalidArgumentException('Referencia inválida para firmar.');
        }

        return substr(hash_hmac('sha256', $reference, (string) config('app.key')), 0, 8);
    }

    public static function verifySignature(string $reference, string $signature): bool
    {
        $reference = self::normalize($reference);
        if ($reference === null) {
            return false;
        }

        $signature = strtolower(trim($signature));
        if ($signature === '' || ! preg_match('/^[a-f0-9]{8}$/', $signature)) {
            return false;
        }

        try {
            return hash_equals(self::signature($reference), $signature);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return string|null Mensaje de error o null si la referencia (y firma opcional) es de confianza.
     */
    public static function authenticationError(?string $reference, ?string $signature = null): ?string
    {
        $reference = self::normalize($reference);
        if ($reference === null || ! self::isValid($reference)) {
            return 'La referencia no es válida (formato o dígito de control incorrecto).';
        }

        $signature = $signature !== null ? strtolower(trim($signature)) : '';
        if ($signature !== '') {
            if (! self::verifySignature($reference, $signature)) {
                return 'La firma del código QR no es válida.';
            }

            return null;
        }

        if (config('lottery.participation_qr_hmac.require_signature', false)) {
            return 'Este código QR requiere firma criptográfica.';
        }

        if (! config('lottery.participation_qr_hmac.allow_legacy_unsigned', true)) {
            return 'Código QR sin firma no permitido.';
        }

        return null;
    }

    public static function signedCheckUrl(string $reference): string
    {
        $reference = self::normalize($reference);
        if ($reference === null || ! self::isValid($reference)) {
            throw new InvalidArgumentException('Referencia inválida para URL firmada.');
        }

        // De momento sin &sig= ni host panel.*: URL más corta → QR más denso / imprimible a ~0,9 cm.
        return self::publicCheckBaseUrl()
            .'/comprobar-participaciones?ref='.$reference;
    }

    /**
     * URL base para QR impresos (partilot.es u otra configurada en .env).
     * Independiente de APP_URL / url() del panel.
     */
    public static function publicCheckBaseUrl(): string
    {
        $url = config('lottery.participation_qr_public_url', 'https://partilot.es');
        if (! is_string($url) || trim($url) === '') {
            $url = 'https://partilot.es';
        }

        $url = rtrim(trim($url), '/');
        // Evitar host panel.* en QR impresos (alarga el payload y no es la URL pública de comprobación).
        $parts = parse_url($url);
        if (is_array($parts) && ! empty($parts['host']) && str_starts_with(strtolower($parts['host']), 'panel.')) {
            $scheme = $parts['scheme'] ?? 'https';
            $host = substr($parts['host'], strlen('panel.'));
            $url = $scheme.'://'.$host;
        }

        return rtrim($url, '/');
    }

    /**
     * Dígito de control sobre 20 dígitos: pesos alternos 2 y 1 (de derecha a izquierda),
     * suma de dígitos del producto, módulo 10 (equivalente al control de documentos españoles).
     */
    public static function computeCheckDigit(string $twentyDigits): int
    {
        if (! preg_match('/^\d{20}$/', $twentyDigits)) {
            throw new InvalidArgumentException('Se requieren exactamente 20 dígitos para calcular el control.');
        }

        $sum = 0;
        $digits = str_split($twentyDigits);
        $length = count($digits);

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            $positionFromRight = $length - $i;
            if ($positionFromRight % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = intdiv($digit, 10) + ($digit % 10);
                }
            }
            $sum += $digit;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function encodeScopeParts(int $entityId, int $reserveId): array
    {
        $scope = self::encodeScopeDigits($entityId, $reserveId);

        return [
            substr($scope, 0, self::ENTITY_LEN),
            substr($scope, self::ENTITY_LEN, self::RESERVE_LEN),
        ];
    }

    /**
     * 8 dígitos deterministas a partir de ambos IDs (evita colisión por min(9999) por separado).
     */
    public static function encodeScopeDigits(int $entityId, int $reserveId): string
    {
        $hash = sprintf('%u', crc32("partilot-ref:v2:e{$entityId}:r{$reserveId}"));

        return str_pad(substr($hash, -8), 8, '0', STR_PAD_LEFT);
    }

    private static function randomDigits(int $count): string
    {
        $out = '';
        for ($i = 0; $i < $count; $i++) {
            $out .= (string) random_int(0, 9);
        }

        return $out;
    }
}
