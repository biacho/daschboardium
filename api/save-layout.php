<?php
/**
 * save-layout.php
 * Zapisuje kolejnosc kafelkow w trzech kolumnach. Reszta config.php bez zmian.
 */

require_once dirname(__DIR__) . '/lib/load-config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Wymagane POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Niepoprawny JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$existing = dashboard_config();
if ($existing === null) {
    http_response_code(503);
    echo json_encode(['error' => 'Brak config.php — uruchom: php install.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

$layout = dashboard_normalize_layout($data);
$existing['LAYOUT'] = $layout;

if (!dashboard_write_config($existing)) {
    http_response_code(500);
    echo json_encode(['error' => 'Nie udało się zapisać układu'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'layout' => $layout], JSON_UNESCAPED_UNICODE);
