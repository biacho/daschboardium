<?php
/**
 * Jedyny sposób odczytu config.php. Brak pliku to null, nie fatal
 * (świeży clone przed `php install.php`).
 */

function dashboard_config_path(): string
{
    return __DIR__ . '/config.php';
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

/** Brak klucza = instancja sprzed first-run (już skonfigurowana). */
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
