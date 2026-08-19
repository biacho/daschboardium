<?php
/**
 * lastfm.php
 * Ostatnie scrobble / now playing. Klucz API zostaje na serwerze.
 * Opcjonalnie: now playing jednej obserwowanej osoby (publiczny profil).
 */

require_once dirname(__DIR__) . '/lib/load-config.php';
require_once dirname(__DIR__) . '/lib/http.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$user = trim((string) ($config['LASTFM_USER'] ?? ''));
$friend = trim((string) ($config['LASTFM_FRIEND'] ?? ''));
$key = trim((string) ($config['LASTFM_API_KEY'] ?? ''));
if ($user === '' || $key === '') {
    echo json_encode([
        'configured' => false,
        'error'      => 'Uzupełnij użytkownika i klucz API Last.fm w konfiguracji',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strcasecmp($friend, $user) === 0) {
    $friend = '';
}

$ttl = (int) ($config['LASTFM_CACHE_TTL'] ?? 20);
if ($ttl < 10) {
    $ttl = 10;
}
if ($ttl > 120) {
    $ttl = 120;
}

$cacheFile = dashboard_cache_path('cache_lastfm.json');
$cached = dashboard_cache_read('cache_lastfm.json');
if ($cached !== null
    && $cached !== ''
    && is_file($cacheFile)
    && (time() - filemtime($cacheFile) < $ttl)
    && filemtime($cacheFile) >= filemtime(dashboard_config_path())) {
    echo $cached;
    exit;
}

$pickImage = static function (array $images): string {
    $bySize = [];
    foreach ($images as $img) {
        if (!is_array($img)) {
            continue;
        }
        $url = trim((string) ($img['#text'] ?? ''));
        if ($url === '') {
            continue;
        }
        $bySize[(string) ($img['size'] ?? '')] = $url;
    }
    foreach (['extralarge', 'large', 'medium', 'small'] as $size) {
        if (!empty($bySize[$size])) {
            return $bySize[$size];
        }
    }
    return $bySize[''] ?? '';
};

$parseTracks = static function (mixed $rawTracks) use ($pickImage): array {
    if (is_array($rawTracks) && isset($rawTracks['name'])) {
        $rawTracks = [$rawTracks];
    }
    if (!is_array($rawTracks)) {
        $rawTracks = [];
    }
    $tracks = [];
    foreach ($rawTracks as $row) {
        if (!is_array($row)) {
            continue;
        }
        $artist = $row['artist'] ?? '';
        if (is_array($artist)) {
            $artist = (string) ($artist['name'] ?? $artist['#text'] ?? '');
        }
        $album = $row['album'] ?? '';
        if (is_array($album)) {
            $album = (string) ($album['#text'] ?? $album['name'] ?? '');
        }
        $uts = isset($row['date']['uts']) ? (int) $row['date']['uts'] : null;
        $now = !empty($row['@attr']['nowplaying']);
        $tracks[] = [
            'title'      => (string) ($row['name'] ?? ''),
            'artist'     => (string) $artist,
            'album'      => (string) $album,
            'image'      => $pickImage(is_array($row['image'] ?? null) ? $row['image'] : []),
            'nowPlaying' => $now,
            'playedAt'   => $now ? null : $uts,
        ];
    }
    return $tracks;
};

$fetchRecent = static function (string $nick, string $apiKey, int $limit) use ($parseTracks): array {
    $url = 'https://ws.audioscrobbler.com/2.0/?' . http_build_query([
        'method'   => 'user.getrecenttracks',
        'user'     => $nick,
        'api_key'  => $apiKey,
        'format'   => 'json',
        'limit'    => $limit,
        'extended' => 1,
    ]);
    $res = dashboard_http('GET', $url, ['timeout' => 8]);
    if (!$res['ok'] || !is_array($res['json'])) {
        return ['error' => 'Last.fm nie odpowiada', 'tracks' => []];
    }
    $json = $res['json'];
    if (isset($json['error'])) {
        return ['error' => (string) ($json['message'] ?? 'Błąd Last.fm'), 'tracks' => []];
    }
    return [
        'error'  => null,
        'tracks' => $parseTracks($json['recenttracks']['track'] ?? []),
    ];
};

$own = $fetchRecent($user, $key, 5);
if ($own['error'] !== null && $own['tracks'] === []) {
    echo json_encode([
        'configured' => true,
        'user'       => $user,
        'friend'     => $friend !== '' ? $friend : null,
        'error'      => $own['error'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$friendTracks = [];
$friendError = null;
if ($friend !== '') {
    $watched = $fetchRecent($friend, $key, 1);
    $friendTracks = $watched['tracks'];
    $friendError = $watched['error'];
}

$payload = json_encode([
    'configured'   => true,
    'user'         => $user,
    'friend'       => $friend !== '' ? $friend : null,
    'error'        => $own['error'],
    'tracks'       => $own['tracks'],
    'friendTracks' => $friendTracks,
    'friendError'  => $friendError,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (is_string($payload)) {
    dashboard_cache_write('cache_lastfm.json', $payload);
    echo $payload;
} else {
    echo json_encode(['configured' => true, 'user' => $user, 'error' => 'Błąd kodowania'], JSON_UNESCAPED_UNICODE);
}
