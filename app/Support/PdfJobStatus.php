<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PdfJobStatus
{
    private const TTL_SECONDS = 86400;

    public static function markProcessing(string $jobId): void
    {
        Cache::put(self::key($jobId), [
            'status' => 'processing',
            'message' => null,
        ], self::TTL_SECONDS);
    }

    public static function markCompleted(string $jobId): void
    {
        Cache::put(self::key($jobId), [
            'status' => 'completed',
            'message' => null,
        ], self::TTL_SECONDS);
    }

    public static function markFailed(string $jobId, string $message): void
    {
        Cache::put(self::key($jobId), [
            'status' => 'failed',
            'message' => $message,
        ], self::TTL_SECONDS);
    }

    /**
     * @return array{status: string, message: ?string}|null
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
