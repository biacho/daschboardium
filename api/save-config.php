<?php
/**
 * save-config.php
 * Zapisuje pogode / kalendarze / domeny z modalu z powrotem do config.php.
 * Pozostale klucze (TTL itd.) zostaja nietkniete. php-fpm musi moc nadpisac
 * istniejacy plik (664, grupa http) - nie tworzy nowego.
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

function fail(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function phpExport(mixed $value, int $indent = 0): string
{
    $pad = str_repeat('    ', $indent);
    if (is_array($value)) {
        if ($value === []) {
            return '[]';
        }
        $isList = array_is_list($value);
        $lines = ['['];
        foreach ($value as $k => $v) {
            $key = $isList ? '' : var_export((string) $k, true) . ' => ';
            $lines[] = $pad . '    ' . $key . phpExport($v, $indent + 1) . ',';
        }
        $lines[] = $pad . ']';
        return implode("\n", $lines);
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return var_export($value, true);
    }
    if ($value === null) {
        return 'null';
    }
    return var_export((string) $value, true);
}

$weather = is_array($data['weather'] ?? null) ? $data['weather'] : [];
$city = trim((string) ($weather['city'] ?? ''));
if ($city === '' || mb_strlen($city) > 80) {
    fail(400, 'Podaj nazwę miasta (max 80 znaków)');
}

$lat = filter_var($weather['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lon = filter_var($weather['lon'] ?? null, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lat < -90 || $lat > 90) {
    fail(400, 'Szerokość geograficzna musi być liczbą od -90 do 90');
}
if ($lon === false || $lon < -180 || $lon > 180) {
    fail(400, 'Długość geograficzna musi być liczbą od -180 do 180');
}

$icals = [];
foreach (is_array($data['calendars'] ?? null) ? $data['calendars'] : [] as $cal) {
    if (!is_array($cal)) {
        continue;
    }
    $name  = trim((string) ($cal['name'] ?? ''));
    $url   = trim((string) ($cal['url'] ?? ''));
    $color = strtolower(trim((string) ($cal['color'] ?? '')));
    if ($name === '' && $url === '') {
        continue;
    }
    if ($name === '' || mb_strlen($name) > 80) {
        fail(400, 'Każdy kalendarz musi mieć nazwę (max 80 znaków)');
    }
    if (isset($icals[$name])) {
        fail(400, 'Zdublowana nazwa kalendarza: ' . $name);
    }
    if (str_starts_with($url, 'webcal://')) {
        $url = 'https://' . substr($url, 9);
    }
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        fail(400, 'Kalendarz „' . $name . '” potrzebuje linku http(s)://…ics');
    }
    if (str_contains($url, 'ZAMIEN-NA-LINK')) {
        fail(400, 'Kalendarz „' . $name . '” ma niewypełniony link');
    }
    if (preg_match('/^#([0-9a-f]{3})$/', $color, $m)) {
        $h = $m[1];
        $color = '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    } elseif (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
        $color = '#4fd1c5';
    }
    $icals[$name] = ['url' => $url, 'color' => $color];
}

$hostRe = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i';
$domains = [];
foreach (is_array($data['domains'] ?? null) ? $data['domains'] : [] as $row) {
    if (!is_array($row)) {
        continue;
    }
    $name = strtolower(trim((string) ($row['name'] ?? '')));
    if ($name === '') {
        continue;
    }
    if (!preg_match($hostRe, $name)) {
        fail(400, 'Niepoprawna domena: ' . $name);
    }
    if (isset($domains[$name])) {
        fail(400, 'Zdublowana domena: ' . $name);
    }
    $entry = [];
    $url = trim((string) ($row['url'] ?? ''));
    if ($url !== '') {
        if (!preg_match('#^https?://#i', $url)) {
            fail(400, 'URL domeny ' . $name . ' musi zaczynać się od http(s)://');
        }
        $entry['url'] = $url;
    }
    $expectA = trim((string) ($row['expect_a'] ?? ''));
    if ($expectA !== '') {
        if (!filter_var($expectA, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            fail(400, 'expect_a dla ' . $name . ' musi być adresem IPv4');
        }
        $entry['expect_a'] = $expectA;
    }
    $expectMx = trim((string) ($row['expect_mx'] ?? ''));
    if ($expectMx !== '') {
        if (str_contains($expectMx, ' ') || mb_strlen($expectMx) > 120) {
            fail(400, 'expect_mx dla ' . $name . ' wygląda niepoprawnie');
        }
        $entry['expect_mx'] = $expectMx;
    }
    $domains[$name] = $entry;
}

$limits = is_array($data['limits'] ?? null) ? $data['limits'] : [];
$showClaude = !empty($limits['claude']);
$showGrok = !empty($limits['grok']);
$grokAllowed = ['GrokBuild', 'GrokChat', 'GrokImagine'];
$grokProducts = [];
foreach (is_array($limits['grokProducts'] ?? null) ? $limits['grokProducts'] : [] as $name) {
    $name = (string) $name;
    if (in_array($name, $grokAllowed, true) && !in_array($name, $grokProducts, true)) {
        $grokProducts[] = $name;
    }
}
if ($showGrok && $grokProducts === []) {
    fail(400, 'Zaznacz przynajmniej jeden produkt Groka albo wyłącz Grok');
}

$pomodoro = [];
foreach (is_array($data['pomodoro'] ?? null) ? $data['pomodoro'] : [] as $min) {
    if ($min === '' || $min === null) {
        continue;
    }
    $n = filter_var($min, FILTER_VALIDATE_INT);
    if ($n === false || $n < 1 || $n > 180) {
        fail(400, 'Pomodoro: każda długość to liczba minut od 1 do 180');
    }
    if (!in_array($n, $pomodoro, true)) {
        $pomodoro[] = $n;
    }
}
sort($pomodoro);
if (count($pomodoro) > 6) {
    fail(400, 'Pomodoro: maksymalnie 6 presetów');
}
if ($pomodoro === []) {
    fail(400, 'Dodaj przynajmniej jeden preset pomodoro');
}

$panelIds = ['internet', 'usage', 'domains', 'calendar', 'weather', 'clock', 'events', 'countdown'];
$panelsIn = is_array($data['panels'] ?? null) ? $data['panels'] : [];
$panels = [];
$anyPanel = false;
foreach ($panelIds as $id) {
    $on = array_key_exists($id, $panelsIn) ? !empty($panelsIn[$id]) : true;
    $panels[$id] = $on;
    if ($on) {
        $anyPanel = true;
    }
}
if (!$anyPanel) {
    fail(400, 'Zostaw przynajmniej jeden moduł');
}

$existing = dashboard_config();
if ($existing === null) {
    fail(503, 'Brak config.php — uruchom: php install.php');
}

$out = $existing;
$out['ICAL_URLS']     = $icals;
$out['DOMAINS']       = $domains;
$out['WEATHER_CITY']  = $city;
$out['WEATHER_LAT']   = round((float) $lat, 4);
$out['WEATHER_LON']   = round((float) $lon, 4);
$out['SHOW_CLAUDE']   = $showClaude;
$out['SHOW_GROK']     = $showGrok;
$out['GROK_PRODUCTS'] = $grokProducts;
$out['POMODORO']       = $pomodoro;
$out['PANELS']         = $panels;
$out['SETUP_COMPLETE'] = true;

$preferred = [
    'ICAL_URLS',
    'CACHE_TTL',
    'DAYS_AHEAD',
    'DOMAINS',
    'DOMAINS_CACHE_TTL',
    'DOMAINS_RDAP_TTL',
    'WEATHER_LAT',
    'WEATHER_LON',
    'WEATHER_CITY',
    'SHOW_CLAUDE',
    'SHOW_GROK',
    'GROK_PRODUCTS',
    'POMODORO',
    'PANELS',
    'SETUP_COMPLETE',
];
$ordered = [];
foreach ($preferred as $key) {
    if (array_key_exists($key, $out)) {
        $ordered[$key] = $out[$key];
    }
}
foreach ($out as $key => $value) {
    if (!array_key_exists($key, $ordered)) {
        $ordered[$key] = $value;
    }
}

$php = "<?php\n"
    . "// Wygenerowane przez panel konfiguracji. Nie serwowany (nginx deny).\n"
    . "// URL-e iCal traktuj jak sekrety.\n\n"
    . 'return ' . phpExport($ordered) . ";\n";

$path = dashboard_config_path();
if (file_put_contents($path, $php, LOCK_EX) === false) {
    fail(500, 'Nie udało się zapisać config.php (sprawdź uprawnienia)');
}
clearstatcache(true, $path);
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($path, true);
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
