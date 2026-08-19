<?php
/**
 * tidal-disconnect.php
 * Kasuje refresh token z var/. Client ID/Secret zostaja w config.php.
 */

require_once dirname(__DIR__) . '/lib/tidal.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Wymagane POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (dashboard_config() === null) {
    dashboard_fail_unconfigured();
}

tidal_token_clear();
dashboard_cache_write('cache_tidal.json', '');
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
