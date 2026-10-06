<?php

namespace App\Services;

use App\Models\PhoneVerificationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Verificación de teléfono por OTP SMS.
 * Desarrollo: SMS_DRIVER=log (código en storage/logs/laravel.log).
 * Producción: SMS_DRIVER=httpsms (httpSMS + app Android).
 */
class PhoneVerificationService
{
    public function __construct(
        private HttpSmsClient $httpSms,
    ) {}
    public function sendVerificationCode(string $phone): void
    {
        $phone = $this->normalizePhone($phone);
        $this->assertCanSendTo($phone);

        $code = $this->generateCode();
        $message = 'Tu código de verificación Partilot es: '.$code;

        PhoneVerificationCode::query()
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->delete();

        PhoneVerificationCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('sms.code_ttl_minutes', 10)),
        ]);

        $this->dispatchSms($phone, $message);

        Cache::put($this->cooldownCacheKey($phone), true, (int) config('sms.resend_cooldown_seconds', 60));
    }

    public function verifyCode(string $phone, string $code): bool
    {
        $phone = $this->normalizePhone($phone);
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        $row = PhoneVerificationCode::query()
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $row) {
            return false;
        }

        if ($row->locked_until && $row->locked_until->isFuture()) {
            return false;
        }

        if (! Hash::check($code, $row->code_hash)) {
            $attempts = (int) $row->failed_attempts + 1;
            $maxAttempts = max(1, (int) config('sms.max_verify_attempts', 5));
            $updates = ['failed_attempts' => $attempts];

            if ($attempts >= $maxAttempts) {
                $updates['locked_until'] = now()->addMinutes((int) config('sms.verify_lockout_minutes', 15));
                Log::warning('SMS verification locked after failed attempts', [
                    'phone' => $phone,
                    'attempts' => $attempts,
                ]);
            }

            $row->update($updates);

            return false;
        }

        $row->update([
            'verified_at' => now(),
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);

        return true;
    }

    /**
     * Normaliza teléfono si el usuario lo indicó; null si dejó el campo vacío.
     */
    public function resolveOptionalPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        return $this->normalizePhone($phone);
    }

    public function smsVerificationRequired(?string $phone): bool
    {
        return (bool) config('sms.enabled') && trim((string) $phone) !== '';
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if ($phone === '') {
            throw new \InvalidArgumentException('Introduce un número de teléfono válido.');
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $country = (string) config('sms.default_country_code', '34');
        if (str_starts_with($phone, $country)) {
            return '+'.$phone;
        }

        if (strlen($phone) === 9 && in_array($phone[0], ['6', '7', '8', '9'], true)) {
            return '+'.$country.$phone;
        }

        return '+'.$phone;
    }

    private function assertCanSendTo(string $phone): void
    {
        if (Cache::has($this->cooldownCacheKey($phone))) {
            $seconds = (int) config('sms.resend_cooldown_seconds', 60);
            throw new \RuntimeException("Espera {$seconds} segundos antes de pedir otro código.");
        }

        $driver = config('sms.driver', 'log');
        if ($driver === 'log') {
            return;
        }

        if ($driver === 'httpsms') {
            if (! $this->httpSms->isConfigured()) {
                throw new \RuntimeException('SMS no configurado: revisa HTTPSMS_API_KEY y HTTPSMS_FROM_NUMBER en .env');
            }

            return;
        }

        throw new \RuntimeException("Driver SMS no soportado: {$driver}");
    }

    private function dispatchSms(string $phone, string $message): void
    {
        match (config('sms.driver', 'log')) {
            'httpsms' => $this->httpSms->send($phone, $message, 'otp-'.sha1($phone)),
            default => Log::info("[SMS verificación] {$phone}: {$message}"),
        };
    }

    private function generateCode(): string
    {
        $length = max(4, (int) config('sms.code_length', 6));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function cooldownCacheKey(string $phone): string
    {
        return 'sms_cooldown:'.sha1($phone);
    }
}
