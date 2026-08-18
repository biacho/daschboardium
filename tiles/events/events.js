/* ============== WYDARZENIA Z KALENDARZA ============== */
// Endpoint PHP musi siedzieć na tym samym serwerze co kiosk.html
// (albo ustaw pełny URL i odpowiednio CORS w get-events.php)
const EVENTS_ENDPOINT = 'get-events.php';

function formatEventTime(ev) {
  if (ev.allDay) return 'cały dzień';
  const start = new Date(ev.start);
  const startStr = start.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });
  if (!ev.end) return startStr;
  const end = new Date(ev.end);
  const endStr = end.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });
  return `${startStr}–${endStr}`;
}

// Etykieta dnia: "Dzisiaj" / "Jutro (wtorek)" - z nazwa dnia tygodnia, bez daty
// (data jest w kafelku Kalendarza obok). Lista i tak ograniczona po stronie PHP.
function dayLabel(date) {
  const today = new Date();
  const tomorrow = new Date();
  tomorrow.setDate(today.getDate() + 1);
  const sameDay = (a, b) => a.toDateString() === b.toDateString();
  const weekday = date.toLocaleDateString('pl-PL', { weekday: 'long' });
  if (sameDay(date, today)) return 'Dzisiaj';
  if (sameDay(date, tomorrow)) return `Jutro <span class="day-weekday">(${weekday})</span>`;
  return weekday;
}

let eventsLast = [];

async function fetchEvents() {
  const list = document.getElementById('eventsList');
  try {
    const res = await fetch(EVENTS_ENDPOINT, { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    eventsLast = data.events || [];
    if (typeof renderCountdown === 'function') renderCountdown();

    if (!list) return;
    if (eventsLast.length === 0) {
      list.innerHTML = '<div class="events-empty">Brak najbliższych wydarzeń</div>';
      return;
    }

    // Grupowanie po dniu z naglowkiem Dziś/Jutro (bez pelnej daty)
    const groups = {};
    eventsLast.forEach(ev => {
      const key = new Date(ev.start).toDateString();
      (groups[key] ||= { date: new Date(ev.start), items: [] }).items.push(ev);
    });

    let html = '';
    Object.values(groups).forEach(group => {
      html += `<div class="events-day-label">${dayLabel(group.date)}</div>`;
      group.items.forEach(ev => {
        const barColor = ev.color || 'var(--accent-cyan)';
        const calTitle = ev.calendar ? ` title="${escapeHtml(ev.calendar)}"` : '';
        html += `
          <div class="event-item"${calTitle}>
            <div class="event-bar" style="background:${escapeHtml(barColor)}"></div>
            <div class="event-text">
              <div class="event-time">${formatEventTime(ev)}</div>
              <div class="event-title">${escapeHtml(ev.title)}</div>
            </div>
          </div>`;
      });
    });

    list.innerHTML = html;
  } catch (e) {
    eventsLast = [];
    if (typeof renderCountdown === 'function') renderCountdown();
    if (list) list.innerHTML = '<div class="events-empty">Nie udało się wczytać wydarzeń</div>';
  }
}

if (panelOn('events') || panelOn('countdown')) {
  fetchEvents();
  setInterval(fetchEvents, 5 * 60 * 1000); // co 5 minut - i tak cache'owane po stronie PHP
}
