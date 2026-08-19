<?php
/**
 * tidal.php
 * Profil + ostatnio dodane utwory z kolekcji. Tokeny zostaja w var/.
 */

require_once dirname(__DIR__) . '/lib/tidal.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$id = trim((string) ($config['TIDAL_CLIENT_ID'] ?? ''));
$secret = trim((string) ($config['TIDAL_CLIENT_SECRET'] ?? ''));
if ($id === '' || $secret === '') {
    echo json_encode([
        'configured' => false,
        'connected'  => false,
        'error'      => 'Uzupełnij Client ID i Secret TIDAL w konfiguracji',
        'user'       => null,
        'tracks'     => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!tidal_connected()) {
    echo json_encode([
        'configured' => true,
        'connected'  => false,
        'error'      => 'Połącz konto TIDAL w konfiguracji',
        'user'       => null,
        'tracks'     => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ttl = (int) ($config['TIDAL_CACHE_TTL'] ?? 300);
if ($ttl < 30) {
    $ttl = 30;
}
if ($ttl > 3600) {
    $ttl = 3600;
}

$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query) > 80) {
    $query = mb_substr($query, 0, 80);
}

$cacheFile = dashboard_cache_path('cache_tidal.json');
$cached = dashboard_cache_read('cache_tidal.json');
if ($query === ''
    && $cached !== null
    && $cached !== ''
    && is_file($cacheFile)
    && (time() - filemtime($cacheFile) < $ttl)
    && filemtime($cacheFile) >= filemtime(dashboard_config_path())) {
    echo $cached;
    exit;
}

$snap = tidal_snapshot($config, $query);
$payload = json_encode([
    'configured' => true,
    'connected'  => $snap['connected'],
    'error'      => $snap['error'],
    'user'       => $snap['user'],
    'tracks'     => $snap['tracks'],
    'query'      => $query,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (is_string($payload)) {
    if ($snap['connected']) {
        dashboard_cache_write('cache_tidal.json', $payload);
    }
    echo $payload;
} else {
    echo json_encode(['configured' => true, 'connected' => false, 'error' => 'Błąd kodowania'], JSON_UNESCAPED_UNICODE);
}
