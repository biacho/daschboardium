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
