<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ten skrypt uruchamia się tylko z CLI.\n");
}

require_once dirname(__DIR__) . '/lib/load-config.php';

$root = dashboard_root();
$example = dashboard_config_example_path();
$config = dashboard_config_path();

if (!is_dir($root . '/config') && !@mkdir($root . '/config', 0755, true)) {
    fwrite(STDERR, "Nie udało się utworzyć katalogu config/\n");
    exit(1);
}
if (!is_dir($root . '/var') && !@mkdir($root . '/var', 0755, true)) {
    fwrite(STDERR, "Nie udało się utworzyć katalogu var/\n");
    exit(1);
}

if (!is_file($example)) {
    fwrite(STDERR, "Brak config/config.example.php\n");
    exit(1);
}

if (is_file($config)) {
    echo "config.php już jest — nie nadpisuję.\n";
} else {
    if (!@copy($example, $config)) {
        fwrite(STDERR, "Nie udało się skopiować config.example.php → config.php\n");
        exit(1);
    }
    echo "Utworzono config/config.php z przykładu.\n";
}

$caches = [
    'cache_events.json',
    'cache_domains.json',
    'cache_domains_rdap.json',
    'cache_usage.json',
    'cache_lastfm.json',
    'cache_lastfm_friends.json',
    'cache_tidal.json',
    'cache_tidal_tokens.json',
    'cache_tidal_oauth.json',
];

foreach ($caches as $name) {
    $path = dashboard_cache_path($name);
    if (!is_file($path)) {
        if (!dashboard_cache_write($name, '')) {
            fwrite(STDERR, "Nie udało się utworzyć var/$name\n");
            exit(1);
        }
        echo "Utworzono var/" . basename($path) . "\n";
    }
}

foreach (array_merge([$config], array_map('dashboard_cache_path', $caches)) as $path) {
    if (is_file($path) && !@chmod($path, 0666)) {
        fwrite(STDERR, "Uwaga: nie udało się chmod 666 " . basename($path) . " (php-fpm może nie zapisać)\n");
    }
}

if (!is_file(dashboard_vendor_autoload())) {
    echo "Brak vendor/ — uruchom: composer install\n";
} else {
    echo "vendor/ jest na miejscu.\n";
}

echo "\nDalej:\n";
echo "  1. Skopiuj deploy/nginx.conf.example i dostosuj root / allow.\n";
echo "  2. sudo nginx -t && sudo systemctl reload nginx\n";
echo "  3. Otwórz kiosk — pierwsze uruchomienie otworzy konfigurację.\n";
