/* ============== STATUS INTERNETU ============== */
let downSinceTimestamp = null;

function formatDuration(ms) {
  const seconds = Math.floor(ms / 1000);
  if (seconds < 60) return `od ${seconds} sek.`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `od ${minutes} min.`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `od ${hours} godz.`;
  const days = Math.floor(hours / 24);
  return `od ${days} dni`;
}

async function checkInternet() {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 4000);

  try {
    // no-cors: nie czytamy odpowiedzi, liczy się tylko że fetch się powiódł
    await fetch('https://www.gstatic.com/generate_204', {
      mode: 'no-cors',
      cache: 'no-store',
      signal: controller.signal
    });
    clearTimeout(timeout);
    setNetStatus(true);
  } catch (e) {
    clearTimeout(timeout);
    setNetStatus(false);
  }
}

function setNetStatus(connected) {
  const panel = document.getElementById('netPanel');
  const dot = document.getElementById('netDot');
  const label = document.getElementById('netLabel');
  const detail = document.getElementById('netDetail');
  const downSinceEl = document.getElementById('netDownSince');

  const now = new Date();
  detail.textContent = `ostatnia próba: ${now.toLocaleTimeString('pl-PL')}`;

  if (connected) {
    panel.className = 'panel net-panel connected';
    dot.className = 'net-dot connected';
    label.className = 'net-label connected';
    label.textContent = 'ON-LINE';
    downSinceTimestamp = null;
    downSinceEl.textContent = '';
  } else {
    panel.className = 'panel net-panel disconnected';
    dot.className = 'net-dot disconnected';
    label.className = 'net-label disconnected';
    label.textContent = 'OFF-LINE';
    if (downSinceTimestamp === null) {
      downSinceTimestamp = Date.now();
    }
    downSinceEl.textContent = formatDuration(Date.now() - downSinceTimestamp);
  }
}

if (panelOn('internet')) {
  checkInternet();
  setInterval(checkInternet, 5000); // co 5 sekund - to JEST realne, w przeciwieństwie do widgetu
}
