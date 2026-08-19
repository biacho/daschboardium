<?php

require_once __DIR__ . '/load-config.php';
require_once __DIR__ . '/http.php';

const TIDAL_AUTHORIZE = 'https://login.tidal.com/authorize';
const TIDAL_TOKEN = 'https://auth.tidal.com/v1/oauth2/token';
const TIDAL_API = 'https://openapi.tidal.com/v2';
const TIDAL_SCOPES = 'user.read collection.read';

function tidal_callback_uri(): string
{
    return dashboard_public_base() . '/api/tidal-callback.php';
}

function tidal_home_uri(array $query = []): string
{
    $q = $query === [] ? '' : ('?' . http_build_query($query));
    return dashboard_public_base() . '/' . $q;
}

function tidal_country(array $config): string
{
    $c = strtoupper(trim((string) ($config['TIDAL_COUNTRY'] ?? 'PL')));
    return preg_match('/^[A-Z]{2}$/', $c) ? $c : 'PL';
}

function tidal_token_store(): ?array
{
    $raw = dashboard_cache_read('cache_tidal_tokens.json');
    if ($raw === null || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function tidal_token_save(array $data): bool
{
    return dashboard_cache_write('cache_tidal_tokens.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function tidal_token_clear(): void
{
    dashboard_cache_write('cache_tidal_tokens.json', '{}');
}

function tidal_oauth_save(array $data): bool
{
    $data['created'] = time();
    return dashboard_cache_write('cache_tidal_oauth.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function tidal_oauth_take(): ?array
{
    $raw = dashboard_cache_read('cache_tidal_oauth.json');
    dashboard_cache_write('cache_tidal_oauth.json', '{}');
    if ($raw === null || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['verifier']) || empty($data['state'])) {
        return null;
    }
    if (time() - (int) ($data['created'] ?? 0) > 600) {
        return null;
    }
    return $data;
}

function tidal_pkce(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return [$verifier, $challenge];
}

function tidal_connected(): bool
{
    $store = tidal_token_store();
    return is_array($store) && !empty($store['refresh_token']);
}

function tidal_api(string $path, string $access, array $query = []): array
{
    $url = TIDAL_API . $path;
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }
    return dashboard_http('GET', $url, [
        'timeout' => 12,
        'headers' => [
            'Authorization' => 'Bearer ' . $access,
            'Accept'        => 'application/vnd.api+json',
        ],
    ]);
}

function tidal_exchange(array $fields, array $config): array
{
    $id = (string) ($config['TIDAL_CLIENT_ID'] ?? '');
    $secret = (string) ($config['TIDAL_CLIENT_SECRET'] ?? '');
    $auth = base64_encode($id . ':' . $secret);
    $res = dashboard_http('POST', TIDAL_TOKEN, [
        'timeout' => 12,
        'headers' => [
            'Authorization' => 'Basic ' . $auth,
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Accept'        => 'application/json',
        ],
        'body' => http_build_query($fields),
    ]);
    if (!$res['ok'] || !is_array($res['json']) || empty($res['json']['access_token'])) {
        $msg = is_array($res['json'])
            ? (string) ($res['json']['error_description'] ?? $res['json']['error'] ?? $res['error'])
            : (string) $res['error'];
        return ['ok' => false, 'error' => $msg !== '' ? $msg : 'Nie udało się wymienić tokena TIDAL'];
    }
    return ['ok' => true, 'token' => $res['json']];
}

function tidal_persist_token(array $token, ?array $prev = null): array
{
    $prev = $prev ?? [];
    $store = [
        'access_token'  => (string) $token['access_token'],
        'refresh_token' => (string) ($token['refresh_token'] ?? $prev['refresh_token'] ?? ''),
        'token_type'    => (string) ($token['token_type'] ?? 'Bearer'),
        'expires_at'    => time() + max(60, (int) ($token['expires_in'] ?? 3600) - 60),
        'user'          => $prev['user'] ?? null,
    ];
    tidal_token_save($store);
    return $store;
}

function tidal_access(array $config): array
{
    $id = trim((string) ($config['TIDAL_CLIENT_ID'] ?? ''));
    $secret = trim((string) ($config['TIDAL_CLIENT_SECRET'] ?? ''));
    if ($id === '' || $secret === '') {
        return ['ok' => false, 'error' => 'Brak Client ID / Secret — uzupełnij w konfiguracji'];
    }
    $store = tidal_token_store();
    if ($store === null || empty($store['refresh_token'])) {
        return ['ok' => false, 'error' => 'TIDAL nie jest połączony'];
    }
    if (!empty($store['access_token']) && (int) ($store['expires_at'] ?? 0) > time()) {
        return ['ok' => true, 'store' => $store];
    }
    $ex = tidal_exchange([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $store['refresh_token'],
    ], $config);
    if (!$ex['ok']) {
        return ['ok' => false, 'error' => 'Sesja TIDAL wygasła — połącz ponownie'];
    }
    return ['ok' => true, 'store' => tidal_persist_token($ex['token'], $store)];
}

function tidal_included_map(?array $included): array
{
    $map = [];
    foreach (is_array($included) ? $included : [] as $row) {
        if (!is_array($row) || empty($row['type']) || !isset($row['id'])) {
            continue;
        }
        $map[$row['type'] . ':' . $row['id']] = $row;
    }
    return $map;
}

function tidal_rel_ids(array $resource, string $rel): array
{
    $data = $resource['relationships'][$rel]['data'] ?? null;
    if (!is_array($data)) {
        return [];
    }
    if (isset($data['id'])) {
        return [[(string) ($data['type'] ?? ''), (string) $data['id']]];
    }
    $out = [];
    foreach ($data as $row) {
        if (is_array($row) && isset($row['id'])) {
            $out[] = [(string) ($row['type'] ?? ''), (string) $row['id']];
        }
    }
    return $out;
}

function tidal_artwork(array $map, array $resource): string
{
    if (($resource['type'] ?? '') === 'artworks') {
        $best = '';
        $bestW = -1;
        foreach ($resource['attributes']['files'] ?? [] as $file) {
            if (!is_array($file) || empty($file['href'])) {
                continue;
            }
            $w = (int) ($file['meta']['width'] ?? 0);
            if ($w >= $bestW) {
                $bestW = $w;
                $best = (string) $file['href'];
            }
        }
        return $best;
    }
    foreach (tidal_rel_ids($resource, 'coverArt') as [$type, $id]) {
        $art = $map[$type . ':' . $id] ?? null;
        if (is_array($art)) {
            $href = tidal_artwork($map, $art);
            if ($href !== '') {
                return $href;
            }
        }
    }
    foreach (tidal_rel_ids($resource, 'albums') as [$type, $id]) {
        $album = $map[$type . ':' . $id] ?? null;
        if (is_array($album)) {
            $href = tidal_artwork($map, $album);
            if ($href !== '') {
                return $href;
            }
        }
    }
    return '';
}

function tidal_artist_names(array $map, array $track): string
{
    $names = [];
    foreach (tidal_rel_ids($track, 'artists') as [$type, $id]) {
        $artist = $map[$type . ':' . $id] ?? null;
        $name = is_array($artist) ? trim((string) ($artist['attributes']['name'] ?? '')) : '';
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return implode(', ', $names);
}

function tidal_album_title(array $map, array $track): string
{
    foreach (tidal_rel_ids($track, 'albums') as [$type, $id]) {
        $album = $map[$type . ':' . $id] ?? null;
        $title = is_array($album) ? trim((string) ($album['attributes']['title'] ?? '')) : '';
        if ($title !== '') {
            return $title;
        }
    }
    return '';
}

function tidal_fetch_user(string $access): ?array
{
    $res = tidal_api('/users/me', $access);
    $data = $res['json']['data'] ?? null;
    if (!is_array($data)) {
        return null;
    }
    $attr = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
    return [
        'id'       => (string) ($data['id'] ?? ''),
        'username' => (string) ($attr['username'] ?? ''),
        'country'  => (string) ($attr['country'] ?? ''),
    ];
}

function tidal_fetch_tracks(string $access, string $country): array
{
    $rel = tidal_api('/userCollectionTracks/me/relationships/items', $access, [
        'include'     => 'items',
        'sort'        => '-addedAt',
        'countryCode' => $country,
        'locale'      => 'pl-PL',
    ]);
    if (!$rel['ok'] || !is_array($rel['json'])) {
        return ['tracks' => [], 'error' => 'Nie udało się pobrać kolekcji TIDAL'];
    }
    $ids = [];
    foreach ($rel['json']['data'] ?? [] as $row) {
        if (is_array($row) && isset($row['id'])) {
            $ids[] = (string) $row['id'];
        }
        if (count($ids) >= 20) {
            break;
        }
    }
    if ($ids === []) {
        return ['tracks' => [], 'error' => null];
    }

    $cat = tidal_api('/tracks', $access, [
        'filter[id]'  => implode(',', $ids),
        'include'     => 'artists,albums,albums.coverArt',
        'countryCode' => $country,
        'locale'      => 'pl-PL',
    ]);
    $map = tidal_included_map($cat['json']['included'] ?? []);
    foreach ($cat['json']['data'] ?? [] as $row) {
        if (is_array($row) && isset($row['type'], $row['id'])) {
            $map[$row['type'] . ':' . $row['id']] = $row;
        }
    }

    $tracks = [];
    foreach ($ids as $id) {
        $track = $map['tracks:' . $id] ?? null;
        if (!is_array($track)) {
            continue;
        }
        $title = trim((string) ($track['attributes']['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $tracks[] = [
            'id'       => $id,
            'title'    => $title,
            'artist'   => tidal_artist_names($map, $track),
            'album'    => tidal_album_title($map, $track),
            'image'    => tidal_artwork($map, $track),
            'duration' => tidal_iso_seconds((string) ($track['attributes']['duration'] ?? '')),
        ];
    }
    return ['tracks' => $tracks, 'error' => null];
}

function tidal_iso_seconds(string $iso): int
{
    if ($iso === '' || !preg_match('/^P(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)$/', $iso, $m)) {
        return 0;
    }
    return ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) round((float) ($m[3] ?? 0));
}

function tidal_tracks_from_included(array $ids, array $map): array
{
    $tracks = [];
    foreach ($ids as $id) {
        $track = $map['tracks:' . $id] ?? null;
        if (!is_array($track)) {
            continue;
        }
        $title = trim((string) ($track['attributes']['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $tracks[] = [
            'id'       => $id,
            'title'    => $title,
            'artist'   => tidal_artist_names($map, $track),
            'album'    => tidal_album_title($map, $track),
            'image'    => tidal_artwork($map, $track),
            'duration' => tidal_iso_seconds((string) ($track['attributes']['duration'] ?? '')),
        ];
    }
    return $tracks;
}

function tidal_hydrate_tracks(string $access, string $country, array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids, static fn($id) => $id !== '')));
    if ($ids === []) {
        return [];
    }
    $cat = tidal_api('/tracks', $access, [
        'filter[id]'  => implode(',', array_slice($ids, 0, 20)),
        'include'     => 'artists,albums,albums.coverArt',
        'countryCode' => $country,
        'locale'      => 'pl-PL',
    ]);
    $map = tidal_included_map($cat['json']['included'] ?? []);
    foreach ($cat['json']['data'] ?? [] as $row) {
        if (is_array($row) && isset($row['type'], $row['id'])) {
            $map[$row['type'] . ':' . $row['id']] = $row;
        }
    }
    return tidal_tracks_from_included($ids, $map);
}

function tidal_fetch_playlist_tracks(string $access, string $country): array
{
    $rel = tidal_api('/userCollectionPlaylists/me/relationships/items', $access, [
        'include'     => 'items',
        'countryCode' => $country,
    ]);
    foreach ($rel['json']['data'] ?? [] as $row) {
        if (!is_array($row) || ($row['type'] ?? '') !== 'playlists' || empty($row['id'])) {
            continue;
        }
        $items = tidal_api('/playlists/' . rawurlencode((string) $row['id']) . '/relationships/items', $access, [
            'include'     => 'items',
            'countryCode' => $country,
        ]);
        $ids = [];
        foreach ($items['json']['data'] ?? [] as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'tracks' && isset($item['id'])) {
                $ids[] = (string) $item['id'];
            }
            if (count($ids) >= 20) {
                break;
            }
        }
        if ($ids !== []) {
            return tidal_hydrate_tracks($access, $country, $ids);
        }
    }
    return [];
}

function tidal_search_tracks(string $access, string $country, string $query): array
{
    $query = trim($query);
    if ($query === '' || mb_strlen($query) > 80) {
        return ['tracks' => [], 'error' => 'Podaj frazę do wyszukania'];
    }
    $res = tidal_api('/searchResults', $access, [
        'filter[query]' => $query,
        'include'       => 'tracks',
        'countryCode'   => $country,
    ]);
    if (!$res['ok'] || !is_array($res['json'])) {
        return ['tracks' => [], 'error' => 'Wyszukiwanie TIDAL nie zadziałało'];
    }
    $ids = [];
    foreach ($res['json']['included'] ?? [] as $row) {
        if (is_array($row) && ($row['type'] ?? '') === 'tracks' && isset($row['id'])) {
            $ids[] = (string) $row['id'];
        }
        if (count($ids) >= 12) {
            break;
        }
    }
    if ($ids === []) {
        foreach ($res['json']['data'] ?? [] as $hit) {
            foreach ($hit['relationships']['tracks']['data'] ?? [] as $row) {
                if (is_array($row) && isset($row['id'])) {
                    $ids[] = (string) $row['id'];
                }
            }
        }
    }
    $tracks = tidal_hydrate_tracks($access, $country, array_slice($ids, 0, 12));
    return ['tracks' => $tracks, 'error' => $tracks === [] ? 'Nic nie znaleziono' : null];
}

function tidal_decode_data_uri(string $uri): ?array
{
    if (!preg_match('#^data:([^;,]+);base64,(.+)$#s', $uri, $m)) {
        return null;
    }
    $bin = base64_decode($m[2], true);
    if (!is_string($bin) || $bin === '') {
        return null;
    }
    return ['mime' => $m[1], 'body' => $bin];
}

function tidal_flatten_hls(string $playlist): string
{
    if (preg_match('#data:application/vnd\.apple\.mpegurl;base64,([A-Za-z0-9+/=]+)#', $playlist, $m)) {
        $inner = base64_decode($m[1], true);
        if (is_string($inner) && str_contains($inner, '#EXTINF')) {
            return $inner;
        }
    }
    return $playlist;
}

function tidal_hls_playlist(array $config, string $trackId): array
{
    $got = tidal_access($config);
    if (!$got['ok']) {
        return ['ok' => false, 'error' => $got['error']];
    }
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $trackId)) {
        return ['ok' => false, 'error' => 'Niepoprawny utwór'];
    }
    $session = bin2hex(random_bytes(16));
    $url = TIDAL_API . '/trackManifests/' . rawurlencode($trackId) . '?' . http_build_query([
        'adaptive'     => 'false',
        'formats'      => 'HEAACV1,AACLC',
        'manifestType' => 'HLS',
        'uriScheme'    => 'DATA',
        'usage'        => 'PLAYBACK',
    ]);
    $res = dashboard_http('GET', $url, [
        'timeout' => 15,
        'headers' => [
            'Authorization'          => 'Bearer ' . $got['store']['access_token'],
            'Accept'                 => 'application/vnd.api+json',
            'x-playback-session-id'  => $session,
        ],
    ]);
    $attr = $res['json']['data']['attributes'] ?? null;
    $uri = is_array($attr) ? (string) ($attr['uri'] ?? '') : '';
    $decoded = tidal_decode_data_uri($uri);
    if (!$res['ok'] || $decoded === null) {
        $detail = $res['json']['errors'][0]['detail'] ?? $res['error'];
        return ['ok' => false, 'error' => is_string($detail) && $detail !== '' ? $detail : 'Brak strumienia'];
    }
    $body = $decoded['mime'] === 'application/vnd.apple.mpegurl'
        ? tidal_flatten_hls($decoded['body'])
        : $decoded['body'];
    return [
        'ok'           => true,
        'playlist'     => $body,
        'presentation' => (string) ($attr['trackPresentation'] ?? ''),
        'reason'       => (string) ($attr['previewReason'] ?? ''),
    ];
}

function tidal_snapshot(array $config, string $query = ''): array
{
    $got = tidal_access($config);
    if (!$got['ok']) {
        return [
            'connected' => false,
            'error'     => $got['error'],
            'user'      => null,
            'tracks'    => [],
        ];
    }
    $store = $got['store'];
    $access = (string) $store['access_token'];
    $user = $store['user'] ?? null;
    if (!is_array($user) || empty($user['username'])) {
        $user = tidal_fetch_user($access);
        if (is_array($user)) {
            $store['user'] = $user;
            tidal_token_save($store);
        }
    }
    $country = tidal_country($config);
    if ($query !== '') {
        $col = tidal_search_tracks($access, $country, $query);
    } else {
        $col = tidal_fetch_tracks($access, $country);
        if (($col['error'] ?? null) === null && $col['tracks'] === []) {
            $fromPl = tidal_fetch_playlist_tracks($access, $country);
            if ($fromPl !== []) {
                $col['tracks'] = $fromPl;
            }
        }
    }
    return [
        'connected' => true,
        'error'     => $col['error'],
        'user'      => $user,
        'tracks'    => $col['tracks'],
    ];
}
