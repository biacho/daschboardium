/* ============== KAFELKI (wlacz / wylacz) ============== */
const APP = window.APP_CONFIG || {};
const PANEL_IDS = ['internet', 'usage', 'domains', 'calendar', 'weather', 'clock', 'events', 'countdown'];

function panelOn(id) {
  const p = APP.panels;
  if (!p || typeof p !== 'object') return true;
  return p[id] !== false;
}

function syncPanelLayout() {
  const kiosk = document.querySelector('.kiosk');
  if (!kiosk) return;
  const left = !!kiosk.querySelector('.col-left > .panel:not([hidden])');
  const mid = !!kiosk.querySelector('.col-mid > .panel:not([hidden])');
  const right = !!kiosk.querySelector('.col-right > .panel:not([hidden])');
  kiosk.dataset.cols = [left && 'l', mid && 'm', right && 'r'].filter(Boolean).join('') || 'l';
}

function applyPanelVisibility() {
  document.querySelectorAll('[data-panel]').forEach((el) => {
    el.hidden = !panelOn(el.dataset.panel);
  });
  syncPanelLayout();
}
applyPanelVisibility();

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

const WEATHER_LAT  = APP.weatherLat  ?? 51.1079;
const WEATHER_LON  = APP.weatherLon  ?? 17.0385;
const WEATHER_CITY = APP.weatherCity ?? 'Wrocław';
const SHOW_CLAUDE  = APP.showClaude !== false;
const SHOW_GROK    = APP.showGrok !== false;
const GROK_ALLOWED = ['GrokBuild', 'GrokChat', 'GrokImagine'];
const GROK_PRODUCTS = (Array.isArray(APP.grokProducts) ? APP.grokProducts : GROK_ALLOWED)
  .filter((name, i, arr) => GROK_ALLOWED.includes(name) && arr.indexOf(name) === i);
const POMODORO_PRESETS = (function () {
  const out = [];
  (Array.isArray(APP.pomodoro) ? APP.pomodoro : [10, 15, 20]).forEach((n) => {
    const min = Number(n);
    if (min >= 1 && min <= 180 && !out.includes(min)) out.push(min);
  });
  out.sort((a, b) => a - b);
  return out.slice(0, 6);
})();








/* ============== MOTYW JASNY / CIEMNY ============== */
// Wybor siedzi w localStorage, zeby ↻ i standalone iOS nie wracaly do dark.
// data-theme ustawia tez skrypt w <head> (przed CSS), tu tylko przycisk i pasek iOS.
const THEME_KEY = 'dashboard-theme';

function currentTheme() {
  return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
}

const THEME_ICON_SUN = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>';
const THEME_ICON_MOON = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/></svg>';

function applyTheme(theme) {
  const next = theme === 'light' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  try { localStorage.setItem(THEME_KEY, next); } catch (e) {}

  const meta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
  if (meta) meta.setAttribute('content', next === 'light' ? 'default' : 'black-translucent');

  const btn = document.getElementById('themeBtn');
  if (!btn) return;
  if (next === 'light') {
    btn.innerHTML = THEME_ICON_MOON;
    btn.setAttribute('aria-label', 'Ciemny wygląd');
    btn.title = 'Ciemny wygląd';
  } else {
    btn.innerHTML = THEME_ICON_SUN;
    btn.setAttribute('aria-label', 'Jasny wygląd');
    btn.title = 'Jasny wygląd';
  }
}

const themeBtn = document.getElementById('themeBtn');
if (themeBtn) {
  applyTheme(currentTheme());
  themeBtn.addEventListener('click', () => {
    applyTheme(currentTheme() === 'light' ? 'dark' : 'light');
  });
}


/* ============== KONFIGURACJA ============== */
// Modal edytuje config.php przez get-config.php / save-config.php.
// URL-e iCal sa sekretami - endpointy sa tylko w LAN (nginx allow/deny).
const CONFIG_GET = 'get-config.php';
const CONFIG_SAVE = 'save-config.php';

function escapeAttr(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');
}

function cfgColor(value) {
  const c = String(value || '').trim().toLowerCase();
  if (/^#[0-9a-f]{6}$/.test(c)) return c;
  if (/^#[0-9a-f]{3}$/.test(c)) {
    return '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
  }
  return '#4fd1c5';
}

function closePathMenuNow() {
  const menu = document.getElementById('pathMenu');
  const toggle = document.getElementById('pathMenuToggle');
  if (menu && menu.classList.contains('open') && toggle) toggle.click();
}

function setConfigStatus(text, kind) {
  const el = document.getElementById('cfgStatus');
  if (!el) return;
  el.textContent = text || '';
  el.classList.toggle('error', kind === 'error');
  el.classList.toggle('ok', kind === 'ok');
}

const CFG_ICON_X = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

function calendarRow(cal) {
  const el = document.createElement('div');
  el.className = 'config-row config-cal';
  el.innerHTML =
    '<div class="config-row-main">'
    + `<input class="cfg-cal-name" type="text" maxlength="80" placeholder="Nazwa" value="${escapeAttr(cal.name || '')}">`
    + `<input class="cfg-cal-color" type="color" value="${escapeAttr(cfgColor(cal.color))}" title="Kolor paska" aria-label="Kolor paska">`
    + `<button type="button" class="config-row-remove" data-remove="cal" aria-label="Usuń kalendarz">${CFG_ICON_X}</button>`
    + '</div>'
    + `<input class="cfg-cal-url" type="url" placeholder="https://…ics" value="${escapeAttr(cal.url || '')}">`;
  return el;
}

function pomoRow(min) {
  const el = document.createElement('div');
  el.className = 'config-row config-pomo';
  const val = min != null && min !== '' ? escapeAttr(String(min)) : '';
  el.innerHTML =
    '<div class="config-row-main">'
    + `<input class="cfg-pomo-min" type="number" min="1" max="180" step="1" inputmode="numeric" placeholder="25" value="${val}">`
    + '<span class="config-suffix">min</span>'
    + `<button type="button" class="config-row-remove" data-remove="pomo" aria-label="Usuń preset">${CFG_ICON_X}</button>`
    + '</div>';
  return el;
}

function syncGrokProductState() {
  const on = !!(document.getElementById('cfgShowGrok') || {}).checked;
  const wrap = document.getElementById('cfgGrokProducts');
  if (wrap) wrap.classList.toggle('dim', !on);
  document.querySelectorAll('.cfg-grok-product').forEach((el) => {
    el.disabled = !on;
  });
}

function domainRow(d) {
  const el = document.createElement('div');
  el.className = 'config-row config-domain';
  el.innerHTML =
    '<div class="config-row-main">'
    + `<input class="cfg-dom-name" type="text" maxlength="80" placeholder="example.pl" value="${escapeAttr(d.name || '')}" autocapitalize="off" spellcheck="false">`
    + `<button type="button" class="config-row-remove" data-remove="dom" aria-label="Usuń domenę">${CFG_ICON_X}</button>`
    + '</div>'
    + `<input class="cfg-dom-url" type="url" placeholder="URL checku (opcjonalnie)" value="${escapeAttr(d.url || '')}">`
    + '<div class="config-row-extra">'
    + `<input class="cfg-dom-a" type="text" maxlength="45" placeholder="oczekiwane A" value="${escapeAttr(d.expect_a || '')}" autocapitalize="off" spellcheck="false">`
    + `<input class="cfg-dom-mx" type="text" maxlength="120" placeholder="oczekiwane MX" value="${escapeAttr(d.expect_mx || '')}" autocapitalize="off" spellcheck="false">`
    + '</div>';
  return el;
}

function fillConfigForm(data) {
  const city = document.getElementById('cfgWeatherCity');
  const lat = document.getElementById('cfgWeatherLat');
  const lon = document.getElementById('cfgWeatherLon');
  if (city) city.value = (data.weather && data.weather.city) || '';
  if (lat) lat.value = data.weather && data.weather.lat != null ? String(data.weather.lat) : '';
  if (lon) lon.value = data.weather && data.weather.lon != null ? String(data.weather.lon) : '';

  const cals = document.getElementById('cfgCalendars');
  if (cals) {
    cals.innerHTML = '';
    const list = (data.calendars && data.calendars.length) ? data.calendars : [{}];
    list.forEach((cal) => cals.appendChild(calendarRow(cal)));
  }

  const doms = document.getElementById('cfgDomains');
  if (doms) {
    doms.innerHTML = '';
    const list = (data.domains && data.domains.length) ? data.domains : [{}];
    list.forEach((d) => doms.appendChild(domainRow(d)));
  }

  const limits = data.limits || {};
  const claudeEl = document.getElementById('cfgShowClaude');
  const grokEl = document.getElementById('cfgShowGrok');
  if (claudeEl) claudeEl.checked = limits.claude !== false;
  if (grokEl) grokEl.checked = limits.grok !== false;
  const selected = Array.isArray(limits.grokProducts) ? limits.grokProducts : GROK_ALLOWED;
  document.querySelectorAll('.cfg-grok-product').forEach((el) => {
    el.checked = selected.includes(el.value);
  });
  syncGrokProductState();

  const panels = data.panels || {};
  document.querySelectorAll('.cfg-panel').forEach((el) => {
    el.checked = panels[el.value] !== false;
  });

  const pomos = document.getElementById('cfgPomodoro');
  if (pomos) {
    pomos.innerHTML = '';
    const list = (data.pomodoro && data.pomodoro.length) ? data.pomodoro : [10, 15, 20];
    list.forEach((min) => pomos.appendChild(pomoRow(min)));
  }
}

function collectConfigForm() {
  const cityEl = document.getElementById('cfgWeatherCity');
  const latEl = document.getElementById('cfgWeatherLat');
  const lonEl = document.getElementById('cfgWeatherLon');
  const payload = {
    weather: {
      city: cityEl ? cityEl.value.trim() : '',
      lat: latEl ? latEl.value.trim() : '',
      lon: lonEl ? lonEl.value.trim() : '',
    },
    calendars: [],
    domains: [],
    limits: {
      claude: !!(document.getElementById('cfgShowClaude') || {}).checked,
      grok: !!(document.getElementById('cfgShowGrok') || {}).checked,
      grokProducts: Array.from(document.querySelectorAll('.cfg-grok-product:checked')).map((el) => el.value),
    },
    pomodoro: [],
    panels: {},
  };
  document.querySelectorAll('.cfg-panel').forEach((el) => {
    payload.panels[el.value] = !!el.checked;
  });

  document.querySelectorAll('#cfgCalendars .config-cal').forEach((row) => {
    payload.calendars.push({
      name: (row.querySelector('.cfg-cal-name') || {}).value || '',
      url: (row.querySelector('.cfg-cal-url') || {}).value || '',
      color: (row.querySelector('.cfg-cal-color') || {}).value || '',
    });
  });

  document.querySelectorAll('#cfgDomains .config-domain').forEach((row) => {
    payload.domains.push({
      name: (row.querySelector('.cfg-dom-name') || {}).value || '',
      url: (row.querySelector('.cfg-dom-url') || {}).value || '',
      expect_a: (row.querySelector('.cfg-dom-a') || {}).value || '',
      expect_mx: (row.querySelector('.cfg-dom-mx') || {}).value || '',
    });
  });

  document.querySelectorAll('#cfgPomodoro .cfg-pomo-min').forEach((input) => {
    if (input.value !== '') payload.pomodoro.push(input.value);
  });

  return payload;
}

async function loadConfigForm() {
  setConfigStatus('Wczytywanie...', null);
  const saveBtn = document.getElementById('configSave');
  if (saveBtn) saveBtn.disabled = true;
  try {
    const res = await fetch(CONFIG_GET, { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    fillConfigForm(data);
    setConfigStatus('', null);
    if (saveBtn) saveBtn.disabled = false;
  } catch (e) {
    fillConfigForm({ weather: {}, calendars: [], domains: [], limits: {}, pomodoro: [] });
    setConfigStatus('Nie udało się wczytać konfiguracji', 'error');
  }
}

const IS_FIRST_RUN = APP.setupComplete === false;

function applySetupMode(on) {
  const overlay = document.getElementById('configOverlay');
  const title = document.getElementById('configTitle');
  if (overlay) overlay.classList.toggle('is-setup', on);
  if (title) title.textContent = on ? 'Pierwsze uruchomienie' : 'Konfiguracja';
}

function openConfig() {
  const overlay = document.getElementById('configOverlay');
  if (!overlay) return;
  closePathMenuNow();
  applySetupMode(IS_FIRST_RUN);
  overlay.hidden = false;
  loadConfigForm();
  const city = document.getElementById('cfgWeatherCity');
  if (city) setTimeout(() => city.focus(), 50);
}

function closeConfig() {
  if (IS_FIRST_RUN) return;
  const overlay = document.getElementById('configOverlay');
  if (overlay) overlay.hidden = true;
  setConfigStatus('', null);
}

async function lookupWeatherCity() {
  const cityEl = document.getElementById('cfgWeatherCity');
  const latEl = document.getElementById('cfgWeatherLat');
  const lonEl = document.getElementById('cfgWeatherLon');
  const name = cityEl ? cityEl.value.trim() : '';
  if (!name) {
    setConfigStatus('Wpisz miasto do wyszukania', 'error');
    return;
  }
  setConfigStatus('Szukam współrzędnych...', null);
  try {
    const url = 'https://geocoding-api.open-meteo.com/v1/search?name='
      + encodeURIComponent(name) + '&count=1&language=pl&format=json';
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    const hit = data.results && data.results[0];
    if (!hit) {
      setConfigStatus('Nie znaleziono miasta', 'error');
      return;
    }
    if (cityEl && hit.name) cityEl.value = hit.name;
    if (latEl) latEl.value = String(hit.latitude);
    if (lonEl) lonEl.value = String(hit.longitude);
    const extra = [hit.admin1, hit.country].filter(Boolean).join(', ');
    setConfigStatus(extra ? 'Ustawiono: ' + extra : 'Ustawiono współrzędne', 'ok');
  } catch (e) {
    setConfigStatus('Nie udało się wyszukać miasta', 'error');
  }
}

async function saveConfig(ev) {
  if (ev) ev.preventDefault();
  const saveBtn = document.getElementById('configSave');
  if (saveBtn) saveBtn.disabled = true;
  setConfigStatus('Zapisuję...', null);
  try {
    const payload = collectConfigForm();
    if (!Object.values(payload.panels).some(Boolean)) {
      throw new Error('Zostaw przynajmniej jeden kafelek');
    }
    const res = await fetch(CONFIG_SAVE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      cache: 'no-store',
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.error) throw new Error(data.error || ('HTTP ' + res.status));
    APP.panels = payload.panels;
    applyPanelVisibility();
    closeConfig();
    location.replace(location.pathname + '?_=' + Date.now());
  } catch (e) {
    setConfigStatus(e.message || 'Nie udało się zapisać', 'error');
    if (saveBtn) saveBtn.disabled = false;
  }
}

{
  const overlay = document.getElementById('configOverlay');
  const btn = document.getElementById('configBtn');
  const closeBtn = document.getElementById('configClose');
  const cancelBtn = document.getElementById('configCancel');
  const form = document.getElementById('configForm');
  const addCal = document.getElementById('cfgAddCal');
  const addDom = document.getElementById('cfgAddDomain');
  const addPomo = document.getElementById('cfgAddPomo');
  const lookup = document.getElementById('cfgWeatherLookup');
  const cals = document.getElementById('cfgCalendars');
  const doms = document.getElementById('cfgDomains');
  const pomos = document.getElementById('cfgPomodoro');
  const showGrok = document.getElementById('cfgShowGrok');

  if (btn) btn.addEventListener('click', openConfig);
  if (closeBtn) closeBtn.addEventListener('click', closeConfig);
  if (cancelBtn) cancelBtn.addEventListener('click', closeConfig);
  if (form) form.addEventListener('submit', saveConfig);
  if (lookup) lookup.addEventListener('click', lookupWeatherCity);
  if (addCal && cals) {
    addCal.addEventListener('click', () => {
      cals.appendChild(calendarRow({}));
      const input = cals.lastElementChild && cals.lastElementChild.querySelector('.cfg-cal-name');
      if (input) input.focus();
    });
  }
  if (addDom && doms) {
    addDom.addEventListener('click', () => {
      doms.appendChild(domainRow({}));
      const input = doms.lastElementChild && doms.lastElementChild.querySelector('.cfg-dom-name');
      if (input) input.focus();
    });
  }
  if (addPomo && pomos) {
    addPomo.addEventListener('click', () => {
      if (pomos.children.length >= 6) {
        setConfigStatus('Maksymalnie 6 presetów pomodoro', 'error');
        return;
      }
      pomos.appendChild(pomoRow(''));
      const input = pomos.lastElementChild && pomos.lastElementChild.querySelector('.cfg-pomo-min');
      if (input) input.focus();
    });
  }
  if (showGrok) showGrok.addEventListener('change', syncGrokProductState);
  if (cals) {
    cals.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-remove="cal"]');
      if (!rm) return;
      const row = rm.closest('.config-cal');
      if (row) row.remove();
      if (!cals.children.length) cals.appendChild(calendarRow({}));
    });
  }
  if (doms) {
    doms.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-remove="dom"]');
      if (!rm) return;
      const row = rm.closest('.config-domain');
      if (row) row.remove();
      if (!doms.children.length) doms.appendChild(domainRow({}));
    });
  }
  if (pomos) {
    pomos.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-remove="pomo"]');
      if (!rm) return;
      const row = rm.closest('.config-pomo');
      if (row) row.remove();
      if (!pomos.children.length) pomos.appendChild(pomoRow(25));
    });
  }
  if (overlay) {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeConfig();
    });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay && !overlay.hidden) {
      e.stopPropagation();
      closeConfig();
    }
  });
  try {
    if (IS_FIRST_RUN || new URLSearchParams(location.search).get('config') === '1') openConfig();
  } catch (e) {
    if (IS_FIRST_RUN) openConfig();
  }
}

/* ============== PRZYCISK ODSWIEZANIA ============== */
// Twardy reload: w trybie standalone na iOS (dodane do ekranu glownego) zwykly
// reload bywa serwowany ze starego cache, wiec doklejamy unikatowy query, ktory
// wymusza swieze pobranie index.html (a z nim inline CSS/JS). location.pathname
// (bez starego query) -> param sie nie kumuluje; replace() -> bez smiecenia historii.
const refreshBtn = document.getElementById('refreshBtn');
if (refreshBtn) {
  refreshBtn.addEventListener('click', () => {
    refreshBtn.classList.add('spinning');
    location.replace(location.pathname + '?_=' + Date.now());
  });
}

/* ============== MENU WACHLARZOWE ============== */
// Opcje wylecaja lukiem z prawego dolnego rogu (jak Path, lustrzanie).
// Nowa opcja = <li><button class="fab path-item"> w index.html + handler;
// katy licza sie same z liczby .path-item.
(function initPathMenu() {
  const menu = document.getElementById('pathMenu');
  const toggle = document.getElementById('pathMenuToggle');
  if (!menu || !toggle) return;

  const RADIUS = 128;

  function items() {
    return Array.from(menu.querySelectorAll('.path-item'));
  }

  // Szerszy luk niz dawniej: przy 3 przyciskach maja ~32 px przerwy, nie stykaja sie.
  function arcFor(n) {
    if (n <= 1) return { start: 45, end: 45 };
    if (n === 2) return { start: 16, end: 78 };
    if (n === 3) return { start: 8, end: 84 };
    return { start: 4, end: 88 };
  }

  function layout() {
    const els = items();
    const n = els.length;
    const { start, end } = arcFor(n);
    els.forEach((el, i) => {
      const t = n === 1 ? 0.5 : i / (n - 1);
      const deg = start + (end - start) * t;
      const rad = deg * Math.PI / 180;
      el.style.setProperty('--tx', Math.round(-Math.cos(rad) * RADIUS) + 'px');
      el.style.setProperty('--ty', Math.round(-Math.sin(rad) * RADIUS) + 'px');
    });
  }

  function isOpen() {
    return menu.classList.contains('open');
  }

  function slotOf(el) {
    return {
      x: parseFloat(el.style.getPropertyValue('--tx')) || 0,
      y: parseFloat(el.style.getPropertyValue('--ty')) || 0,
    };
  }

  function at(x, y, p, s) {
    return 'translate3d(' + Math.round(x * p) + 'px,' + Math.round(y * p) + 'px,0) scale(' + s + ')';
  }

  function reduceMotion() {
    try {
      return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
      return false;
    }
  }

  function fly(el, open, fromTransform, delay) {
    const { x, y } = slotOf(el);
    const start = (fromTransform && fromTransform !== 'none')
      ? fromTransform
      : (open ? at(x, y, 0, 0.15) : at(x, y, 1, 1));

    el.getAnimations().forEach((a) => a.cancel());

    if (reduceMotion() || !el.animate) {
      el.style.transform = open ? at(x, y, 1, 1) : at(x, y, 0, 0);
      return;
    }

    el.style.transform = '';
    const frames = open
      ? [
          { transform: start, offset: 0 },
          { transform: at(x, y, 0.1, 1), offset: 0.18 },
          { transform: at(x, y, 1, 1), offset: 1 },
        ]
      : [
          { transform: start, offset: 0 },
          { transform: at(x, y, 0.18, 1), offset: 0.78 },
          { transform: at(x, y, 0, 0), offset: 1 },
        ];

    el.animate(frames, {
      duration: open ? 560 : 520,
      delay: delay,
      easing: 'cubic-bezier(0.25, 0.8, 0.25, 1)',
      fill: 'forwards',
    });
  }

  function setOpen(open) {
    const els = items();
    const n = els.length;
    const starts = els.map((el) => getComputedStyle(el).transform);
    els.forEach((el) => el.getAnimations().forEach((a) => a.cancel()));
    menu.classList.toggle('open', open);
    els.forEach((el, i) => {
      const delay = open ? i * 32 : (n - 1 - i) * 28;
      fly(el, open, starts[i], delay);
    });
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Zamknij menu' : 'Menu');
    toggle.title = open ? 'Zamknij menu' : 'Menu';
  }

  layout();

  try {
    if (new URLSearchParams(location.search).get('menu') === '1') {
      if (toggle) toggle.style.transition = 'none';
      menu.querySelectorAll('.path-toggle-burger span').forEach((el) => {
        el.style.transition = 'none';
      });
      menu.classList.add('open');
      items().forEach((el) => {
        const { x, y } = slotOf(el);
        el.getAnimations().forEach((a) => a.cancel());
        el.style.transform = at(x, y, 1, 1);
      });
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Zamknij menu');
      toggle.title = 'Zamknij menu';
    }
  } catch (e) {}

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    setOpen(!isOpen());
  });

  document.addEventListener('pointerdown', (e) => {
    if (!isOpen()) return;
    if (menu.contains(e.target)) return;
    setOpen(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) {
      setOpen(false);
    }
  });
})();

/* ============== BOOT: CRT ============== */
(function bootLoader() {
  const loader = document.getElementById('bootLoader');
  const bar = document.getElementById('bootLoaderBar');
  if (!loader) return;

  let revealed = false;
  let reduce = false;
  try {
    reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  } catch (e) {}

  function finish() {
    loader.classList.add('is-done');
    loader.setAttribute('aria-hidden', 'true');
  }

  function reveal() {
    if (revealed) return;
    revealed = true;
    if (bar) bar.style.transform = 'scaleX(1)';
    if (reduce) {
      finish();
      return;
    }
    setTimeout(() => loader.classList.add('is-line'), 220);
    setTimeout(() => loader.classList.add('is-open'), 900);
    setTimeout(finish, 1800);
  }

  try {
    const q = new URLSearchParams(location.search).get('boot');
    if (q === '0') {
      loader.style.transition = 'none';
      finish();
      return;
    }
    if (q === '1' || q === 'hold') {
      if (bar) bar.style.transform = 'scaleX(0.45)';
      return;
    }
    if (q === 'line') {
      if (bar) bar.style.transform = 'scaleX(1)';
      loader.classList.add('is-line');
      return;
    }
  } catch (e) {}

  requestAnimationFrame(() => {
    if (bar) bar.style.transform = 'scaleX(1)';
  });

  const start = () => setTimeout(reveal, 280);
  if (document.fonts && document.fonts.ready) {
    Promise.race([
      document.fonts.ready,
      new Promise((r) => setTimeout(r, 1200)),
    ]).then(start);
  } else {
    start();
  }
  setTimeout(reveal, 8000);
})();
