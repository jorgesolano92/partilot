<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config.php';
if (! is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falta config.php (copia config.example.php).';
    exit;
}

$config = require $configPath;
$GLOBALS['config'] = $config;

function partilot_config(string $key, mixed $default = null): mixed
{
    $config = $GLOBALS['config'] ?? [];

    return $config[$key] ?? $default;
}

require_once __DIR__ . '/IpGuard.php';
require_once __DIR__ . '/FileCache.php';
require_once __DIR__ . '/PanelApiClient.php';
require_once __DIR__ . '/render.php';

function partilot_client_ip(): string
{
    if (partilot_config('trust_proxy', true)) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            if ($parts[0] !== '') {
                return $parts[0];
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function partilot_data_dir(): string
{
    $dir = (string) partilot_config('data_dir');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}
