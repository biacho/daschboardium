<?php
/**
 * get-events.php
 * Sciaga publiczne kalendarze iCal z iCloud (lista w config.php), scala
 * wydarzenia, sortuje chronologicznie i zwraca JSON. Wynik cache'owany do pliku,
 * zeby nie odpytywac iCloud przy kazdym odswiezeniu kiosku. Padniecie pojedynczego
 * kalendarza nie wywala calosci - pozostale i tak sa zwracane.
 */

use ICal\ICal;

require_once __DIR__ . '/load-config.php';

header('Content-Type: application/json; charset=utf-8');

$config = dashboard_config();
if ($config === null) {
    dashboard_fail_unconfigured();
}

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    http_response_code(503);
    echo json_encode(['error' => 'Brak vendor/ — uruchom: composer install'], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/vendor/autoload.php';

$cacheFile = __DIR__ . '/cache_events.json';
$cacheTtl  = $config['CACHE_TTL'];

// --- Sprawdz cache ---
// Cache jest wazny tylko gdy: nie wygasl (TTL) ORAZ jest nowszy niz config.php i sam
// skrypt - dzieki temu zmiana kolorow/nazw/URL-i w config.php widac od razu, bez czekania na TTL.
if (file_exists($cacheFile)
    && (time() - filemtime($cacheFile) < $cacheTtl)
    && filemtime($cacheFile) >= filemtime(__DIR__ . '/config.php')
    && filemtime($cacheFile) >= filemtime(__FILE__)) {
    echo file_get_contents($cacheFile);
    exit;
}

// Normalizuj liste kalendarzy (wsteczna zgodnosc z pojedynczym ICAL_URL)
$calendars = $config['ICAL_URLS']
    ?? ['Kalendarz' => ['url' => $config['ICAL_URL'] ?? '', 'color' => null]];

$output = [];
$errors = [];

// Dozwolone daty: tylko dzis i jutro (strefa kiosku) - precyzyjne odciecie "pojutrze",
// ktore okno DAYS_AHEAD moglo jeszcze wpuscic (np. wydarzenie pojutrze nad ranem)
$tz = new DateTimeZone('Europe/Warsaw');
$allowedDays = [
    (new DateTime('today', $tz))->format('Y-m-d'),
    (new DateTime('tomorrow', $tz))->format('Y-m-d'),
];

foreach ($calendars as $name => $cal) {
    $url   = is_array($cal) ? ($cal['url'] ?? '')   : $cal;
    $color = is_array($cal) ? ($cal['color'] ?? null) : null;

    // Pomijaj puste i niewypelnione placeholdery
    if ($url === '' || strpos($url, 'ZAMIEN-NA-LINK') !== false) {
        continue;
    }

    try {
        // ics-parser stosuje ten filtr PRZED strefami czasowymi i porównuje
        // tylko datę (północ), nie godzinę. filterDaysBefore=0 = okno od "teraz",
        // więc wydarzenie dzisiaj o 16:00 odpada (północ < now). Biblioteka sama
        // każe dać +/- 1 dzień zapasu; precyzyjne cięcie dziś/jutro jest niżej.
        $ical = new ICal($url, [
            'defaultSpan'                 => 2,
            'defaultWeekStart'            => 'MO',
            'disableCharacterReplacement' => false,
            'filterDaysBefore'            => 1,
            'filterDaysAfter'             => $config['DAYS_AHEAD'] + 1,
        ]);

        $events = $ical->eventsFromInterval($config['DAYS_AHEAD'] . ' days');

        foreach ($events as $event) {
            $start = $ical->iCalDateToDateTime($event->dtstart_array[3] ?? $event->dtstart);
            $end   = isset($event->dtend) ? $ical->iCalDateToDateTime($event->dtend_array[3] ?? $event->dtend) : null;

            // Zostaw tylko wydarzenia zaczynajace sie dzis lub jutro
            if (!in_array($start->format('Y-m-d'), $allowedDays, true)) {
                continue;
            }

            // ics-parser nie ma isAllDayEvent(); calodniowe ma DTSTART typu DATE
            // (wartosc bez czesci czasowej "T", np. "20260622" zamiast "20260622T140000")
            $isAllDay = isset($event->dtstart_array[1]) && strpos($event->dtstart_array[1], 'T') === false;

            $output[] = [
                'title'    => $event->summary ?? '(bez tytułu)',
                'start'    => $start->format('c'),
                'end'      => $end ? $end->format('c') : null,
                'allDay'   => $isAllDay,
                'location' => $event->location ?? null,
                'calendar' => $name,
                'color'    => $color,
            ];
        }
    } catch (\Throwable $e) {
        // Padniety kalendarz nie blokuje pozostalych
        $errors[] = $name . ': ' . $e->getMessage();
    }
}

// Sortuj chronologicznie (scalone ze wszystkich kalendarzy)
usort($output, fn($a, $b) => strtotime($a['start']) <=> strtotime($b['start']));

// Nic nie pobrano, a byly bledy -> nie cachuj bledu; zwroc stary cache jesli jest
if (empty($output) && !empty($errors)) {
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        echo json_encode(['events' => [], 'generated' => date('c'), 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$json = json_encode(['events' => $output, 'generated' => date('c')], JSON_UNESCAPED_UNICODE);

file_put_contents($cacheFile, $json);
echo $json;
