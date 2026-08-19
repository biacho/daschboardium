<?php
/**
 * lastfm-friends.php
 * Lista znajomych Last.fm (do selecta w konfiguracji). Klucz zostaje na serwerze.
 */

require_once dirname(__DIR__) . '/lib/load-config.php';
require_once dirname(__DIR__) . '/lib/http.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$posted = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);
    if (is_array($decoded)) {
        $posted = $decoded;
    }
}

$user = trim((string) ($posted['user'] ?? $config['LASTFM_USER'] ?? ''));
$key = trim((string) ($posted['apiKey'] ?? $config['LASTFM_API_KEY'] ?? ''));
$refresh = isset($_GET['refresh']) && (string) $_GET['refresh'] === '1';

$nickRe = '/^[A-Za-z0-9_-]{1,50}$/';
if ($user !== '' && !preg_match($nickRe, $user)) {
    echo json_encode(['error' => 'Last.fm: nick wygląda niepoprawnie', 'friends' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($key !== '' && !preg_match('/^[A-Za-z0-9]{8,64}$/', $key)) {
    echo json_encode(['error' => 'Last.fm: klucz API wygląda niepoprawnie', 'friends' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($user === '' || $key === '') {
    echo json_encode([
        'configured' => false,
        'user'       => $user,
        'friends'    => [],
        'error'      => 'Uzupełnij użytkownika i klucz API Last.fm',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ttl = 900;
$cacheFile = dashboard_cache_path('cache_lastfm_friends.json');
if (!$refresh) {
    $cached = dashboard_cache_read('cache_lastfm_friends.json');
    if ($cached !== null && $cached !== '' && is_file($cacheFile)
        && (time() - filemtime($cacheFile) < $ttl)
        && filemtime($cacheFile) >= filemtime(dashboard_config_path())) {
        $prev = json_decode($cached, true);
        if (is_array($prev) && strcasecmp((string) ($prev['user'] ?? ''), $user) === 0) {
            echo $cached;
            exit;
        }
    }
}

$friends = [];
$error = null;
$page = 1;
$totalPages = 1;
$maxPages = 5;

while ($page <= $totalPages && $page <= $maxPages) {
    $url = 'https://ws.audioscrobbler.com/2.0/?' . http_build_query([
        'method'  => 'user.getfriends',
        'user'    => $user,
        'api_key' => $key,
        'format'  => 'json',
        'limit'   => 50,
        'page'    => $page,
    ]);
    $res = dashboard_http('GET', $url, ['timeout' => 8]);
    if (!$res['ok'] || !is_array($res['json'])) {
        $error = 'Last.fm nie odpowiada';
        break;
    }
    $json = $res['json'];
    if (isset($json['error'])) {
        $error = (string) ($json['message'] ?? 'Błąd Last.fm');
        break;
    }

    $raw = $json['friends']['user'] ?? [];
    if (is_array($raw) && isset($raw['name'])) {
        $raw = [$raw];
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '' || !preg_match($nickRe, $name)) {
            continue;
        }
        if (strcasecmp($name, $user) === 0) {
            continue;
        }
        $real = trim((string) ($row['realname'] ?? ''));
        $friends[$name] = [
            'name'     => $name,
            'realname' => $real,
        ];
    }

    $attr = is_array($json['friends']['@attr'] ?? null) ? $json['friends']['@attr'] : [];
    $totalPages = max(1, (int) ($attr['totalPages'] ?? 1));
    $page++;
}

$list = array_values($friends);
usort($list, static function (array $a, array $b): int {
    return strcasecmp($a['name'], $b['name']);
});

$payload = json_encode([
    'configured' => true,
    'user'       => $user,
    'friends'    => $list,
    'error'      => $error,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (is_string($payload) && $error === null) {
    dashboard_cache_write('cache_lastfm_friends.json', $payload);
    echo $payload;
} elseif (is_string($payload)) {
    echo $payload;
} else {
    echo json_encode(['configured' => true, 'user' => $user, 'friends' => [], 'error' => 'Błąd kodowania'], JSON_UNESCAPED_UNICODE);
}
