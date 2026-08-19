<?php
/**
 * tidal-stream.php
 * Spłaszcza HLS z trackManifests do playlisty, którą Safari umie odtworzyć.
 * Oficjalny Player SDK na HTTP (brak crypto.subtle) i DASH w <audio> padają.
 */

require_once dirname(__DIR__) . '/lib/tidal.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Brak id';
    exit;
}

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$hls = tidal_hls_playlist($config, $id);
if (!$hls['ok']) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo (string) $hls['error'];
    exit;
}

header('Content-Type: application/vnd.apple.mpegurl');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
echo $hls['playlist'];
