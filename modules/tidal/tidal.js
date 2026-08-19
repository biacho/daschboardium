const TIDAL_ENDPOINT = 'api/tidal.php';
const TIDAL_STREAM = 'api/tidal-stream.php';

const ICON_PREV = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg>';
const ICON_NEXT = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M16 6h2v12h-2zM6 18l8.5-6L6 6z"/></svg>';
const ICON_PLAY = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
const ICON_PAUSE = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>';

let tidalQueue = [];
let tidalIndex = 0;
let tidalPlaying = false;
let tidalStatus = '';
let tidalPreview = false;

function tidalMediaEl() {
  return document.getElementById('tidalMedia');
}

function tidalInitials(title) {
  return ((title || '?').trim()[0] || '?').toUpperCase();
}

function tidalFmt(sec) {
  const n = Math.max(0, Math.floor(Number(sec) || 0));
  return Math.floor(n / 60) + ':' + String(n % 60).padStart(2, '0');
}

function tidalArt(url, title, cls, phCls) {
  if (url) return `<img class="${cls}" src="${escapeAttr(url)}" alt="" width="78" height="78">`;
  return `<div class="${phCls}" aria-hidden="true">${escapeHtml(tidalInitials(title))}</div>`;
}

function tidalCurrent() {
  return tidalQueue[tidalIndex] || null;
}

function tidalPos() {
  const el = tidalMediaEl();
  return el ? el.currentTime : 0;
}

function tidalDur() {
  const el = tidalMediaEl();
  if (el && isFinite(el.duration) && el.duration > 0) return el.duration;
  const t = tidalCurrent();
  return t && t.duration ? t.duration : 0;
}

function renderTidal() {
  const main = document.getElementById('tidalMain');
  const label = document.getElementById('tidalLabel');
  if (!main) return;
  if (label) label.textContent = 'TIDAL · player';

  const now = tidalCurrent();
  const dur = tidalDur();
  const pos = tidalPos();
  const pct = dur > 0 ? Math.min(100, (pos / dur) * 100) : 0;
  const title = now ? now.title : 'Nic nie gra';
  let sub = now ? [now.artist, now.album].filter(Boolean).join(' · ') : (tidalStatus || 'Szukaj utworu');
  if (now && tidalPreview) sub = (sub ? sub + ' · ' : '') + 'podgląd 30 s';

  let html = `<form class="tidal-search" id="tidalSearch" autocomplete="off">`
    + `<input id="tidalQuery" type="search" maxlength="80" placeholder="Szukaj utworu…" enterkeyhint="search">`
    + `<button type="submit" class="tidal-btn tidal-btn-search" aria-label="Szukaj">`
    + `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>`
    + `</button></form>`;

  html += `<div class="tidal-now">`
    + tidalArt(now && now.image, title, 'tidal-now-art', 'tidal-now-ph')
    + `<div class="tidal-now-meta">`
    + `<div class="tidal-now-title">${escapeHtml(title)}</div>`
    + `<div class="tidal-now-sub" id="tidalSub">${escapeHtml(sub)}</div>`
    + `</div></div>`;

  html += `<div class="tidal-progress">`
    + `<span class="tidal-time" id="tidalPos">${escapeHtml(tidalFmt(pos))}</span>`
    + `<div class="tidal-bar" id="tidalBar" role="slider" aria-label="Postęp">`
    + `<div class="tidal-bar-track"><div class="tidal-bar-fill" id="tidalFill" style="width:${pct}%"></div></div>`
    + `</div>`
    + `<span class="tidal-time is-end" id="tidalDur">${escapeHtml(tidalFmt(dur))}</span>`
    + `</div>`;

  html += `<div class="tidal-controls">`
    + `<button type="button" class="tidal-btn" id="tidalPrev" aria-label="Poprzedni">${ICON_PREV}</button>`
    + `<button type="button" class="tidal-btn tidal-btn-play" id="tidalPlay" aria-label="${tidalPlaying ? 'Pauza' : 'Odtwarzaj'}">${tidalPlaying ? ICON_PAUSE : ICON_PLAY}</button>`
    + `<button type="button" class="tidal-btn" id="tidalNext" aria-label="Następny">${ICON_NEXT}</button>`
    + `</div>`;

  html += '<div class="tidal-queue" id="tidalQueue">';
  tidalQueue.forEach((t, i) => {
    const line = [t.artist, t.album].filter(Boolean).join(' · ');
    html += `<button type="button" class="tidal-row${i === tidalIndex ? ' is-active' : ''}" data-tidal-i="${i}">`
      + tidalArt(t.image, t.title, 'tidal-art', 'tidal-art-ph')
      + `<div class="tidal-txt"><div class="tidal-title">${escapeHtml(t.title)}</div>`
      + (line ? `<div class="tidal-sub">${escapeHtml(line)}</div>` : '')
      + `</div></button>`;
  });
  html += '</div>';
  main.innerHTML = html;
}

function tidalSetSub(text) {
  const el = document.getElementById('tidalSub');
  if (el) el.textContent = text;
}

function updateTidalProgress() {
  const posEl = document.getElementById('tidalPos');
  const fill = document.getElementById('tidalFill');
  const durEl = document.getElementById('tidalDur');
  const pos = tidalPos();
  const dur = tidalDur();
  if (posEl) posEl.textContent = tidalFmt(pos);
  if (durEl) durEl.textContent = tidalFmt(dur);
  if (fill && dur > 0) fill.style.width = Math.min(100, (pos / dur) * 100) + '%';
  const playBtn = document.getElementById('tidalPlay');
  if (playBtn) {
    playBtn.innerHTML = tidalPlaying ? ICON_PAUSE : ICON_PLAY;
    playBtn.setAttribute('aria-label', tidalPlaying ? 'Pauza' : 'Odtwarzaj');
  }
}

function tidalPlayAt(index, autoplay) {
  if (!tidalQueue.length) {
    tidalStatus = 'Najpierw wyszukaj utwór';
    renderTidal();
    return;
  }
  tidalIndex = (index + tidalQueue.length) % tidalQueue.length;
  const track = tidalCurrent();
  if (!track) return;
  const media = tidalMediaEl();
  if (!media) {
    tidalSetSub('Brak elementu audio');
    return;
  }
  tidalPreview = true;
  renderTidal();
  tidalSetSub('Ładuję…');
  media.pause();
  media.src = TIDAL_STREAM + '?id=' + encodeURIComponent(String(track.id)) + '&_=' + Date.now();
  media.load();
  if (!autoplay) return;
  const p = media.play();
  if (p && p.catch) {
    p.catch((err) => {
      tidalPlaying = false;
      tidalSetSub((err && err.message) ? err.message : 'Safari nie puściło strumienia');
      updateTidalProgress();
    });
  }
}

function tidalToggle() {
  const media = tidalMediaEl();
  if (!media) return;
  if (tidalPlaying) {
    media.pause();
    return;
  }
  if (media.src && !media.ended) {
    media.play().catch(() => tidalPlayAt(tidalIndex, true));
    return;
  }
  tidalPlayAt(tidalIndex, true);
}

function tidalSkip(dir) {
  if (!tidalQueue.length) return;
  if (dir < 0 && tidalPos() > 3) {
    const media = tidalMediaEl();
    if (media) media.currentTime = 0;
    return;
  }
  tidalPlayAt(tidalIndex + dir, true);
}

function bindTidalMedia() {
  const media = tidalMediaEl();
  if (!media || media.dataset.tidalBound === '1') return;
  media.dataset.tidalBound = '1';
  media.addEventListener('playing', () => { tidalPlaying = true; updateTidalProgress(); });
  media.addEventListener('pause', () => { tidalPlaying = false; updateTidalProgress(); });
  media.addEventListener('ended', () => { tidalPlaying = false; tidalSkip(1); });
  media.addEventListener('timeupdate', updateTidalProgress);
  media.addEventListener('durationchange', updateTidalProgress);
  media.addEventListener('error', () => {
    tidalPlaying = false;
    tidalSetSub('Błąd odtwarzania (HLS / podgląd 30 s)');
  });
}

function bindTidalUiOnce() {
  const panel = document.querySelector('.tidal-panel');
  if (!panel || panel.dataset.tidalBound === '1') return;
  panel.dataset.tidalBound = '1';
  bindTidalMedia();

  panel.addEventListener('pointerdown', (ev) => {
    const input = ev.target.closest('#tidalQuery');
    if (input) input.focus();
  });

  panel.addEventListener('submit', (ev) => {
    if (!ev.target.closest('#tidalSearch')) return;
    ev.preventDefault();
    const q = ((document.getElementById('tidalQuery') || {}).value || '').trim();
    if (!q) {
      tidalStatus = 'Wpisz tytuł albo wykonawcę';
      renderTidal();
      return;
    }
    fetchTidal(q);
  });

  const onPick = (ev) => {
    const play = ev.target.closest('#tidalPlay');
    const prev = ev.target.closest('#tidalPrev');
    const next = ev.target.closest('#tidalNext');
    const row = ev.target.closest('[data-tidal-i]');
    const bar = ev.target.closest('#tidalBar');
    if (play) { ev.preventDefault(); tidalToggle(); return; }
    if (prev) { ev.preventDefault(); tidalSkip(-1); return; }
    if (next) { ev.preventDefault(); tidalSkip(1); return; }
    if (row) {
      ev.preventDefault();
      const i = Number(row.getAttribute('data-tidal-i'));
      if (Number.isFinite(i)) tidalPlayAt(i, true);
      return;
    }
    if (bar) {
      const rect = bar.getBoundingClientRect();
      const x = (ev.clientX != null ? ev.clientX : (ev.changedTouches && ev.changedTouches[0].clientX)) - rect.left;
      const ratio = Math.max(0, Math.min(1, x / rect.width));
      const media = tidalMediaEl();
      const dur = tidalDur();
      if (media && dur > 0) media.currentTime = ratio * dur;
    }
  };
  panel.addEventListener('click', onPick);
  panel.addEventListener('touchend', (ev) => {
    if (ev.target.closest('#tidalQuery') || ev.target.closest('#tidalSearch')) return;
    if (!ev.target.closest('[data-tidal-i], #tidalPlay, #tidalPrev, #tidalNext')) return;
    ev.preventDefault();
    onPick(ev);
  }, { passive: false });
}

async function fetchTidal(query) {
  try {
    const url = query ? (TIDAL_ENDPOINT + '?q=' + encodeURIComponent(query)) : TIDAL_ENDPOINT;
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const d = await res.json();
    if (d.error && !(d.tracks && d.tracks.length)) {
      tidalQueue = [];
      tidalStatus = d.error;
      renderTidal();
      return;
    }
    tidalQueue = Array.isArray(d.tracks) ? d.tracks.filter((t) => t && t.id && t.title) : [];
    tidalStatus = tidalQueue.length ? '' : (d.error || 'Kolekcja jest pusta');
    if (tidalIndex >= tidalQueue.length) tidalIndex = 0;
    renderTidal();
  } catch (e) {
    tidalQueue = [];
    tidalStatus = 'Brak danych TIDAL';
    renderTidal();
  }
}

if (panelOn('tidal')) {
  bindTidalUiOnce();
  fetchTidal();
  setInterval(fetchTidal, 5 * 60 * 1000);
}
