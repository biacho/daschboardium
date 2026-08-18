<?php
// Skopiuj do config.php (`php install.php`) albo uzupełnij modalem
// przy pierwszym otwarciu kiosku. Nie serwowany (nginx deny).
// URL-e iCal traktuj jak sekrety — tego pliku nie commituj jako config.php.

return [
    'ICAL_URLS' => [],
    'CACHE_TTL' => 300,
    'DAYS_AHEAD' => 2,
    'DOMAINS' => [],
    'DOMAINS_CACHE_TTL' => 300,
    'DOMAINS_RDAP_TTL' => 86400,
    'WEATHER_LAT' => 51.1079,
    'WEATHER_LON' => 17.0385,
    'WEATHER_CITY' => '',
    'SHOW_CLAUDE' => false,
    'SHOW_GROK' => false,
    'GROK_PRODUCTS' => [
        'GrokBuild',
        'GrokChat',
        'GrokImagine',
    ],
    'POMODORO' => [
        10,
        15,
        20,
    ],
    'PANELS' => [
        'internet' => true,
        'usage' => false,
        'domains' => true,
        'calendar' => true,
        'weather' => true,
        'clock' => true,
        'events' => true,
        'countdown' => true,
    ],
    'SETUP_COMPLETE' => false,
];
