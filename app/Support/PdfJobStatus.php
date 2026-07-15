<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PdfJobStatus
{
    private const TTL_SECONDS = 86400;

    /** Segundos sin poll para considerar al usuario ausente. */
    public const PRESENCE_TTL_SECONDS = 45;

    public static function markProcessing(string $jobId): void
    {
        $current = self::get($jobId) ?? [];
        Cache::put(self::key($jobId), array_merge($current, [
            'status' => 'processing',
            'message' => null,
        ]), self::TTL_SECONDS);
    }

    public static function markCompleted(string $jobId): void
    {
        $current = self::get($jobId) ?? [];
        Cache::put(self::key($jobId), array_merge($current, [
            'status' => 'completed',
            'message' => null,
        ]), self::TTL_SECONDS);
    }

    public static function markFailed(string $jobId, string $message): void
    {
        $current = self::get($jobId) ?? [];
        Cache::put(self::key($jobId), array_merge($current, [
            'status' => 'failed',
            'message' => $message,
        ]), self::TTL_SECONDS);
    }

    /**
     * Heartbeat del cliente mientras consulta el estado del PDF.
     */
    public static function touchPresence(string $jobId): void
    {
        $current = self::get($jobId) ?? [];
        $current['presence_at'] = time();
        Cache::put(self::key($jobId), $current, self::TTL_SECONDS);
    }

    public static function hasRecentPresence(string $jobId, ?int $seconds = null): bool
    {
        $seconds = $seconds ?? self::PRESENCE_TTL_SECONDS;
        $current = self::get($jobId);
        if (! is_array($current) || ! isset($current['presence_at'])) {
            return false;
        }

        return (time() - (int) $current['presence_at']) <= max(1, $seconds);
    }

    public static function wasEmailSent(string $jobId): bool
    {
        $current = self::get($jobId);

        return is_array($current) && ! empty($current['email_sent']);
    }

    public static function markEmailSent(string $jobId): void
    {
        $current = self::get($jobId) ?? [];
        $current['email_sent'] = true;
        Cache::put(self::key($jobId), $current, self::TTL_SECONDS);
    }

    /**
     * @return array{status?: string, message?: ?string, presence_at?: int, email_sent?: bool}|null
     */
    public static function get(string $jobId): ?array
    {
        $value = Cache::get(self::key($jobId));

        return is_array($value) ? $value : null;
    }

    private static function key(string $jobId): string
    {
        return 'pdf_job_status:'.preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId);
    }
}
