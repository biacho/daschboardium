<?php
/**
 * tidal-callback.php
 * Wymienia code na tokeny i wraca do kiosku.
 */

require_once dirname(__DIR__) . '/lib/tidal.php';

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$fail = static function (string $msg): never {
    header('Location: ' . tidal_home_uri(['config' => '1', 'tidal' => 'err', 'msg' => $msg]));
    exit;
};

if (isset($_GET['error'])) {
    $fail((string) $_GET['error']);
}

$code = trim((string) ($_GET['code'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));
if ($code === '' || $state === '') {
    $fail('missing');
}

$pending = tidal_oauth_take();
if ($pending === null || !hash_equals((string) $pending['state'], $state)) {
    $fail('state');
}

$ex = tidal_exchange([
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => tidal_callback_uri(),
    'code_verifier' => $pending['verifier'],
], $config);

if (!$ex['ok']) {
    $fail('token');
}

$store = tidal_persist_token($ex['token']);
$user = tidal_fetch_user((string) $store['access_token']);
if (is_array($user)) {
    $store['user'] = $user;
    tidal_token_save($store);
}
dashboard_cache_write('cache_tidal.json', '');

header('Location: ' . tidal_home_uri(['config' => '1', 'tidal' => 'ok']));
exit;
