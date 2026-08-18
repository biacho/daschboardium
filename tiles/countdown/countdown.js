/* ============== ODLICZANIE DO NAJBLIZSZEGO WYDARZENIA ============== */
// Bierze te same eventy co kafelek listy. Preferuje wydarzenia z godzina
// (calodniowe tylko gdy nic timed nie zostalo), zeby "za 12 min" nie
// ginelo za all-day od polnocy.
function eventEndMs(ev) {
  if (ev.end) return new Date(ev.end).getTime();
  const start = new Date(ev.start);
  if (ev.allDay) {
    const d = new Date(start);
    d.setHours(24, 0, 0, 0);
    return d.getTime();
  }
  return start.getTime() + 60 * 60 * 1000;
}

function pickCountdownEvent(events, now) {
  const timed = [];
  const allDay = [];
  for (const ev of events || []) {
    if (eventEndMs(ev) <= now) continue;
    (ev.allDay ? allDay : timed).push(ev);
  }
  return timed[0] || allDay[0] || null;
}

function formatRemain(ms) {
  const mins = Math.max(0, Math.ceil(ms / 60000));
  if (mins <= 0) return 'teraz';
  if (mins < 60) return mins + ' min';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  if (h < 24) return m > 0 ? `${h} h ${m} min` : `${h} h`;
  const days = Math.floor(h / 24);
  return days === 1 ? '1 dzień' : days + ' dni';
}

// Okno paska: do startu - od polnocy; w trakcie - od startu do konca.
// Wypelnienie rosnie jak w limitach (0% rano, 100% gdy sie zaczyna).
function countdownWindow(ev, now) {
  const start = new Date(ev.start).getTime();
  const end = eventEndMs(ev);
  if (start <= now) return { from: start, to: end };
  const midnight = new Date();
  midnight.setHours(0, 0, 0, 0);
  return { from: midnight.getTime(), to: start };
}

let countdownBarReady = false;
function setCountdownBar(panel, pct) {
  const bar = document.getElementById('countdownBar');
  const fill = bar && bar.querySelector('.countdown-bar-fill');
  const p = Math.max(0, Math.min(100, pct));
  if (bar) {
    if (fill && !countdownBarReady) fill.style.transition = 'none';
    bar.style.setProperty('--used', p + '%');
    bar.style.setProperty('--used-n', String(Math.round(p)));
    if (fill && !countdownBarReady) {
      void fill.getBoundingClientRect();
      fill.style.transition = '';
      countdownBarReady = true;
    }
  }
  panel.classList.toggle('low', p < 40);
}

function renderCountdown() {
  const panel = document.getElementById('countdownPanel');
  const label = document.getElementById('countdownLabel');
  const when = document.getElementById('countdownWhen');
  const title = document.getElementById('countdownTitle');
  const meta = document.getElementById('countdownMeta');
  if (!panel || !when) return;

  const now = Date.now();
  const ev = pickCountdownEvent(eventsLast, now);

  if (!ev) {
    panel.classList.remove('live', 'low');
    panel.style.removeProperty('--countdown-bar');
    setCountdownBar(panel, 0);
    if (label) label.textContent = 'Następne';
    when.textContent = '—';
    if (title) title.textContent = 'Brak nadchodzących';
    if (meta) meta.textContent = '';
    return;
  }

  const start = new Date(ev.start).getTime();
  const live = start <= now;
  panel.classList.toggle('live', live);
  panel.style.setProperty('--countdown-bar', ev.color || 'var(--accent)');

  const win = countdownWindow(ev, now);
  const span = win.to - win.from;
  setCountdownBar(panel, span > 0 ? ((now - win.from) / span) * 100 : 0);

  if (ev.allDay && !live) {
    if (label) label.textContent = 'Następne';
    when.textContent = 'cały dzień';
  } else if (ev.allDay) {
    if (label) label.textContent = 'Dziś';
    when.textContent = 'cały dzień';
  } else if (live) {
    if (label) label.textContent = 'Teraz';
    when.textContent = 'jeszcze ' + formatRemain(eventEndMs(ev) - now);
  } else {
    if (label) label.textContent = 'Następne';
    when.textContent = 'za ' + formatRemain(start - now);
  }

  if (title) title.textContent = ev.title || '';
  if (meta) {
    const startDate = new Date(ev.start);
    const today = new Date();
    const tomorrow = new Date();
    tomorrow.setDate(today.getDate() + 1);
    const sameDay = startDate.toDateString() === today.toDateString();
    const isTomorrow = startDate.toDateString() === tomorrow.toDateString();
    const dayBit = sameDay ? '' : (isTomorrow ? 'jutro ' : startDate.toLocaleDateString('pl-PL', { weekday: 'short' }) + ' ');
    meta.textContent = ev.allDay
      ? (sameDay ? 'dziś' : (isTomorrow ? 'jutro' : dayBit.trim()))
      : dayBit + formatEventTime(ev);
  }
}

if (panelOn('countdown')) setInterval(renderCountdown, 1000);
