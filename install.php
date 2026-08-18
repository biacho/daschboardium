<?php
/**
 * install.php — tylko CLI.
 * Tworzy config.php z przykładu i puste pliki cache z prawami, które
 * php-fpm (user http) potrafi nadpisać. Nie rusza istniejącego config.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ten skrypt uruchamia się tylko z CLI.\n");
}

$dir = __DIR__;
$example = $dir . '/config.example.php';
$config = $dir . '/config.php';

if (!is_file($example)) {
    fwrite(STDERR, "Brak config.example.php\n");
    exit(1);
}

if (is_file($config)) {
    echo "config.php już jest — nie nadpisuję.\n";
} else {
    if (!@copy($example, $config)) {
        fwrite(STDERR, "Nie udało się skopiować config.example.php → config.php\n");
        exit(1);
    }
    echo "Utworzono config.php z przykładu.\n";
}

$caches = [
    'cache_events.json',
    'cache_domains.json',
    'cache_domains_rdap.json',
    'cache_usage.json',
];

foreach ($caches as $name) {
    $path = $dir . '/' . $name;
    if (!is_file($path)) {
        if (@file_put_contents($path, '') === false) {
            fwrite(STDERR, "Nie udało się utworzyć $name\n");
            exit(1);
        }
        echo "Utworzono $name\n";
    }
}

foreach (array_merge(['config.php'], $caches) as $name) {
    $path = $dir . '/' . $name;
    if (is_file($path) && !@chmod($path, 0666)) {
        fwrite(STDERR, "Uwaga: nie udało się chmod 666 $name (php-fpm może nie zapisać)\n");
    }
}

if (!is_file($dir . '/vendor/autoload.php')) {
    echo "Brak vendor/ — uruchom: composer install\n";
} else {
    echo "vendor/ jest na miejscu.\n";
}

echo "\nDalej:\n";
echo "  1. Skopiuj deploy/nginx.conf.example i dostosuj root / allow.\n";
echo "  2. sudo nginx -t && sudo systemctl reload nginx\n";
echo "  3. Otwórz kiosk — pierwsze uruchomienie otworzy konfigurację.\n";
