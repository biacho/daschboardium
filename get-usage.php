<?php
/**
 * get-usage.php
 * Serwuje snapshot zuzycia tokenow Claude Code przygotowany przez usage-snapshot.php
 * (cron). Sam nic nie parsuje - php-fpm i tak nie ma dostepu do ~/.claude/projects.
 * Dokłada informacje o wieku danych, zeby kiosk mogl pokazac, ze snapshot jest stary.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$snapshot = __DIR__ . '/cache_usage.json';

if (!file_exists($snapshot)) {
    echo json_encode([
        'error' => 'Brak snapshotu - uruchom usage-snapshot.php (cron).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents($snapshot), true);
if (!is_array($data)) {
    echo json_encode(['error' => 'Uszkodzony snapshot.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$age = time() - filemtime($snapshot);

$data['ageSeconds'] = $age;
// Cron chodzi co minute, wiec 10 min bez odswiezenia = cos jest nie tak
$data['stale']      = $age > 600;

echo json_encode($data, JSON_UNESCAPED_UNICODE);
