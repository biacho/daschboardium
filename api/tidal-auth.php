<?php
/**
 * tidal-auth.php
 * Start OAuth 2.1 + PKCE. Wroc po zalogowaniu na tidal-callback.php.
 */

require_once dirname(__DIR__) . '/lib/tidal.php';

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

$id = trim((string) ($config['TIDAL_CLIENT_ID'] ?? ''));
$secret = trim((string) ($config['TIDAL_CLIENT_SECRET'] ?? ''));
if ($id === '' || $secret === '') {
    header('Location: ' . tidal_home_uri(['config' => '1', 'tidal' => 'err', 'msg' => 'credentials']));
    exit;
}

[$verifier, $challenge] = tidal_pkce();
$state = bin2hex(random_bytes(16));
if (!tidal_oauth_save(['verifier' => $verifier, 'state' => $state])) {
    header('Location: ' . tidal_home_uri(['config' => '1', 'tidal' => 'err', 'msg' => 'store']));
    exit;
}

$qs = http_build_query([
    'response_type'         => 'code',
    'client_id'             => $id,
    'redirect_uri'          => tidal_callback_uri(),
    'scope'                 => TIDAL_SCOPES,
    'code_challenge_method' => 'S256',
    'code_challenge'        => $challenge,
    'state'                 => $state,
]);

header('Location: ' . TIDAL_AUTHORIZE . '?' . $qs);
exit;
