<?php
// Szkielet kiosku: kafelki w tiles/<nazwa>/<nazwa>.{html,css,js}.
require_once __DIR__ . '/lib/load-config.php';
$dashboardConfig = dashboard_config();
$needsInstall = ($dashboardConfig === null);
$appVersion = dashboard_version();
$v = (string) time();
$tiles = ['internet', 'usage', 'domains', 'calendar', 'weather', 'clock', 'events', 'countdown'];
function tile($name) {
    $path = __DIR__ . '/tiles/' . $name . '/' . $name . '.html';
    if (is_file($path)) {
        include $path;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="format-detection" content="telephone=no">
<title>Dashbordium</title>
<script>
(function () {
  try {
    var q = new URLSearchParams(location.search).get('theme');
    var t = (q === 'light' || q === 'dark') ? q : localStorage.getItem('dashboard-theme');
    if (t === 'light' || t === 'dark') {
      document.documentElement.setAttribute('data-theme', t);
      if (q === 'light' || q === 'dark') localStorage.setItem('dashboard-theme', t);
    }
  } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?_=<?= $v ?>">
<?php foreach ($tiles as $name): ?>
<link rel="stylesheet" href="tiles/<?= htmlspecialchars($name, ENT_QUOTES) ?>/<?= htmlspecialchars($name, ENT_QUOTES) ?>.css?_=<?= $v ?>">
<?php endforeach; ?>
</head>
<body>
<?php if ($needsInstall): ?>
<div class="install-needed">
  <div class="install-card">
    <p class="install-kicker">Dashbordium <?= htmlspecialchars($appVersion, ENT_QUOTES) ?></p>
    <h1 class="install-title">Brak konfiguracji</h1>
    <p class="install-copy">php-fpm nie umie utworzyć plików w tym katalogu. Na hoście, w katalogu projektu, odpal:</p>
    <pre class="install-cmd">php install.php</pre>
    <p class="install-copy">Potem odśwież tę stronę — otworzy się konfiguracja pierwszego uruchomienia.</p>
  </div>
</div>
</body>
</html>
<?php exit; endif; ?>

<div class="loader" id="bootLoader">
  <div class="loader__shade loader__shade--top" aria-hidden="true"></div>
  <div class="loader__shade loader__shade--bottom" aria-hidden="true"></div>
  <div class="loader__name">Dashbordium</div>
  <div class="loader__bar"><i id="bootLoaderBar"></i></div>
</div>

<div class="kiosk">
<?php tile('internet'); ?>
  <div class="col-left">
<?php tile('usage'); ?>
<?php tile('domains'); ?>
  </div>
  <div class="col-mid">
<?php tile('calendar'); ?>
<?php tile('weather'); ?>
  </div>
  <div class="col-right">
<?php tile('clock'); ?>
<?php tile('events'); ?>
<?php tile('countdown'); ?>
  </div>
</div>

<div class="config-overlay" id="configOverlay" hidden>
  <div class="config-modal" role="dialog" aria-modal="true" aria-labelledby="configTitle">
    <header class="config-head">
      <h2 class="config-title" id="configTitle">Konfiguracja</h2>
      <button type="button" class="config-close" id="configClose" aria-label="Zamknij">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>
    <form class="config-form" id="configForm" autocomplete="off">
      <div class="config-body">
        <section class="config-section">
          <h3 class="config-section-title">Kafelki</h3>
          <p class="config-hint">Odznacz, żeby zdjąć kafelek z siatki. Sąsiedzi biorą jego miejsce.</p>
          <div class="config-checks" id="cfgPanels">
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="internet" checked><span>Internet</span></label>
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="clock" checked><span>Zegar</span></label>
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="calendar" checked><span>Kalendarz</span></label>
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="events" checked><span>Wydarzenia</span></label>
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="countdown" checked><span>Odliczanie</span></label>
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="usage" checked><span>Limity</span></label>
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="domains" checked><span>Domeny</span></label>
            <label class="config-check"><input type="checkbox" class="cfg-panel" value="weather" checked><span>Pogoda</span></label>
          </div>
        </section>

        <section class="config-section">
          <h3 class="config-section-title">Pogoda</h3>
          <p class="config-hint">Miasto to podpis kafelka. Współrzędne możesz wyszukać po nazwie.</p>
          <div class="config-grid config-grid-city">
            <label class="config-field config-field-wide">
              <span>Miasto</span>
              <div class="config-city-row">
                <input id="cfgWeatherCity" type="text" maxlength="80" placeholder="Wrocław">
                <button type="button" class="config-btn ghost" id="cfgWeatherLookup">Szukaj</button>
              </div>
            </label>
            <label class="config-field">
              <span>Szerokość</span>
              <input id="cfgWeatherLat" type="text" inputmode="decimal" placeholder="51.1079">
            </label>
            <label class="config-field">
              <span>Długość</span>
              <input id="cfgWeatherLon" type="text" inputmode="decimal" placeholder="17.0385">
            </label>
          </div>
        </section>

        <section class="config-section">
          <div class="config-section-head">
            <h3 class="config-section-title">Kalendarze</h3>
            <button type="button" class="config-btn ghost" id="cfgAddCal">+ Dodaj</button>
          </div>
          <p class="config-hint">Publiczny link <code>.ics</code>. Kolor to pasek na liście wydarzeń.</p>
          <div class="config-list" id="cfgCalendars"></div>
        </section>

        <section class="config-section">
          <div class="config-section-head">
            <h3 class="config-section-title">Domeny</h3>
            <button type="button" class="config-btn ghost" id="cfgAddDomain">+ Dodaj</button>
          </div>
          <p class="config-hint">Wystarczy nazwa. URL, A i MX zostaw puste, jeśli nie chcesz pilnować rozjazdu.</p>
          <div class="config-list" id="cfgDomains"></div>
        </section>

        <section class="config-section">
          <h3 class="config-section-title">Limity · Claude Code i Grok</h3>
          <p class="config-hint">Zużycie nadal liczy konto. Tu włączasz Claude Code / Grok na kafelku i które produkty Groka widać.</p>
          <div class="config-checks">
            <label class="config-check">
              <input type="checkbox" id="cfgShowClaude">
              <span>Claude Code</span>
            </label>
            <label class="config-check">
              <input type="checkbox" id="cfgShowGrok">
              <span>Grok</span>
            </label>
          </div>
          <div class="config-checks" id="cfgGrokProducts">
            <label class="config-check">
              <input type="checkbox" class="cfg-grok-product" value="GrokBuild">
              <span>Build</span>
            </label>
            <label class="config-check">
              <input type="checkbox" class="cfg-grok-product" value="GrokChat">
              <span>Chat</span>
            </label>
            <label class="config-check">
              <input type="checkbox" class="cfg-grok-product" value="GrokImagine">
              <span>Imagine</span>
            </label>
          </div>
        </section>

        <section class="config-section">
          <div class="config-section-head">
            <h3 class="config-section-title">Pomodoro</h3>
            <button type="button" class="config-btn ghost" id="cfgAddPomo">+ Dodaj</button>
          </div>
          <p class="config-hint">Długości przycisków na zegarze, w minutach (1–180, max 6).</p>
          <div class="config-list config-pomo-list" id="cfgPomodoro"></div>
        </section>
      </div>
      <footer class="config-foot">
        <p class="config-status" id="cfgStatus" role="status"></p>
        <span class="config-ver"><?= htmlspecialchars($appVersion, ENT_QUOTES) ?></span>
        <div class="config-actions">
          <button type="button" class="config-btn ghost" id="configCancel">Anuluj</button>
          <button type="submit" class="config-btn primary" id="configSave">Zapisz</button>
        </div>
      </footer>
    </form>
  </div>
</div>

<div class="pomodoro-overlay" id="pomodoroOverlay" hidden>
  <div class="pomodoro-modal" role="dialog" aria-modal="true" aria-labelledby="pomodoroTime">
    <div class="pomodoro-dial">
      <svg viewBox="0 0 40 40" aria-hidden="true">
        <circle class="pomodoro-track" cx="20" cy="20" r="18.4"/>
        <circle class="pomodoro-ring" id="pomodoroRing" cx="20" cy="20" r="18.4"
          pathLength="100" transform="rotate(-90 20 20)"/>
      </svg>
      <div class="pomodoro-core">
        <img class="pomodoro-cup" src="assets/img/cup_of_coffee.gif" alt="" width="256" height="256">
        <div class="pomodoro-time" id="pomodoroTime">10:00</div>
      </div>
    </div>
    <button type="button" class="pomodoro-stop" id="pomodoroStop">Przerwij</button>
  </div>
</div>

<nav class="path-menu" id="pathMenu">
  <ul class="path-menu-list" id="pathMenuList">
    <li>
      <button type="button" class="fab path-item" id="configBtn" aria-label="Konfiguracja" title="Konfiguracja">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
      </button>
    </li>
    <li>
      <button type="button" class="fab path-item" id="themeBtn" aria-label="Jasny wygląd" title="Jasny wygląd">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="12" cy="12" r="4"/>
          <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
        </svg>
      </button>
    </li>
    <li>
      <button type="button" class="fab path-item refresh-btn" id="refreshBtn" aria-label="Odśwież widok" title="Odśwież">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 12a9 9 0 1 1-3-6.7"/>
          <polyline points="21 3 21 9 15 9"/>
        </svg>
      </button>
    </li>
  </ul>
  <button type="button" class="fab path-toggle" id="pathMenuToggle" aria-label="Menu" title="Menu" aria-expanded="false" aria-haspopup="true" aria-controls="pathMenuList">
    <span class="path-toggle-burger" aria-hidden="true"><span></span><span></span><span></span></span>
  </button>
</nav>


<script src="api/config-js.php?_=<?= $v ?>"></script>
<script src="assets/js/scripts.js?_=<?= $v ?>"></script>
<?php
// countdown przed events: fetchEvents wola renderCountdown po odpowiedzi.
$jsOrder = ['clock', 'calendar', 'weather', 'internet', 'countdown', 'events', 'domains', 'usage'];
foreach ($jsOrder as $name) {
    echo '<script src="tiles/' . htmlspecialchars($name, ENT_QUOTES) . '/' . htmlspecialchars($name, ENT_QUOTES) . '.js?_=' . $v . '"></script>' . "\n";
}
?>
</body>
</html>
