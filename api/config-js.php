<?php
/**
 * config-js.php
 * Wystawia PUBLICZNA czesc konfiguracji do frontendu jako `window.APP_CONFIG`.
 *
 * Frontend (scripts.js) jest statyczny i nie umie czytac config.php (PHP, serwer),
 * wiec ten plik jest mostkiem: czyta config.php po stronie serwera i wypisuje
 * tylko BEZPIECZNE klucze jako JavaScript.
 *
 * !!! NIE wypisuj tu ICAL_URLS ani niczego sekretnego - to leci wprost do przegladarki.
 */

require_once dirname(__DIR__) . '/lib/load-config.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store'); // zawsze swieze, bez problemow z cache iOS

$config = dashboard_config();
if ($config === null) {
    echo 'window.APP_CONFIG = ' . json_encode([
        'version'       => dashboard_version(),
        'setupComplete' => false,
        'weatherLat'    => 51.1079,
        'weatherLon'    => 17.0385,
        'weatherCity'   => '',
        'showClaude'    => false,
        'showGrok'      => false,
        'grokProducts'  => ['GrokBuild', 'GrokChat', 'GrokImagine'],
        'pomodoro'      => [10, 15, 20],
        'panels'        => [],
    ], JSON_UNESCAPED_UNICODE) . ';';
    exit;
}

$grokAllowed = ['GrokBuild', 'GrokChat', 'GrokImagine'];
$grokProducts = [];
foreach (is_array($config['GROK_PRODUCTS'] ?? null) ? $config['GROK_PRODUCTS'] : $grokAllowed as $name) {
    $name = (string) $name;
    if (in_array($name, $grokAllowed, true) && !in_array($name, $grokProducts, true)) {
        $grokProducts[] = $name;
    }
}
if ($grokProducts === []) {
    $grokProducts = $grokAllowed;
}

$pomodoro = [];
foreach (is_array($config['POMODORO'] ?? null) ? $config['POMODORO'] : [10, 15, 20] as $min) {
    $n = (int) $min;
    if ($n >= 1 && $n <= 180 && !in_array($n, $pomodoro, true)) {
        $pomodoro[] = $n;
    }
}
sort($pomodoro);
$pomodoro = array_slice($pomodoro, 0, 6);

$panelIds = ['internet', 'usage', 'domains', 'calendar', 'weather', 'clock', 'events', 'countdown'];
$panelsIn = is_array($config['PANELS'] ?? null) ? $config['PANELS'] : [];
$panels = [];
foreach ($panelIds as $id) {
    $panels[$id] = array_key_exists($id, $panelsIn) ? (bool) $panelsIn[$id] : true;
}

$public = [
    'weatherLat'    => $config['WEATHER_LAT']  ?? 51.1079,
    'weatherLon'    => $config['WEATHER_LON']  ?? 17.0385,
    'weatherCity'   => $config['WEATHER_CITY'] ?? 'Wrocław',
    'showClaude'    => ($config['SHOW_CLAUDE'] ?? true) ? true : false,
    'showGrok'      => ($config['SHOW_GROK'] ?? true) ? true : false,
    'grokProducts'  => $grokProducts,
    'pomodoro'      => $pomodoro,
    'panels'        => $panels,
    'setupComplete' => dashboard_setup_complete($config),
    'version'       => dashboard_version(),
];

echo 'window.APP_CONFIG = ' . json_encode($public, JSON_UNESCAPED_UNICODE) . ';';
