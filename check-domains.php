<?php
/**
 * check-domains.php
 * Monitoring domen z config.php (DOMAINS). Dla kazdej domeny sprawdza:
 *   - HTTP  - kod odpowiedzi i czas (cURL),
 *   - DNS   - rekordy A / MX / CAA przez DNS-over-HTTPS (dns.google), zeby nie
 *             zalezec od cache lokalnego resolvera,
 *   - SSL   - ile dni do wygasniecia certyfikatu,
 *   - domena- data wygasniecia z RDAP (nastepca WHOIS, zwraca JSON).
 * Zwraca JSON. Wynik cache'owany do pliku (DOMAINS_CACHE_TTL), a RDAP osobno
 * i duzo dluzej (DOMAINS_RDAP_TTL) - rejestry maja rate limity, a data waznosci
 * domeny i tak zmienia sie raz w roku.
 * Padniecie pojedynczej domeny nie wywala calosci - to wlasnie jest wynik.
 */

require_once __DIR__ . '/load-config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$cacheFile = __DIR__ . '/cache_domains.json';
$rdapFile  = __DIR__ . '/cache_domains_rdap.json';
$cacheTtl  = $config['DOMAINS_CACHE_TTL'] ?? 300;
$rdapTtl   = $config['DOMAINS_RDAP_TTL']  ?? 86400;

// Cache wazny gdy: nie wygasl (TTL) ORAZ jest nowszy niz config.php i sam skrypt
// - dzieki temu zmiana listy domen w config.php widac od razu, bez czekania na TTL.
if (file_exists($cacheFile)
    && (time() - filemtime($cacheFile) < $cacheTtl)
    && filemtime($cacheFile) >= filemtime(__DIR__ . '/config.php')
    && filemtime($cacheFile) >= filemtime(__FILE__)) {
    echo file_get_contents($cacheFile);
    exit;
}

/** Proste GET-nij-i-zdekoduj-JSON (null przy bledzie sieci/parsowania). */
function fetchJson(string $url, array $headers = [], int $timeout = 6): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,   // rdap.org bootstrapuje przez 302
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'ipad-dashboard/1.0 (domain monitor)',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        return null;
    }
    $data = json_decode($body, true);

    return is_array($data) ? $data : null;
}

/** Zapytanie DNS-over-HTTPS do Google. Zwraca ['ok'=>bool, 'answers'=>string[]]. */
function queryDoh(string $domain, string $type): array
{
    $data = fetchJson('https://dns.google/resolve?name=' . urlencode($domain) . '&type=' . $type);
    if ($data === null) {
        return ['ok' => false, 'answers' => []];
    }

    // Status 0 = NOERROR, 3 = NXDOMAIN. Brak "Answer" przy Status 0 = po prostu
    // brak takiego rekordu (dla CAA to normalne, nie blad).
    return [
        'ok'      => ($data['Status'] ?? -1) === 0,
        'answers' => array_column($data['Answer'] ?? [], 'data'),
    ];
}

/**
 * Ile dni zostalo do wygasniecia certyfikatu SSL (null, gdy nie da sie odczytac).
 * verify_peer=false, bo chcemy poznac date takze dla certyfikatu juz niewaznego.
 */
function sslDaysLeft(string $host, int $port = 443): ?int
{
    $ctx = stream_context_create(['ssl' => [
        'capture_peer_cert' => true,
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    ]]);

    $client = @stream_socket_client(
        'ssl://' . $host . ':' . $port,
        $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$client) {
        return null;
    }

    $params = stream_context_get_params($client);
    fclose($client);

    $cert = $params['options']['ssl']['peer_certificate'] ?? null;
    if (!$cert) {
        return null;
    }
    $info = openssl_x509_parse($cert);
    if (!$info || empty($info['validTo_time_t'])) {
        return null;
    }

    return (int) floor(($info['validTo_time_t'] - time()) / 86400);
}

/** Data wygasniecia domeny z RDAP (ISO 8601) albo null. */
function rdapExpiry(string $domain): ?string
{
    // rdap.org = bootstrap IANA -> przekierowuje do wlasciwego rejestru.
    // Dla .pl NASK bywa szybszy i pewniejszy, wiec jest fallbackiem.
    $candidates = ['https://rdap.org/domain/' . $domain];
    if (str_ends_with($domain, '.pl')) {
        $candidates[] = 'https://rdap.dns.pl/domain/' . $domain;
    }

    foreach ($candidates as $url) {
        $data = fetchJson($url, ['Accept: application/rdap+json'], 8);
        foreach ($data['events'] ?? [] as $event) {
            if (($event['eventAction'] ?? '') === 'expiration' && !empty($event['eventDate'])) {
                return $event['eventDate'];
            }
        }
    }

    return null;
}

/** RDAP z wlasnym, dlugim cache (rejestry maja rate limity). */
function cachedRdapExpiry(string $domain, string $rdapFile, int $rdapTtl): ?string
{
    static $cache = null;
    static $dirty = false;

    if ($cache === null) {
        $cache = file_exists($rdapFile)
            ? (json_decode(file_get_contents($rdapFile), true) ?: [])
            : [];
        // Zapis dopiero na koncu requestu, po wszystkich domenach
        register_shutdown_function(function () use (&$cache, &$dirty, $rdapFile) {
            if ($dirty) {
                @file_put_contents($rdapFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
            }
        });
    }

    $entry = $cache[$domain] ?? null;
    if ($entry && (time() - ($entry['at'] ?? 0) < $rdapTtl)) {
        return $entry['expires'];
    }

    $expires = rdapExpiry($domain);
    if ($expires === null && $entry) {
        return $entry['expires']; // rejestr nie odpowiedzial - zostaw stara wartosc
    }

    $cache[$domain] = ['expires' => $expires, 'at' => time()];
    $dirty = true;

    return $expires;
}

/** Rozbija wartosc CAA ("0 issue \"letsencrypt.org\"") na liste wystawcow. */
function parseCaaIssuers(array $answers): array
{
    $issuers = [];
    foreach ($answers as $entry) {
        if (preg_match('/^\d+\s+(issue|issuewild)\s+"([^"]*)"/', $entry, $m) && $m[2] !== '') {
            $issuers[] = $m[2];
        }
    }

    return array_values(array_unique($issuers));
}

$domains = $config['DOMAINS'] ?? [];
$results = [];

foreach ($domains as $name => $entry) {
    // Wpis moze byc stringiem (sama domena) albo tablica z opcjami
    $cfg    = is_array($entry) ? $entry : ['domain' => $entry];
    $domain = $cfg['domain'] ?? (is_string($name) ? $name : '');
    $url    = $cfg['url'] ?? ('https://' . $domain . '/');

    if ($domain === '') {
        continue;
    }

    // --- HTTP ---
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'ipad-dashboard/1.0 (domain monitor)',
    ]);
    curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $httpMs    = (int) round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    $httpError = curl_error($ch);
    curl_close($ch);

    $httpOk = ($httpError === '' && $httpCode > 0 && $httpCode < 400);

    // --- DNS (A / MX / CAA) ---
    $a   = queryDoh($domain, 'A');
    $mx  = queryDoh($domain, 'MX');
    $caa = queryDoh($domain, 'CAA');

    $aOk  = $a['ok']  && !empty($a['answers']);
    $mxOk = $mx['ok'] && !empty($mx['answers']);

    // --- SSL + wygasniecie domeny ---
    $sslDays  = str_starts_with($url, 'https://') ? sslDaysLeft($domain) : null;
    $expires  = cachedRdapExpiry($domain, $rdapFile, $rdapTtl);
    $domDays  = $expires ? (int) floor((strtotime($expires) - time()) / 86400) : null;

    // --- Wartosci oczekiwane (opcjonalne w config.php) ---
    $mismatch = [];
    if (!empty($cfg['expect_a']) && !in_array($cfg['expect_a'], $a['answers'], true)) {
        $mismatch[] = 'A';
    }
    if (!empty($cfg['expect_mx'])) {
        $found = false;
        foreach ($mx['answers'] as $rec) {
            if (str_contains($rec, $cfg['expect_mx'])) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $mismatch[] = 'MX';
        }
    }

    // --- Stan zbiorczy: error > warn > ok ---
    $status = 'ok';
    if (!$httpOk || !$aOk || ($sslDays !== null && $sslDays < 0) || ($domDays !== null && $domDays < 0)) {
        $status = 'error';
    } elseif (!$mxOk
        || $mismatch
        || ($sslDays !== null && $sslDays < 14)
        || ($domDays !== null && $domDays < 30)) {
        $status = 'warn';
    }

    $results[] = [
        'name'     => $domain,
        'status'   => $status,
        'http'     => [
            'ok'    => $httpOk,
            'code'  => $httpCode ?: null,
            'ms'    => $httpError === '' ? $httpMs : null,
            'error' => $httpError !== '' ? $httpError : null,
        ],
        'dns'      => [
            'aOk'        => $aOk,
            'mxOk'       => $mxOk,
            'caaPresent' => !empty($caa['answers']),  // brak CAA to nie blad
            'a'          => $a['answers'],
            'mx'         => $mx['answers'],
            'caaIssuers' => parseCaaIssuers($caa['answers']),
        ],
        'sslDays'  => $sslDays,
        'expires'  => $expires,
        'domDays'  => $domDays,
        'mismatch' => $mismatch,
    ];
}

$json = json_encode(
    ['domains' => $results, 'generated' => date('c')],
    JSON_UNESCAPED_UNICODE
);

@file_put_contents($cacheFile, $json);
echo $json;
