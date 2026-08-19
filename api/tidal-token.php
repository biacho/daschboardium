<?php
/**
 * tidal-token.php
 * Krotkotrwaly access token dla oficjalnego Player SDK.
 * Refresh / secret nie wychodza do przegladarki.
 */

require_once dirname(__DIR__) . '/lib/tidal.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$got = tidal_access($config);
if (!$got['ok']) {
    http_response_code(401);
    echo json_encode([
        'error' => $got['error'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$store = $got['store'];
$user = is_array($store['user'] ?? null) ? $store['user'] : [];

echo json_encode([
    'token'    => (string) $store['access_token'],
    'clientId' => (string) ($config['TIDAL_CLIENT_ID'] ?? ''),
    'userId'   => (string) ($user['id'] ?? ''),
    'expires'  => (int) ($store['expires_at'] ?? 0) * 1000,
    'scopes'   => explode(' ', TIDAL_SCOPES),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
