<?php
/**
 * usage-snapshot.php  --  URUCHAMIANY Z CLI (cron), NIE przez www!
 *
 * Zbiera trzy rzeczy i zapisuje je do cache_usage.json (serwuje go get-usage.php):
 *
 *  1. PROCENT WYKORZYSTANIA LIMITU PLANU CLAUDE - z endpointu /api/oauth/usage,
 *     tego samego, ktorego uzywa komenda /usage w Claude Code. Liczy go SERWER,
 *     wiec obejmuje wszystkie klienty naraz (dwa edytory, apka macOS, claude.ai)
 *     - inaczej niz liczenie lokalnych plikow, ktore widzi tylko te maszyne.
 *     Autoryzacja: token OAuth z ~/.claude/.credentials.json (plik 600).
 *     Access token zyje ~8 h, wiec gdy Claude Code nie chodzi, sami go
 *     odswiezamy (ten sam POST /v1/oauth/token co CLI) i zapisujemy z powrotem
 *     TYLKO do ~/.claude/.credentials.json - nigdy do katalogu www.
 *     Zapis jest CAS na refreshToken, zeby nie nadpisac rownoleglego refreshu CLI.
 *     ⚠ Endpoint jest wewnetrzny i nieudokumentowany - moze sie zmienic.
 *
 *  2. LIMIT GROKA - z GET cli-chat-proxy.grok.com/v1/billing?format=credits
 *     (to samo, co /usage w Grok CLI). Token z ~/.grok/auth.json; przy wygasnieciu
 *     odswiezamy go przez auth.x.ai/oauth2/token i zapisujemy CAS na refresh_token
 *     z powrotem TYLKO do ~/.grok/auth.json.
 *
 *  3. LICZBE TOKENOW z lokalnych transkryptow (~/.claude/projects/*.jsonl) -
 *     jako uzupelnienie (ile zuzyto na TEJ maszynie, z podzialem na wejscie/
 *     wyjscie/cache i ekwiwalentem kosztu API).
 *
 * Dlaczego osobny skrypt CLI, a nie parsowanie w get-usage.php:
 *   1. ~/.claude ma prawa 700/600 - user "http" (php-fpm) go NIE przeczyta,
 *   2. transkrypty zawieraja pelna tresc rozmow, a credentials token dostepowy -
 *      do katalogu www trafiaja wylacznie liczby, nigdy tresc ani sekrety,
 *   3. parsowanie kilkunastu MB JSONL przy kazdym odswiezeniu kiosku byloby drogie.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ten skrypt uruchamia sie tylko z CLI.\n");
}

require_once dirname(__DIR__) . '/lib/load-config.php';
$config = dashboard_config() ?? [];

$projectsDir = $config['USAGE_PROJECTS_DIR'] ?? (getenv('HOME') . '/.claude/projects');
$outFile     = dashboard_cache_path('cache_usage.json');
$tz          = new DateTimeZone('Europe/Warsaw');

/**
 * Cennik API (USD za 1 mln tokenow) - stan na 2026-07.
 * Cache: zapis 1.25x ceny wejscia dla TTL 5 min i 2x dla TTL 1h, odczyt 0.1x.
 * Uwaga: to tylko EKWIWALENT kosztu w API - abonament Claude Code rozlicza sie inaczej.
 */
const PRICING = [
    'claude-opus-5'      => ['in' => 5.00,  'out' => 25.00],
    'claude-opus-4-8'    => ['in' => 5.00,  'out' => 25.00],
    'claude-opus-4-7'    => ['in' => 5.00,  'out' => 25.00],
    'claude-fable-5'     => ['in' => 10.00, 'out' => 50.00],
    'claude-sonnet-5'    => ['in' => 3.00,  'out' => 15.00],  // promocja do 2026-08-31 nizej
    'claude-sonnet-4-6'  => ['in' => 3.00,  'out' => 15.00],
    'claude-haiku-4-5'   => ['in' => 1.00,  'out' => 5.00],
];

/** Stawki wejsciowe dla modelu (z uwzglednieniem promocji na Sonneta 5). */
function ratesFor(string $model): array
{
    $key = null;
    foreach (array_keys(PRICING) as $candidate) {
        // model w logach bywa z sufiksem daty (np. claude-haiku-4-5-20251001)
        if ($model === $candidate || str_starts_with($model, $candidate)) {
            $key = $candidate;
            break;
        }
    }
    if ($key === null) {
        return ['in' => 0.0, 'out' => 0.0];  // nieznany model - nie zgaduj ceny
    }

    $rates = PRICING[$key];

    // Sonnet 5: cena wprowadzajaca $2/$10 obowiazuje do 31.08.2026
    if ($key === 'claude-sonnet-5' && time() < strtotime('2026-09-01')) {
        $rates = ['in' => 2.00, 'out' => 10.00];
    }

    return $rates;
}

// --- 1. Limity planu z /api/oauth/usage ---------------------------------------

const OAUTH_CLIENT_ID = '9d1c250a-e61b-44d9-88ed-5944d1962f5e';
const OAUTH_TOKEN_URL = 'https://platform.claude.com/v1/oauth/token';
const OAUTH_USAGE_UA  = 'claude-cli/2.1.226 (external, cli)';
/** Odswiez access token, gdy zostalo mniej niz 2 min - unikamy wyscigu z expiresAt. */
const OAUTH_REFRESH_SKEW = 120;

function readJsonFile(string $path): ?array
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Zapis credentials.json: tylko gdy refreshToken nadal jest tym, ktorego uzyliśmy
 * (albo zostal wyczyszczony). Inaczej CLI zdazyl odswiezyc i mamy nie pisac po nim.
 */
function saveRefreshedOauth(string $path, string $usedRefresh, array $patch): bool
{
    $lockPath = $path . '.lock';
    $lf = @fopen($lockPath, 'c');
    if ($lf) {
        flock($lf, LOCK_EX);
    }

    $ok = false;
    $data = readJsonFile($path);
    if (is_array($data)) {
        $cur = $data['claudeAiOauth']['refreshToken'] ?? null;
        if ($cur === $usedRefresh || $cur === '' || $cur === null) {
            $old = is_array($data['claudeAiOauth'] ?? null) ? $data['claudeAiOauth'] : [];
            $data['claudeAiOauth'] = array_merge($old, $patch);
            $dir = dirname($path);
            $tmp = $dir . '/.credentials.json.tmp.' . getmypid();
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($json !== false && @file_put_contents($tmp, $json) !== false) {
                @chmod($tmp, 0600);
                if (@rename($tmp, $path)) {
                    $ok = true;
                } else {
                    @unlink($tmp);
                }
            }
        }
    }

    if ($lf) {
        flock($lf, LOCK_UN);
        fclose($lf);
    }
    return $ok;
}

/**
 * POST /v1/oauth/token - ten sam grant co Claude Code.
 * Zwraca patch do claudeAiOauth (camelCase, expiresAt w ms) albo null.
 */
function refreshOauthToken(array $oauth, ?string &$error = null): ?array
{
    $refresh = $oauth['refreshToken'] ?? '';
    if ($refresh === '') {
        $error = 'brak refresh tokenu (zaloguj się w Claude Code)';
        return null;
    }
    if (!empty($oauth['refreshTokenExpiresAt'])
        && ((int) $oauth['refreshTokenExpiresAt']) / 1000 < time()) {
        $error = 'refresh token wygasł - uruchom Claude Code, żeby się zalogować';
        return null;
    }

    $scopes = $oauth['scopes'] ?? [
        'user:inference',
        'user:profile',
        'user:sessions:claude_code',
        'user:mcp_servers',
        'user:file_upload',
    ];
    $clientId = $oauth['clientId'] ?? OAUTH_CLIENT_ID;

    $payload = json_encode([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh,
        'client_id'     => $clientId,
        'scope'         => implode(' ', $scopes),
    ]);

    $ch = curl_init(OAUTH_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
        ],
        CURLOPT_USERAGENT      => OAUTH_USAGE_UA,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        $error = $curlErr !== '' ? $curlErr : ('odświeżenie tokenu: HTTP ' . $code);
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['access_token']) || !isset($data['expires_in'])) {
        $error = 'nieczytelna odpowiedź przy odświeżaniu tokenu';
        return null;
    }

    $nowMs = (int) round(microtime(true) * 1000);
    $patch = [
        'accessToken' => $data['access_token'],
        'refreshToken'=> $data['refresh_token'] ?? $refresh,
        'expiresAt'   => $nowMs + ((int) $data['expires_in']) * 1000,
        'clientId'    => $clientId,
    ];
    if (isset($data['refresh_token_expires_in'])) {
        $patch['refreshTokenExpiresAt'] = $nowMs + ((int) $data['refresh_token_expires_in']) * 1000;
    }
    if (!empty($data['scope'])) {
        $patch['scopes'] = preg_split('/\s+/', trim((string) $data['scope']), -1, PREG_SPLIT_NO_EMPTY);
    }
    return $patch;
}

/**
 * Wazny access token: odczyt z dysku, w razie potrzeby refresh + zapis CAS.
 */
function getValidAccessToken(string $credFile, ?string &$error = null): ?string
{
    $cred = readJsonFile($credFile);
    $oauth = is_array($cred) ? ($cred['claudeAiOauth'] ?? null) : null;
    if (!is_array($oauth) || (empty($oauth['accessToken']) && empty($oauth['refreshToken']))) {
        $error = 'brak tokenu OAuth (zaloguj się w Claude Code)';
        return null;
    }

    $expiresAt = isset($oauth['expiresAt']) ? ((int) $oauth['expiresAt']) / 1000 : 0;
    if (!empty($oauth['accessToken']) && $expiresAt > time() + OAUTH_REFRESH_SKEW) {
        return $oauth['accessToken'];
    }

    $usedRefresh = (string) ($oauth['refreshToken'] ?? '');
    $patch = refreshOauthToken($oauth, $error);
    if ($patch === null) {
        return null;
    }

    if (saveRefreshedOauth($credFile, $usedRefresh, $patch)) {
        return $patch['accessToken'];
    }

    // CLI zdazyl zapisac nowszy token - uzyj jego, nie nadpisuj
    $again = readJsonFile($credFile);
    $fresh = is_array($again) ? ($again['claudeAiOauth'] ?? null) : null;
    if (is_array($fresh) && !empty($fresh['accessToken'])
        && ((int) ($fresh['expiresAt'] ?? 0)) / 1000 > time()) {
        $error = null;
        return $fresh['accessToken'];
    }

    $error = 'nie udało się zapisać odświeżonego tokenu';
    return null;
}

/**
 * Pobiera wykorzystanie limitow planu (procenty + czasy resetu).
 * Zwraca null, gdy nie ma tokenu / refresh sie nie udal / API nie odpowiedzialo -
 * wtedy wywolujacy zostawia ostatnia znana wartosc.
 */
function fetchPlanUsage(?string &$error = null): ?array
{
    $credFile = getenv('HOME') . '/.claude/.credentials.json';

    if (!is_readable($credFile)) {
        $error = 'brak dostępu do .credentials.json';
        return null;
    }

    static $claudeBackoffUntil = 0;
    if (time() < $claudeBackoffUntil) {
        $error = 'HTTP 429 (odczekuję)';
        return null;
    }

    $accessToken = getValidAccessToken($credFile, $error);
    if ($accessToken === null) {
        return null;
    }

    $cred = readJsonFile($credFile);

    $ch = curl_init('https://api.anthropic.com/api/oauth/usage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'anthropic-beta: oauth-2025-04-20',
        ],
        CURLOPT_USERAGENT      => OAUTH_USAGE_UA,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        if ($code === 429) {
            $claudeBackoffUntil = time() + 300;
        }
        $error = $curlErr !== '' ? $curlErr : ('HTTP ' . $code);
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        $error = 'nieczytelna odpowiedź API';
        return null;
    }

    $spendUsed = $data['spend']['used'] ?? null;

    return [
        'session' => [   // okno 5-godzinne
            'percent'  => $data['five_hour']['utilization'] ?? null,
            'resetsAt' => $data['five_hour']['resets_at']   ?? null,
        ],
        'weekly'  => [
            'percent'  => $data['seven_day']['utilization'] ?? null,
            'resetsAt' => $data['seven_day']['resets_at']   ?? null,
        ],
        // Kredyty "extra usage" - kwota w groszach/centach, stad exponent
        'spend'   => $spendUsed ? [
            'used'     => ((int) $spendUsed['amount_minor']) / (10 ** ((int) ($spendUsed['exponent'] ?? 2))),
            'currency' => $spendUsed['currency'] ?? '',
        ] : null,
        'plan'      => is_array($cred) ? ($cred['claudeAiOauth']['subscriptionType'] ?? null) : null,
        'fetchedAt' => date('c'),
    ];
}

// --- 1b. Limity Groka z /v1/billing?format=credits ----------------------------

const GROK_BILLING_URL = 'https://cli-chat-proxy.grok.com/v1/billing?format=credits';
const GROK_TOKEN_URL   = 'https://auth.x.ai/oauth2/token';

/** Pierwszy wpis w auth.json, ktory ma access token (klucz to issuer::client_id). */
function grokAuthEntry(?array $data): array
{
    if (!is_array($data)) {
        return [null, null];
    }
    foreach ($data as $key => $entry) {
        if (is_array($entry) && (!empty($entry['key']) || !empty($entry['refresh_token']))) {
            return [(string) $key, $entry];
        }
    }
    return [null, null];
}

function saveRefreshedGrok(string $path, string $entryKey, string $usedRefresh, array $patch): bool
{
    $lockPath = $path . '.lock';
    $lf = @fopen($lockPath, 'c');
    if ($lf) {
        flock($lf, LOCK_EX);
    }

    $ok = false;
    $data = readJsonFile($path);
    if (is_array($data) && isset($data[$entryKey]) && is_array($data[$entryKey])) {
        $cur = $data[$entryKey]['refresh_token'] ?? null;
        if ($cur === $usedRefresh || $cur === '' || $cur === null) {
            $data[$entryKey] = array_merge($data[$entryKey], $patch);
            $dir = dirname($path);
            $tmp = $dir . '/.auth.json.tmp.' . getmypid();
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($json !== false && @file_put_contents($tmp, $json) !== false) {
                @chmod($tmp, 0600);
                if (@rename($tmp, $path)) {
                    $ok = true;
                } else {
                    @unlink($tmp);
                }
            }
        }
    }

    if ($lf) {
        flock($lf, LOCK_UN);
        fclose($lf);
    }
    return $ok;
}

function refreshGrokToken(array $oauth, ?string &$error = null): ?array
{
    $refresh  = (string) ($oauth['refresh_token'] ?? '');
    $clientId = (string) ($oauth['oidc_client_id'] ?? '');
    if ($refresh === '' || $clientId === '') {
        $error = 'brak refresh tokenu Groka (uruchom grok login)';
        return null;
    }

    $payload = http_build_query([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh,
        'client_id'     => $clientId,
    ]);

    $ch = curl_init(GROK_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_USERAGENT      => 'grok-cli',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        $error = $curlErr !== '' ? $curlErr : ('odświeżenie tokenu Groka: HTTP ' . $code);
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['access_token'])) {
        $error = 'nieczytelna odpowiedź przy odświeżaniu tokenu Groka';
        return null;
    }

    $ttl = isset($data['expires_in']) ? (int) $data['expires_in'] : 21600;
    $patch = [
        'key'           => $data['access_token'],
        'refresh_token' => $data['refresh_token'] ?? $refresh,
        'expires_at'    => gmdate('Y-m-d\TH:i:s\Z', time() + max(60, $ttl)),
    ];
    return $patch;
}

function getValidGrokAccessToken(string $authFile, ?string &$error = null): ?string
{
    $data = readJsonFile($authFile);
    [$entryKey, $oauth] = grokAuthEntry($data);
    if ($oauth === null) {
        $error = 'brak tokenu Groka (uruchom grok login)';
        return null;
    }

    $expiresAt = 0;
    if (!empty($oauth['expires_at'])) {
        $expiresAt = (int) strtotime((string) $oauth['expires_at']);
    }
    if (!empty($oauth['key']) && $expiresAt > time() + OAUTH_REFRESH_SKEW) {
        return $oauth['key'];
    }

    $usedRefresh = (string) ($oauth['refresh_token'] ?? '');
    $patch = refreshGrokToken($oauth, $error);
    if ($patch === null) {
        return null;
    }

    if ($entryKey !== null && saveRefreshedGrok($authFile, $entryKey, $usedRefresh, $patch)) {
        return $patch['key'];
    }

    $again = readJsonFile($authFile);
    [, $fresh] = grokAuthEntry($again);
    if (is_array($fresh) && !empty($fresh['key'])) {
        $freshExp = !empty($fresh['expires_at']) ? (int) strtotime((string) $fresh['expires_at']) : 0;
        if ($freshExp > time()) {
            $error = null;
            return $fresh['key'];
        }
    }

    $error = 'nie udało się zapisać odświeżonego tokenu Groka';
    return null;
}

/**
 * Procent limitu Groka (wszystkie klienty: Build / Chat / Imagine).
 * Zwraca null, gdy nie ma tokenu / refresh sie nie udal / API nie odpowiedzialo.
 */
function fetchGrokUsage(?string &$error = null): ?array
{
    $authFile = getenv('HOME') . '/.grok/auth.json';
    if (!is_readable($authFile)) {
        $error = 'brak dostępu do ~/.grok/auth.json';
        return null;
    }

    $token = getValidGrokAccessToken($authFile, $error);
    if ($token === null) {
        return null;
    }

    $ch = curl_init(GROK_BILLING_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_USERAGENT      => 'grok-cli',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        $error = $curlErr !== '' ? $curlErr : ('HTTP ' . $code);
        return null;
    }

    $data = json_decode($body, true);
    $cfg  = is_array($data) ? ($data['config'] ?? null) : null;
    if (!is_array($cfg)) {
        $error = 'nieczytelna odpowiedź API Groka';
        return null;
    }

    $period = is_array($cfg['currentPeriod'] ?? null) ? $cfg['currentPeriod'] : [];
    $products = [];
    foreach ($cfg['productUsage'] ?? [] as $p) {
        if (!is_array($p) || empty($p['product'])) {
            continue;
        }
        $products[] = [
            'name'    => (string) $p['product'],
            'percent' => isset($p['usagePercent']) ? (float) $p['usagePercent'] : null,
        ];
    }

    return [
        'percent'      => isset($cfg['creditUsagePercent']) ? (float) $cfg['creditUsagePercent'] : null,
        'resetsAt'     => $period['end'] ?? ($cfg['billingPeriodEnd'] ?? null),
        'periodStart'  => $period['start'] ?? ($cfg['billingPeriodStart'] ?? null),
        'periodType'   => $period['type'] ?? null,
        'products'     => $products,
        'prepaid'      => isset($cfg['prepaidBalance']['val']) ? $cfg['prepaidBalance']['val'] : null,
        'onDemandUsed' => isset($cfg['onDemandUsed']['val']) ? $cfg['onDemandUsed']['val'] : null,
        'onDemandCap'  => isset($cfg['onDemandCap']['val']) ? $cfg['onDemandCap']['val'] : null,
        'fetchedAt'    => date('c'),
    ];
}

/**
 * Pusty kubelek na sume.
 * UWAGA: definicja MUSI byc poza runSnapshot() - w trybie demona funkcja
 * wykonuje sie w petli, a PHP nie pozwala zadeklarowac funkcji dwa razy.
 */
function emptyBucket(): array
{
    return ['in' => 0, 'out' => 0, 'cacheWrite' => 0, 'cacheRead' => 0, 'total' => 0, 'cost' => 0.0, 'calls' => 0];
}

/**
 * Jeden pelny przebieg: limity z API + tokeny z transkryptow -> cache_usage.json.
 * Zwraca jednolinijkowe podsumowanie (do logu / STDOUT).
 */
function runSnapshot(string $projectsDir, string $outFile, DateTimeZone $tz): string
{
    $planError = null;
    $plan = fetchPlanUsage($planError);

    $grokError = null;
    $grok = fetchGrokUsage($grokError);

    // API nie odpowiedzialo -> zostaw ostatnia znana wartosc ze starego snapshotu,
    // zeby kafelek nie zgasl przy jednym nieudanym strzale. planError zostaje,
    // zeby frontend mogl pokazac DLACZEGO liczby sa stare (wczesniej znikal).
    $usedStalePlan = false;
    $usedStaleGrok = false;
    $old = null;
    $oldRaw = dashboard_cache_read('cache_usage.json');
    if ($oldRaw !== null) {
        $old = json_decode($oldRaw, true);
    }
    if ($plan === null && is_array($old) && isset($old['plan']) && is_array($old['plan'])) {
        $plan = $old['plan'];
        $fetchedTs = !empty($plan['fetchedAt']) ? (int) strtotime((string) $plan['fetchedAt']) : 0;
        $recent = $fetchedTs > 0 && (time() - $fetchedTs) < 900;
        $rateLimited = is_string($planError) && str_contains($planError, '429');
        // 429 przez pare minut nie ma sensu jako czerwony banner - liczby sa jeszcze swieze
        if ($recent && $rateLimited) {
            unset($plan['stalePlan']);
        } else {
            $plan['stalePlan'] = true;
            $usedStalePlan = true;
        }
    }
    if ($grok === null && is_array($old) && isset($old['grok']) && is_array($old['grok'])) {
        $grok = $old['grok'];
        $grok['stalePlan'] = true;
        $usedStaleGrok = true;
    }

    // --- 2. Zbierz rekordy z lokalnych transkryptow ---------------------------

    $files = glob($projectsDir . '/*/*.jsonl') ?: [];

    $seen    = [];   // deduplikacja: ten sam requestId+message.id bywa w kilku liniach
    $records = [];   // [ts, model, in, out, cacheWrite5m, cacheWrite1h, cacheRead]

    foreach ($files as $file) {
        $fh = @fopen($file, 'r');
        if (!$fh) {
            continue;
        }

        while (($line = fgets($fh)) !== false) {
            if (strpos($line, '"usage"') === false) {
                continue;  // szybkie odsianie linii bez licznikow
            }

            $rec = json_decode($line, true);
            if (!is_array($rec) || ($rec['type'] ?? '') !== 'assistant') {
                continue;
            }

            $msg   = $rec['message'] ?? [];
            $usage = $msg['usage'] ?? null;
            if (!is_array($usage)) {
                continue;
            }

            $dedupKey = ($rec['requestId'] ?? '') . '|' . ($msg['id'] ?? '');
            if ($dedupKey !== '|' && isset($seen[$dedupKey])) {
                continue;
            }
            $seen[$dedupKey] = true;

            $ts = strtotime($rec['timestamp'] ?? '');
            if (!$ts) {
                continue;
            }

            $cacheCreation = $usage['cache_creation'] ?? [];

            $records[] = [
                'ts'     => $ts,
                'model'  => $msg['model'] ?? 'nieznany',
                'in'     => (int) ($usage['input_tokens'] ?? 0),
                'out'    => (int) ($usage['output_tokens'] ?? 0),
                'read'   => (int) ($usage['cache_read_input_tokens'] ?? 0),
                'w5m'    => (int) ($cacheCreation['ephemeral_5m_input_tokens'] ?? 0),
                'w1h'    => (int) ($cacheCreation['ephemeral_1h_input_tokens'] ?? 0),
                // fallback, gdy brak rozbicia po TTL - policz jak zapis 5-minutowy
                'wSum'   => (int) ($usage['cache_creation_input_tokens'] ?? 0),
            ];
        }
        fclose($fh);
    }

    // --- Agreguj po oknach czasowych ------------------------------------------

    $now         = time();
    $todayStart  = (new DateTime('today', $tz))->getTimestamp();
    $windowStart = $now - 5 * 3600;      // ostatnie 5h (dlugosc okna limitu Claude Code)
    $weekStart   = $now - 7 * 86400;

    $buckets = ['window' => emptyBucket(), 'today' => emptyBucket(), 'week' => emptyBucket()];
    $models  = [];

    foreach ($records as $r) {
        $rates = ratesFor($r['model']);

        // Rozbicie po TTL bywa niepelne - jesli go nie ma, uzyj sumy jako zapisu 5m
        $w5m = $r['w5m'];
        $w1h = $r['w1h'];
        if ($w5m === 0 && $w1h === 0) {
            $w5m = $r['wSum'];
        }
        $cacheWrite = $w5m + $w1h;

        // Koszt: wejscie 1x, wyjscie 1x, zapis cache 1.25x (5m) / 2x (1h), odczyt 0.1x
        $cost = ($r['in']    * $rates['in']
            + $r['out']      * $rates['out']
            + $w5m           * $rates['in'] * 1.25
            + $w1h           * $rates['in'] * 2.0
            + $r['read']     * $rates['in'] * 0.1) / 1_000_000;

        $total = $r['in'] + $r['out'] + $cacheWrite + $r['read'];

        foreach (['window' => $windowStart, 'today' => $todayStart, 'week' => $weekStart] as $name => $from) {
            if ($r['ts'] < $from) {
                continue;
            }
            $buckets[$name]['in']         += $r['in'];
            $buckets[$name]['out']        += $r['out'];
            $buckets[$name]['cacheWrite'] += $cacheWrite;
            $buckets[$name]['cacheRead']  += $r['read'];
            $buckets[$name]['total']      += $total;
            $buckets[$name]['cost']       += $cost;
            $buckets[$name]['calls']++;
        }

        // Rozbicie po modelach - tylko dla dzisiaj (to widac na kafelku)
        if ($r['ts'] >= $todayStart) {
            $name = $r['model'];
            $models[$name] ??= ['total' => 0, 'cost' => 0.0];
            $models[$name]['total'] += $total;
            $models[$name]['cost']  += $cost;
        }
    }

    arsort($models);
    foreach ($buckets as &$b) {
        $b['cost'] = round($b['cost'], 2);
    }
    unset($b);
    foreach ($models as &$m) {
        $m['cost'] = round($m['cost'], 2);
    }
    unset($m);

    $json = json_encode([
        'plan'        => $plan,                // procenty limitu Claude z serwera
        'planError'   => ($plan === null || $usedStalePlan) ? $planError : null,
        'grok'        => $grok,                // procent limitu Groka z /v1/billing
        'grokError'   => ($grok === null || $usedStaleGrok) ? $grokError : null,
        'window'      => $buckets['window'],   // ostatnie 5 godzin, tylko ta maszyna
        'today'       => $buckets['today'],
        'week'        => $buckets['week'],
        'models'      => $models,
        'windowHours' => 5,
        'sessions'    => count($files),
        'generated'   => date('c'),
    ], JSON_UNESCAPED_UNICODE);

    if (!dashboard_cache_write('cache_usage.json', $json)) {
        return 'BLAD: nie udalo sie zapisac ' . $outFile;
    }
    @chmod($outFile, 0666);

    $notes = [];
    if ($usedStalePlan) {
        $notes[] = 'claude STARY: ' . $planError;
    } elseif ($plan === null) {
        $notes[] = 'claude: ' . $planError;
    }
    if ($usedStaleGrok) {
        $notes[] = 'grok STARY: ' . $grokError;
    } elseif ($grok === null) {
        $notes[] = 'grok: ' . $grokError;
    } elseif (isset($grok['percent'])) {
        $notes[] = 'grok ' . $grok['percent'] . '%';
    }

    return sprintf(
        'OK: %d odpowiedzi z %d sesji%s',
        count($records),
        count($files),
        $notes ? ' (' . implode(', ', $notes) . ')' : ''
    );
}

// --- Tryb pracy ---------------------------------------------------------------
// Bez argumentow  -> jeden przebieg i koniec (dobre do recznego uruchomienia).
// --loop[=sekundy]-> pracuje w petli jako demon (domyslnie co 60 s); tak
//                    uruchamia go systemd (dashboard-usage.service).

$interval = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--loop(?:=(\d+))?$/', $arg, $m)) {
        $interval = isset($m[1]) ? max(5, (int) $m[1]) : 60;
    }
}

if ($interval === 0) {
    echo runSnapshot($projectsDir, $outFile, $tz) . "\n";
    exit(0);
}

// Demon: reaguj na systemctl stop/restart (SIGTERM) i Ctrl+C (SIGINT)
$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
    pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });
}

fwrite(STDERR, "Start: odswiezanie co {$interval} s\n");

while ($running) {
    $started = microtime(true);
    fwrite(STDERR, date('H:i:s') . ' ' . runSnapshot($projectsDir, $outFile, $tz) . "\n");

    // Odejmij czas przebiegu, zeby odstepy byly rowne
    $sleep = max(1, $interval - (int) round(microtime(true) - $started));
    for ($i = 0; $i < $sleep && $running; $i++) {
        sleep(1);   // sekundowe kroki, zeby stop dzialal natychmiast
    }
}

fwrite(STDERR, "Zatrzymano.\n");
