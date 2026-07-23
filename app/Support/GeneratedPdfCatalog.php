<?php

namespace App\Support;

class GeneratedPdfCatalog
{
    /** Días que el PDF permanece descargable (enlace del correo). */
    public const TTL_DAYS = 7;

    public static function metaPath(string $jobId): string
    {
        return storage_path('app/generated_pdfs/'.$jobId.'.meta.json');
    }

    public static function writeMeta(string $jobId, string $downloadName, ?int $designFormatId = null): void
    {
        $dir = storage_path('app/generated_pdfs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $now = time();
        $payload = [
            'download_name' => $downloadName,
            'design_format_id' => $designFormatId,
            'created_at' => $now,
            'expires_at' => $now + (self::TTL_DAYS * 86400),
        ];
        file_put_contents(static::metaPath($jobId), json_encode(
            array_filter($payload, static fn ($v) => $v !== null),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        ));
    }

    /**
     * @return array{download_name: string, design_format_id?: int, created_at?: int, expires_at?: int}|null
     */
    public static function readMeta(string $jobId): ?array
    {
        $path = static::metaPath($jobId);
        if (! is_file($path)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true);
        } catch (\Throwable $e) {
            return null;
        }
        if (! is_array($data) || empty($data['download_name'])) {
            return null;
        }
        $out = [
            'download_name' => (string) $data['download_name'],
        ];
        if (isset($data['design_format_id']) && is_numeric($data['design_format_id'])) {
            $out['design_format_id'] = (int) $data['design_format_id'];
        }
        if (isset($data['created_at']) && is_numeric($data['created_at'])) {
            $out['created_at'] = (int) $data['created_at'];
        }
        if (isset($data['expires_at']) && is_numeric($data['expires_at'])) {
            $out['expires_at'] = (int) $data['expires_at'];
        }

        return $out;
    }

    public static function artifactPath(string $jobId, string $downloadName = ''): string
    {
        $ext = str_ends_with(strtolower($downloadName), '.zip') ? 'zip' : 'pdf';
        $zip = storage_path('app/generated_pdfs/'.$jobId.'.zip');
        $pdf = storage_path('app/generated_pdfs/'.$jobId.'.pdf');
        if ($ext === 'zip' && is_file($zip)) {
            return $zip;
        }
        if ($ext === 'pdf' && is_file($pdf)) {
            return $pdf;
        }
        if (is_file($zip)) {
            return $zip;
        }

        return $pdf;
    }

    /**
     * true si el meta indica caducidad (o, sin expires_at, si el PDF/meta supera TTL_DAYS).
     */
    public static function isExpired(string $jobId, ?array $meta = null): bool
    {
        $meta = $meta ?? static::readMeta($jobId);
        if (is_array($meta) && isset($meta['expires_at'])) {
            return time() > (int) $meta['expires_at'];
        }

        $artifact = static::artifactPath($jobId, (string) ($meta['download_name'] ?? ''));
        $ref = is_file($artifact) ? filemtime($artifact) : null;
        if ($ref === false || $ref === null) {
            $metaPath = static::metaPath($jobId);
            $ref = is_file($metaPath) ? filemtime($metaPath) : null;
        }
        if ($ref === false || $ref === null) {
            return true;
        }

        return (time() - (int) $ref) > (self::TTL_DAYS * 86400);
    }

    public static function readDownloadName(string $jobId): ?string
    {
        $meta = static::readMeta($jobId);

        return $meta['download_name'] ?? null;
    }

    /** @deprecated Usar readMeta */
    public static function readDesignFormatId(string $jobId): ?int
    {
        $meta = static::readMeta($jobId);

        return $meta['design_format_id'] ?? null;
    }

    public static function deleteMeta(string $jobId): void
    {
        $p = static::metaPath($jobId);
        if (is_file($p)) {
            @unlink($p);
        }
    }

    /** Borra PDF/ZIP + meta de un job. */
    public static function deleteArtifacts(string $jobId): void
    {
        foreach (['.pdf', '.zip'] as $ext) {
            $path = storage_path('app/generated_pdfs/'.$jobId.$ext);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        static::deleteMeta($jobId);
    }
}
