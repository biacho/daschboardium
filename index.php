<?php
require_once __DIR__ . '/lib/load-config.php';
$dashboardConfig = dashboard_config();
$needsInstall = ($dashboardConfig === null);
$appVersion = dashboard_version();
$v = (string) time();
$modules = ['internet', 'usage', 'domains', 'calendar', 'weather', 'clock', 'events', 'countdown', 'lastfm', 'tidal'];
$layout = dashboard_normalize_layout(is_array($dashboardConfig) ? ($dashboardConfig['LAYOUT'] ?? null) : null);
function module($name) {
    $path = __DIR__ . '/modules/' . $name . '/' . $name . '.html';
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
<?php foreach ($modules as $name): ?>
<link rel="stylesheet" href="modules/<?= htmlspecialchars($name, ENT_QUOTES) ?>/<?= htmlspecialchars($name, ENT_QUOTES) ?>.css?_=<?= $v ?>">
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
<?php module('internet'); ?>
  <div class="col-left" data-col="left">
<?php foreach ($layout['left'] as $name) module($name); ?>
  </div>
  <div class="col-mid" data-col="mid">
<?php foreach ($layout['mid'] as $name) module($name); ?>
  </div>
  <div class="col-right" data-col="right">
<?php foreach ($layout['right'] as $name) module($name); ?>
  </div>
</div>

<div class="config-overlay" id="configOverlay" hidden>
  <div class="config-modal" role="dialog" aria-modal="true" aria-labelledby="configTitle">
    <header class="config-head">
      <h2 class="config-title" id="configTitle">Ustawienia</h2>
      <button type="button" class="config-close" id="configClose" aria-label="Zamknij">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>
    <form class="config-form" id="configForm" autocomplete="off">
      <div class="config-split">
        <nav class="config-nav" id="configNav" role="tablist" aria-label="Moduły">
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-internet" data-cfg-pane="internet" aria-controls="cfgPane-internet" aria-selected="false" tabindex="-1">Internet</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-clock" data-cfg-pane="clock" aria-controls="cfgPane-clock" aria-selected="false" tabindex="-1">Zegar</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-calendar" data-cfg-pane="calendar" aria-controls="cfgPane-calendar" aria-selected="false" tabindex="-1">Kalendarz</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-events" data-cfg-pane="events" aria-controls="cfgPane-events" aria-selected="false" tabindex="-1">Wydarzenia</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-countdown" data-cfg-pane="countdown" aria-controls="cfgPane-countdown" aria-selected="false" tabindex="-1">Odliczanie</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-usage" data-cfg-pane="usage" aria-controls="cfgPane-usage" aria-selected="false" tabindex="-1">Limity</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-domains" data-cfg-pane="domains" aria-controls="cfgPane-domains" aria-selected="false" tabindex="-1">Domeny</button>
          <button type="button" class="config-nav-item is-active" role="tab" id="cfgTab-weather" data-cfg-pane="weather" aria-controls="cfgPane-weather" aria-selected="true" tabindex="0">Pogoda</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-lastfm" data-cfg-pane="lastfm" aria-controls="cfgPane-lastfm" aria-selected="false" tabindex="-1">Last.fm</button>
          <button type="button" class="config-nav-item" role="tab" id="cfgTab-tidal" data-cfg-pane="tidal" aria-controls="cfgPane-tidal" aria-selected="false" tabindex="-1">TIDAL</button>
        </nav>

        <div class="config-body">
          <section class="config-pane" id="cfgPane-internet" data-cfg-pane="internet" role="tabpanel" aria-labelledby="cfgTab-internet" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Internet</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="internet" checked><span>Aktywny</span></label>
            </div>
            <p class="config-hint">Belka u góry kiosku. Sąsiedzi biorą miejsce wyłączonego modułu. Kolejność zmienisz w menu → Edytuj układ.</p>
          </section>

          <section class="config-pane" id="cfgPane-clock" data-cfg-pane="clock" role="tabpanel" aria-labelledby="cfgTab-clock" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Zegar</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="clock" checked><span>Aktywny</span></label>
            </div>
            <div class="config-section-head">
              <h3 class="config-section-title">Pomodoro</h3>
              <button type="button" class="config-btn ghost" id="cfgAddPomo">+ Dodaj</button>
            </div>
            <p class="config-hint">Długości przycisków na zegarze, w minutach (1–180, max 6).</p>
            <div class="config-list config-pomo-list" id="cfgPomodoro"></div>
          </section>

          <section class="config-pane" id="cfgPane-calendar" data-cfg-pane="calendar" role="tabpanel" aria-labelledby="cfgTab-calendar" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Kalendarz</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="calendar" checked><span>Aktywny</span></label>
            </div>
            <div class="config-section-head">
              <h3 class="config-section-title">Źródła iCal</h3>
              <button type="button" class="config-btn ghost" id="cfgAddCal">+ Dodaj</button>
            </div>
            <p class="config-hint">Publiczny link <code>.ics</code>. Kolor to pasek na liście wydarzeń. Te same źródła karmią Wydarzenia i Odliczanie.</p>
            <div class="config-list" id="cfgCalendars"></div>
          </section>

          <section class="config-pane" id="cfgPane-events" data-cfg-pane="events" role="tabpanel" aria-labelledby="cfgTab-events" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Wydarzenia</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="events" checked><span>Aktywny</span></label>
            </div>
            <p class="config-hint">Lista dziś i jutro z kalendarzy iCal. Źródła edytujesz w Kalendarzu.</p>
            <button type="button" class="config-btn ghost" data-cfg-goto="calendar">Otwórz Kalendarz</button>
          </section>

          <section class="config-pane" id="cfgPane-countdown" data-cfg-pane="countdown" role="tabpanel" aria-labelledby="cfgTab-countdown" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Odliczanie</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="countdown" checked><span>Aktywny</span></label>
            </div>
            <p class="config-hint">Do najbliższego wydarzenia z tych samych kalendarzy iCal.</p>
            <button type="button" class="config-btn ghost" data-cfg-goto="calendar">Otwórz Kalendarz</button>
          </section>

          <section class="config-pane" id="cfgPane-usage" data-cfg-pane="usage" role="tabpanel" aria-labelledby="cfgTab-usage" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Limity</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="usage" checked><span>Aktywny</span></label>
            </div>
            <p class="config-hint">Zużycie nadal liczy konto. Tu włączasz Claude Code / Grok na module i które produkty Groka widać.</p>
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

          <section class="config-pane" id="cfgPane-domains" data-cfg-pane="domains" role="tabpanel" aria-labelledby="cfgTab-domains" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Domeny</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="domains" checked><span>Aktywny</span></label>
            </div>
            <div class="config-section-head">
              <h3 class="config-section-title">Lista</h3>
              <button type="button" class="config-btn ghost" id="cfgAddDomain">+ Dodaj</button>
            </div>
            <p class="config-hint">Wystarczy nazwa. URL, A i MX zostaw puste, jeśli nie chcesz pilnować rozjazdu.</p>
            <div class="config-list" id="cfgDomains"></div>
          </section>

          <section class="config-pane" id="cfgPane-weather" data-cfg-pane="weather" role="tabpanel" aria-labelledby="cfgTab-weather">
            <div class="config-section-head">
              <h3 class="config-section-title">Pogoda</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="weather" checked><span>Aktywny</span></label>
            </div>
            <p class="config-hint">Miasto to podpis modułu. Współrzędne możesz wyszukać po nazwie.</p>
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

          <section class="config-pane" id="cfgPane-lastfm" data-cfg-pane="lastfm" role="tabpanel" aria-labelledby="cfgTab-lastfm" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">Last.fm</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="lastfm"><span>Aktywny</span></label>
            </div>
            <p class="config-hint">Klucz z <a href="https://www.last.fm/api/account/create" target="_blank" rel="noopener">last.fm/api</a>. W Apple Music / TIDAL włącz scrobbling — kafelek pokaże, co leci. Obserwowany: lista osób, które followujesz na Last.fm.</p>
            <div class="config-grid">
              <label class="config-field">
                <span>Użytkownik</span>
                <input id="cfgLastfmUser" type="text" maxlength="50" placeholder="nick" autocapitalize="off" spellcheck="false">
              </label>
              <label class="config-field">
                <span>Klucz API</span>
                <input id="cfgLastfmKey" type="password" maxlength="64" placeholder="32 znaki" autocomplete="off" spellcheck="false">
              </label>
              <label class="config-field config-field-wide">
                <span>Obserwowany</span>
                <div class="config-city-row">
                  <select id="cfgLastfmFriend">
                    <option value="">Nikt</option>
                  </select>
                  <button type="button" class="config-btn ghost" id="cfgLastfmFriendsRefresh">Odśwież</button>
                </div>
              </label>
            </div>
          </section>

          <section class="config-pane" id="cfgPane-tidal" data-cfg-pane="tidal" role="tabpanel" aria-labelledby="cfgTab-tidal" hidden>
            <div class="config-section-head">
              <h3 class="config-section-title">TIDAL</h3>
              <label class="config-on"><input type="checkbox" class="cfg-panel" value="tidal"><span>Aktywny</span></label>
            </div>
            <div class="config-city-row">
              <button type="button" class="config-btn ghost" id="cfgTidalConnect">Połącz</button>
              <button type="button" class="config-btn ghost" id="cfgTidalDisconnect">Rozłącz</button>
            </div>
            <p class="config-hint">Aplikacja z <a href="https://developer.tidal.com/" target="_blank" rel="noopener">developer.tidal.com</a>. Kafelek to odtwarzacz na iPadzie (play / pauza / next) — nie steruje telefonem. Redirect URI wklej 1:1. Po zmianie uprawnień kliknij <strong>Połącz</strong> jeszcze raz. OAuth z iPada na HTTP w LAN zwykle nie przejdzie — połącz z maszyny serwera przez <code>127.0.0.1</code>.</p>
            <p class="config-hint" id="cfgTidalStatus">Nie połączono.</p>
            <div class="config-grid">
              <label class="config-field">
                <span>Client ID</span>
                <input id="cfgTidalId" type="text" maxlength="80" placeholder="client id" autocapitalize="off" spellcheck="false" autocomplete="off">
              </label>
              <label class="config-field">
                <span>Client secret</span>
                <input id="cfgTidalSecret" type="password" maxlength="120" placeholder="secret" autocomplete="off" spellcheck="false">
              </label>
              <label class="config-field">
                <span>Kraj</span>
                <input id="cfgTidalCountry" type="text" maxlength="2" placeholder="PL" autocapitalize="characters" spellcheck="false">
              </label>
              <label class="config-field config-field-wide">
                <span>Redirect URI</span>
                <input id="cfgTidalRedirect" type="text" readonly>
              </label>
            </div>
          </section>
        </div>
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

<div class="layout-bar" id="layoutBar" hidden>
  <p>Przesuń kafelki · układ zapisze się sam</p>
  <button type="button" class="config-btn primary" id="layoutDone">Gotowe</button>
</div>

<nav class="path-menu" id="pathMenu">
  <ul class="path-menu-list" id="pathMenuList">
    <li>
      <button type="button" class="fab path-item" id="layoutBtn" aria-label="Edytuj układ" title="Edytuj układ">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="3" width="7" height="7" rx="1.5"/>
          <rect x="14" y="3" width="7" height="7" rx="1.5"/>
          <rect x="3" y="14" width="7" height="7" rx="1.5"/>
          <rect x="14" y="14" width="7" height="7" rx="1.5"/>
        </svg>
      </button>
    </li>
    <li>
      <button type="button" class="fab path-item" id="configBtn" aria-label="Ustawienia" title="Ustawienia">
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
<script src="assets/js/layout.js?_=<?= $v ?>"></script>
<?php
// countdown przed events: fetchEvents wola renderCountdown po odpowiedzi.
$jsOrder = ['clock', 'calendar', 'weather', 'internet', 'countdown', 'events', 'domains', 'usage', 'lastfm', 'tidal'];
foreach ($jsOrder as $name) {
    echo '<script src="modules/' . htmlspecialchars($name, ENT_QUOTES) . '/' . htmlspecialchars($name, ENT_QUOTES) . '.js?_=' . $v . '"></script>' . "\n";
}
?>
</body>
</html>
