<?php

namespace App\Support;

class PendingDigitalSaleContactMask
{
    public static function maskEmail(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = mb_strtolower($local);
        $len = mb_strlen($local);

        if ($len <= 2) {
            $maskedLocal = mb_substr($local, 0, 1).str_repeat('*', max(1, $len - 1));
        } else {
            $maskedLocal = mb_substr($local, 0, 2)
                .str_repeat('*', max(1, $len - 3))
                .mb_substr($local, -1);
        }

        return $maskedLocal.'@'.mb_strtolower($domain);
    }

    public static function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) < 4) {
            return null;
        }

        return substr($digits, 0, 2)
            .str_repeat('*', max(1, strlen($digits) - 4))
            .substr($digits, -2);
    }

    public static function forPending(?string $email, ?string $phone, ?string $channel): ?string
    {
        if (in_array($channel, ['sms', 'whatsapp'], true)) {
            return self::maskPhone($phone);
        }

        if ($channel === 'email' || ($email && ! $phone)) {
            return self::maskEmail($email);
        }

        return self::maskPhone($phone) ?? self::maskEmail($email);
    }
}
