/* ============== KALENDARZ ============== */
function renderCalendar() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const todayDate = now.getDate();

  const monthEl = document.getElementById('calMonth');
  const grid = document.getElementById('calGrid');
  if (!grid) return;
  const monthLabel = now.toLocaleDateString('pl-PL', { month: 'long', year: 'numeric' });
  if (monthEl) monthEl.textContent = monthLabel;

  const weekdays = ['P', 'W', 'Ś', 'C', 'P', 'S', 'N'];
  grid.innerHTML = '';

  weekdays.forEach(w => {
    const el = document.createElement('div');
    el.className = 'cal-weekday';
    el.textContent = w;
    grid.appendChild(el);
  });

  const firstOfMonth = new Date(year, month, 1);
  let firstWeekday = firstOfMonth.getDay(); // 0 = niedziela
  firstWeekday = firstWeekday === 0 ? 6 : firstWeekday - 1; // przesunięcie tak, by poniedziałek = 0

  const daysInMonth = new Date(year, month + 1, 0).getDate();

  for (let i = 0; i < firstWeekday; i++) {
    const el = document.createElement('div');
    el.className = 'cal-day empty';
    grid.appendChild(el);
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const el = document.createElement('div');
    el.className = 'cal-day' + (d === todayDate ? ' today' : '');
    el.textContent = d;
    grid.appendChild(el);
  }
  fitCalDays();
}

function fitCalDays() {
  const grid = document.getElementById('calGrid');
  if (!grid) return;
  const day = grid.querySelector('.cal-day:not(.empty)');
  if (!day) return;
  const h = day.clientHeight;
  const w = day.clientWidth;
  if (h < 4 || w < 4) return;
  const size = Math.max(9, Math.min(Math.floor(h * 0.52), Math.floor(w * 0.48), 20));
  grid.style.setProperty('--cal-day-size', size + 'px');
}

if (panelOn('calendar')) {
  renderCalendar();
  requestAnimationFrame(() => requestAnimationFrame(fitCalDays));
  setInterval(renderCalendar, 60 * 1000);
  const grid = document.getElementById('calGrid');
  const panel = document.querySelector('.cal-panel');
  if (grid && typeof ResizeObserver !== 'undefined') {
    const ro = new ResizeObserver(fitCalDays);
    ro.observe(grid);
    if (panel) ro.observe(panel);
  } else {
    window.addEventListener('resize', fitCalDays);
  }
}
