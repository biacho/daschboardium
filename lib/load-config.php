<?php

function dashboard_root(): string
{
    return dirname(__DIR__);
}

function dashboard_config_path(): string
{
    return dashboard_root() . '/config/config.php';
}

function dashboard_config_example_path(): string
{
    return dashboard_root() . '/config/config.example.php';
}

function dashboard_cache_path(string $name): string
{
    $name = preg_replace('/\.json$/', '.php', $name) ?? $name;
    return dashboard_root() . '/var/' . $name;
}

function dashboard_cache_read(string $name): ?string
{
    $path = dashboard_cache_path($name);
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    if (str_starts_with($raw, '<?php')) {
        $pos = strpos($raw, '?>');
        return $pos === false ? null : substr($raw, $pos + 2);
    }
    return $raw;
}

function dashboard_cache_write(string $name, string $json): bool
{
    $path = dashboard_cache_path($name);
    $body = "<?php\nhttp_response_code(404);\nexit;\n?>" . $json;
    return file_put_contents($path, $body, LOCK_EX) !== false;
}

function dashboard_vendor_autoload(): string
{
    return dashboard_root() . '/vendor/autoload.php';
}

function dashboard_version(): string
{
    $path = dashboard_root() . '/VERSION';
    if (!is_file($path)) {
        return '0';
    }
    $v = trim((string) file_get_contents($path));
    return $v !== '' ? $v : '0';
}

function dashboard_config(): ?array
{
    $path = dashboard_config_path();
    if (!is_file($path)) {
        return null;
    }
    $config = require $path;
    return is_array($config) ? $config : null;
}

// Brak SETUP_COMPLETE = instancja już skonfigurowana.
function dashboard_setup_complete(?array $config): bool
{
    if ($config === null) {
        return false;
    }
    if (!array_key_exists('SETUP_COMPLETE', $config)) {
        return true;
    }
    return (bool) $config['SETUP_COMPLETE'];
}

function dashboard_panel_ids(): array
{
    return ['internet', 'usage', 'domains', 'calendar', 'weather', 'clock', 'events', 'countdown', 'lastfm', 'tidal'];
}

/** Nowe panele (lastfm/tidal) sa wylaczone, dopoki ktos ich nie zaznaczy. */
function dashboard_panel_default(string $id): bool
{
    return !in_array($id, ['lastfm', 'tidal'], true);
}

function dashboard_normalize_panels(mixed $in): array
{
    $in = is_array($in) ? $in : [];
    $out = [];
    foreach (dashboard_panel_ids() as $id) {
        $out[$id] = array_key_exists($id, $in) ? (bool) $in[$id] : dashboard_panel_default($id);
    }
    return $out;
}

/** Kafle, ktore mozna przestawiac miedzy kolumnami. Internet zostaje belka u gory. */
function dashboard_movable_panel_ids(): array
{
    return ['usage', 'domains', 'calendar', 'weather', 'clock', 'events', 'countdown', 'lastfm', 'tidal'];
}

function dashboard_layout_columns(): array
{
    return ['left', 'mid', 'right'];
}

function dashboard_default_layout(): array
{
    return [
        'left'  => ['tidal', 'usage', 'domains'],
        'mid'   => ['calendar', 'weather', 'lastfm'],
        'right' => ['clock', 'events', 'countdown'],
    ];
}

function dashboard_normalize_layout(mixed $in): array
{
    $movable = dashboard_movable_panel_ids();
    $defaults = dashboard_default_layout();
    $in = is_array($in) ? $in : [];
    $placed = [];
    $out = ['left' => [], 'mid' => [], 'right' => []];

    foreach (dashboard_layout_columns() as $col) {
        $list = is_array($in[$col] ?? null) ? $in[$col] : [];
        foreach ($list as $id) {
            $id = (string) $id;
            if (!in_array($id, $movable, true) || isset($placed[$id])) {
                continue;
            }
            $out[$col][] = $id;
            $placed[$id] = true;
        }
    }

    foreach ($defaults as $col => $ids) {
        foreach ($ids as $id) {
            if (!isset($placed[$id])) {
                $out[$col][] = $id;
                $placed[$id] = true;
            }
        }
    }

    return $out;
}

function dashboard_php_export(mixed $value, int $indent = 0): string
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
            $lines[] = $pad . '    ' . $key . dashboard_php_export($v, $indent + 1) . ',';
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

function dashboard_config_preferred_keys(): array
{
    return [
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
        'LAYOUT',
        'LASTFM_USER',
        'LASTFM_FRIEND',
        'LASTFM_API_KEY',
        'LASTFM_CACHE_TTL',
        'TIDAL_CLIENT_ID',
        'TIDAL_CLIENT_SECRET',
        'TIDAL_COUNTRY',
        'TIDAL_CACHE_TTL',
        'SETUP_COMPLETE',
    ];
}

function dashboard_write_config(array $config): bool
{
    $ordered = [];
    foreach (dashboard_config_preferred_keys() as $key) {
        if (array_key_exists($key, $config)) {
            $ordered[$key] = $config[$key];
        }
    }
    foreach ($config as $key => $value) {
        if (!array_key_exists($key, $ordered)) {
            $ordered[$key] = $value;
        }
    }

    $php = "<?php\n"
        . "// Wygenerowane przez panel konfiguracji. Nie serwowany (nginx deny).\n"
        . "// URL-e iCal traktuj jak sekrety.\n\n"
        . 'return ' . dashboard_php_export($ordered) . ";\n";

    $path = dashboard_config_path();
    if (file_put_contents($path, $php, LOCK_EX) === false) {
        return false;
    }
    clearstatcache(true, $path);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($path, true);
    }
    return true;
}

/** Publiczny prefix aplikacji (bez koncowego /), np. http://dashboard.lan/dashboard */
function dashboard_public_base(): string
{
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = $fwd === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $host = trim(explode(',', $host)[0]);
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    if (str_ends_with($dir, '/api')) {
        $dir = substr($dir, 0, -4);
    }
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $dir = '';
    }
    return $scheme . '://' . $host . $dir;
}

function dashboard_fail_unconfigured(): never
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code(503);
    }
    echo json_encode([
        'error' => 'Brak config.php — uruchom: php install.php',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
