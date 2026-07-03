<?php

declare(strict_types=1);

final class FileCache
{
    public function __construct(private string $dir, private int $ttlSeconds = 60)
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload) || ! isset($payload['expires'], $payload['value'])) {
            return null;
        }

        if ((int) $payload['expires'] < time()) {
            @unlink($path);

            return null;
        }

        return $payload['value'];
    }

    public function put(string $key, mixed $value): void
    {
        $payload = json_encode([
            'expires' => time() + $this->ttlSeconds,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE);

        file_put_contents($this->path($key), $payload, LOCK_EX);
    }

    private function path(string $key): string
    {
        return rtrim($this->dir, '/\\') . '/' . hash('sha256', $key) . '.json';
    }
}
