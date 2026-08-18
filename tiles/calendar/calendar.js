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
}
if (panelOn('calendar')) renderCalendar();
/* Odśwież datę o północy */
if (panelOn('calendar')) setInterval(renderCalendar, 60 * 1000);
