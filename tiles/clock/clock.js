/* ============== ZEGAR + DATA ============== */
const CLOCK_RING_C = 2 * Math.PI * 17; // r=17 w viewBox 40x40
let clockRingsReady = false;

function setClockRing(el, t, wrap) {
  if (!el) return;
  const p = Math.max(0, Math.min(1, t));
  el.style.transition = (!clockRingsReady || wrap) ? 'none' : '';
  el.style.strokeDasharray = String(CLOCK_RING_C);
  el.style.strokeDashoffset = String(CLOCK_RING_C * (1 - p));
}

function updateClock() {
  const now = new Date();
  const h = now.getHours();
  const m = now.getMinutes();
  const s = now.getSeconds();

  const hEl = document.getElementById('clockH');
  const mEl = document.getElementById('clockM');
  const sEl = document.getElementById('clockS');
  if (hEl) hEl.textContent = String(h).padStart(2, '0');
  if (mEl) mEl.textContent = String(m).padStart(2, '0');
  if (sEl) sEl.textContent = String(s).padStart(2, '0');

  setClockRing(document.getElementById('clockRingH'), (h + m / 60 + s / 3600) / 24, h === 0 && m === 0 && s === 0);
  setClockRing(document.getElementById('clockRingM'), (m + s / 60) / 60, m === 0 && s === 0);
  setClockRing(document.getElementById('clockRingS'), s / 60, s === 0);
  clockRingsReady = true;
}
if (panelOn('clock')) {
  updateClock();
  setInterval(updateClock, 1000);
}

/* Tarcze wypelniaja kafelek (iPad nie honoruje cqh). Fallback: --clock-dial w CSS. */
(function fitClockDials() {
  if (!panelOn('clock')) return;
  const panel = document.querySelector('.clock-panel');
  const face = document.querySelector('.clock-face');
  if (!panel || !face) return;

  function apply() {
    const h = face.clientHeight;
    const w = face.clientWidth;
    if (h < 8 || w < 8) return;
    const gap = 6;
    let extra = 0;
    face.querySelectorAll('.clock-colon').forEach((el) => { extra += el.offsetWidth; });
    const byW = (w - gap * 4 - extra) / 3;
    const size = Math.max(44, Math.min(h, byW, 140));
    panel.style.setProperty('--clock-dial', Math.floor(size) + 'px');
  }

  apply();
  if (typeof ResizeObserver !== 'undefined') {
    new ResizeObserver(apply).observe(face);
  } else {
    window.addEventListener('resize', apply);
  }
})();
/* ============== POMODORO ============== */
// Modal: kubek w srodku, a kolo wokół to progress bar odliczania
// (zostalo / calosc; pathLength=100, wiec offset 0 = pelny, 100 = pusty).
let pomodoroRaf = null;
let pomodoroEndAt = 0;
let pomodoroTotalMs = 0;

function formatPomodoro(ms) {
  const s = Math.max(0, Math.ceil(ms / 1000));
  const m = Math.floor(s / 60);
  return String(m).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}

function setPomodoroRing(remaining) {
  const el = document.getElementById('pomodoroRing');
  if (!el) return;
  const t = Math.max(0, Math.min(1, remaining));
  el.style.strokeDasharray = '100';
  el.style.strokeDashoffset = String(100 * (1 - t));
}

function paintPomodoro(leftMs) {
  const timeEl = document.getElementById('pomodoroTime');
  if (timeEl) timeEl.textContent = formatPomodoro(leftMs);
  setPomodoroRing(pomodoroTotalMs > 0 ? leftMs / pomodoroTotalMs : 0);
}

function stopPomodoro() {
  if (pomodoroRaf) {
    cancelAnimationFrame(pomodoroRaf);
    pomodoroRaf = null;
  }
  pomodoroEndAt = 0;
  pomodoroTotalMs = 0;
  const overlay = document.getElementById('pomodoroOverlay');
  if (overlay) {
    overlay.hidden = true;
    overlay.classList.remove('done');
  }
  const stopBtn = document.getElementById('pomodoroStop');
  if (stopBtn) stopBtn.textContent = 'Przerwij';
}

function finishPomodoro() {
  pomodoroRaf = null;
  paintPomodoro(0);
  const overlay = document.getElementById('pomodoroOverlay');
  if (overlay) overlay.classList.add('done');
  const stopBtn = document.getElementById('pomodoroStop');
  if (stopBtn) stopBtn.textContent = 'Zamknij';
  try { navigator.vibrate && navigator.vibrate([200, 80, 200]); } catch (e) {}
}

function tickPomodoro() {
  const left = pomodoroEndAt - Date.now();
  if (left <= 0) {
    finishPomodoro();
    return;
  }
  paintPomodoro(left);
  pomodoroRaf = requestAnimationFrame(tickPomodoro);
}

function startPomodoro(minutes) {
  const overlay = document.getElementById('pomodoroOverlay');
  const timeEl = document.getElementById('pomodoroTime');
  if (!overlay || !timeEl) return;

  if (pomodoroRaf) {
    cancelAnimationFrame(pomodoroRaf);
    pomodoroRaf = null;
  }

  pomodoroTotalMs = minutes * 60 * 1000;
  pomodoroEndAt = Date.now() + pomodoroTotalMs;
  overlay.classList.remove('done');
  overlay.hidden = false;
  const stopBtn = document.getElementById('pomodoroStop');
  if (stopBtn) stopBtn.textContent = 'Przerwij';
  paintPomodoro(pomodoroTotalMs);
  pomodoroRaf = requestAnimationFrame(tickPomodoro);
}

{
  const overlay = document.getElementById('pomodoroOverlay');
  const stopBtn = document.getElementById('pomodoroStop');
  const wrap = document.getElementById('pomodoroBtns');
  const bar = document.querySelector('.pomodoro-bar');
  if (wrap) {
    wrap.innerHTML = POMODORO_PRESETS.map((min) =>
      `<button type="button" class="pomodoro-btn" data-min="${min}">${min} min</button>`
    ).join('');
    wrap.addEventListener('click', (e) => {
      const btn = e.target.closest('.pomodoro-btn');
      if (!btn || !wrap.contains(btn)) return;
      const min = Number(btn.getAttribute('data-min'));
      if (min > 0) startPomodoro(min);
    });
  }
  if (bar) bar.hidden = POMODORO_PRESETS.length === 0;
  if (stopBtn) stopBtn.addEventListener('click', stopPomodoro);
  if (overlay) {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) stopPomodoro();
    });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || !overlay || overlay.hidden) return;
    const cfg = document.getElementById('configOverlay');
    if (cfg && !cfg.hidden) return;
    stopPomodoro();
  });
  try {
    const q = Number(new URLSearchParams(location.search).get('pomodoro'));
    if (POMODORO_PRESETS.includes(q)) startPomodoro(q);
  } catch (e) {}
}
