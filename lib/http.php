<?php

/**
 * Wspolny GET/POST JSON. Zwraca ['ok','code','json','raw','error'].
 */
function dashboard_http(string $method, string $url, array $opts = []): array
{
    $headers = $opts['headers'] ?? [];
    $body = $opts['body'] ?? null;
    $timeout = (int) ($opts['timeout'] ?? 10);

    $ch = curl_init($url);
    $curlHeaders = [];
    foreach ($headers as $k => $v) {
        $curlHeaders[] = is_int($k) ? (string) $v : $k . ': ' . $v;
    }

    $curl = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => max(3, $timeout),
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_USERAGENT      => 'dashbordium/' . dashboard_version(),
    ];
    if ($body !== null) {
        $curl[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $curl);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'code' => $code, 'json' => null, 'raw' => '', 'error' => $err !== '' ? $err : 'Błąd sieci'];
    }
    $json = json_decode((string) $raw, true);
    $ok = $code >= 200 && $code < 300;
    return [
        'ok'    => $ok,
        'code'  => $code,
        'json'  => is_array($json) ? $json : null,
        'raw'   => (string) $raw,
        'error' => $ok ? null : ('HTTP ' . $code),
    ];
}
