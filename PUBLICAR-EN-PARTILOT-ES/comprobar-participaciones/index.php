<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$config = $GLOBALS['config'];
$ip = partilot_client_ip();
$dataDir = partilot_data_dir();

$guard = new IpGuard($dataDir, partilot_config('ip_block_steps', [60, 300, 600]));
$cache = new FileCache($dataDir . '/cache', (int) partilot_config('cache_ttl', 60));
$client = new PanelApiClient((string) partilot_config('panel_api_url'));

if ($blockedMessage = $guard->assertAllowed($ip)) {
    partilot_render_blocked($blockedMessage, $config);
    exit;
}

$ref = isset($_GET['ref']) ? trim((string) $_GET['ref']) : '';
$sig = isset($_GET['sig']) ? trim((string) $_GET['sig']) : null;

if ($ref === '') {
    header('Cache-Control: no-store');
    partilot_render_landing($config);
    exit;
}

$cacheKey = 'participation:' . $ref . ':' . ($sig ?? '');
$cachedTicket = $cache->get($cacheKey);
if (is_array($cachedTicket)) {
    partilot_render_ticket($cachedTicket, $config);
    exit;
}

$result = $client->check($ref, $sig);

if ($result['success'] && is_array($result['ticket'])) {
    $cache->put($cacheKey, $result['ticket']);
    partilot_render_ticket($result['ticket'], $config);
    exit;
}

if ($client->isCountableFailure($result['error'])) {
    $guard->recordFailure($ip);
}

header('Cache-Control: no-store');
partilot_render_error($result['error'] ?? 'No se pudo verificar la participación.', $config);
