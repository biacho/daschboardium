<?php
/**
 * get-config.php
 * Zwraca edytowalna czesc config.php dla modalu ustawien.
 *
 * To NIE jest config-js.php - tu sa tez URL-e iCal (sekrety). Endpoint
 * jest schowany za allow/deny LAN w nginx; nie wystawiaj go publicznie.
 */

require_once dirname(__DIR__) . '/lib/load-config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

function hexColor(?string $color): string
{
    $c = strtolower(trim((string) $color));
    if (preg_match('/^#([0-9a-f]{3})$/', $c, $m)) {
        $h = $m[1];
        return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (preg_match('/^#[0-9a-f]{6}$/', $c)) {
        return $c;
    }
    return '#4fd1c5';
}

$calendars = [];
$rawCals = $config['ICAL_URLS']
    ?? (($config['ICAL_URL'] ?? '') !== ''
        ? ['Kalendarz' => ['url' => $config['ICAL_URL'], 'color' => null]]
        : []);

foreach ($rawCals as $name => $cal) {
    $url   = is_array($cal) ? (string) ($cal['url'] ?? '') : (string) $cal;
    $color = is_array($cal) ? ($cal['color'] ?? null) : null;
    $calendars[] = [
        'name'  => (string) $name,
        'url'   => $url,
        'color' => hexColor($color),
    ];
}

$domains = [];
foreach ($config['DOMAINS'] ?? [] as $name => $opts) {
    $opts = is_array($opts) ? $opts : [];
    $domains[] = [
        'name'     => (string) $name,
        'url'      => (string) ($opts['url'] ?? ''),
        'expect_a' => (string) ($opts['expect_a'] ?? ''),
        'expect_mx'=> (string) ($opts['expect_mx'] ?? ''),
    ];
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
if ($pomodoro === []) {
    $pomodoro = [10, 15, 20];
}

$panels = dashboard_normalize_panels($config['PANELS'] ?? []);

$tidalUser = null;
$tidalConnected = false;
require_once dirname(__DIR__) . '/lib/tidal.php';
if (tidal_connected()) {
    $tidalConnected = true;
    $store = tidal_token_store();
    if (is_array($store['user'] ?? null) && !empty($store['user']['username'])) {
        $tidalUser = (string) $store['user']['username'];
    }
}

echo json_encode([
    'weather' => [
        'city' => (string) ($config['WEATHER_CITY'] ?? 'Wrocław'),
        'lat'  => (float) ($config['WEATHER_LAT'] ?? 51.1079),
        'lon'  => (float) ($config['WEATHER_LON'] ?? 17.0385),
    ],
    'calendars' => $calendars,
    'domains'   => $domains,
    'limits' => [
        'claude'        => ($config['SHOW_CLAUDE'] ?? true) ? true : false,
        'grok'          => ($config['SHOW_GROK'] ?? true) ? true : false,
        'grokProducts'  => $grokProducts,
    ],
    'pomodoro' => $pomodoro,
    'panels'   => $panels,
    'lastfm' => [
        'user'   => (string) ($config['LASTFM_USER'] ?? ''),
        'friend' => (string) ($config['LASTFM_FRIEND'] ?? ''),
        'apiKey' => (string) ($config['LASTFM_API_KEY'] ?? ''),
    ],
    'tidal' => [
        'clientId'     => (string) ($config['TIDAL_CLIENT_ID'] ?? ''),
        'clientSecret' => (string) ($config['TIDAL_CLIENT_SECRET'] ?? ''),
        'country'      => tidal_country($config),
        'redirectUri'  => tidal_callback_uri(),
        'connected'    => $tidalConnected,
        'user'         => $tidalUser,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
