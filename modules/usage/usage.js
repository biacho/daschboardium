/* ============== ZUZYCIE TOKENOW (CLAUDE CODE) ============== */
// Dane pochodza ze snapshotu robionego cronem przez usage-snapshot.php - php-fpm
// nie ma dostepu do ~/.claude/projects (700), wiec tu leci tylko gotowa agregacja.
const USAGE_ENDPOINT = 'api/get-usage.php';

// 1 234 -> "1,2 tys.", 5 633 837 -> "5,63 mln"
function formatTokens(n) {
  if (n == null) return '—';
  if (n < 1000) return String(n);
  if (n < 1000000) return (n / 1000).toLocaleString('pl-PL', { maximumFractionDigits: 1 }) + ' tys.';
  return (n / 1000000).toLocaleString('pl-PL', { maximumFractionDigits: 2 }) + ' mln';
}

function renderClaudeTokens(d) {
  const t = d.today || {};
  const w = d.week || {};
  const spend = d.plan && d.plan.spend;
  const bits = [
    `dziś ${formatTokens(t.total)}`,
    `tydz. ${formatTokens(w.total)}`,
  ];
  if (spend && spend.used != null) {
    bits.push(
      `${spend.used.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${spend.currency}`
    );
  }
  return `<div class="usage-sub usage-products">${bits.map(escapeHtml).join(' · ')}</div>`;
}

// "za 2 h 38 min · 15:10", a dla odleglego resetu z nazwa dnia: "... · pt 21:00"
function formatReset(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const time = d.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });
  const sameDay = d.toDateString() === new Date().toDateString();
  const at = sameDay ? time : `${d.toLocaleDateString('pl-PL', { weekday: 'short' })} ${time}`;

  const mins = Math.round((d - Date.now()) / 60000);
  if (mins <= 0) return `reset ${at}`;

  const h = Math.floor(mins / 60);
  const m = mins % 60;

  let left;
  if (h >= 24) {
    // powyzej doby (limit tygodniowy) godziny+minuty sa nieczytelne
    const days = Math.floor(h / 24);
    const restH = h % 24;
    left = restH > 0 ? `${days} d ${restH} h` : `${days} d`;
  } else {
    left = h > 0 ? (m > 0 ? `${h} h ${m} min` : `${h} h`) : `${m} min`;
  }

  return `za ${left} · ${at}`;
}

// Kolor paska wg ZUZYCIA: spokojnie do 60%, ostrzegawczo do 85%, potem alarm
function usageState(percent) {
  if (percent >= 85) return 'error';
  if (percent >= 60) return 'warn';
  return 'ok';
}

function renderLimit(title, limit) {
  if (!limit || limit.percent == null) return '';
  const used = Math.round(limit.percent);
  const state = usageState(used);
  // Ponizej ~18% liczba nie miesci sie w wypelnieniu - zostaje na torze
  const low = used < 18 ? ' low' : '';

  return `
    <div class="usage-limit ${state}${low}">
      <div class="usage-limit-head">
        <span class="usage-limit-title">${escapeHtml(title)}</span>
        <span class="usage-limit-reset">${escapeHtml(formatReset(limit.resetsAt))}</span>
      </div>
      <div class="usage-bar" style="--used:${used}%;--used-n:${used}">
        <div class="usage-bar-fill"></div>
        <span class="usage-bar-pct">${used}%</span>
      </div>
    </div>`;
}

function grokProductLabel(name) {
  if (name === 'GrokBuild') return 'Build';
  if (name === 'GrokChat') return 'Chat';
  if (name === 'GrokImagine') return 'Imagine';
  return name || '';
}

function renderGrokProducts(products) {
  const order = GROK_PRODUCTS.length ? GROK_PRODUCTS : GROK_ALLOWED;
  const byName = {};
  for (const p of products || []) {
    if (p && p.name) byName[p.name] = p;
  }
  return order.map((name) => {
    const p = byName[name] || { name, percent: 0 };
    return renderLimit(grokProductLabel(name), {
      percent: p.percent == null ? 0 : p.percent,
      resetsAt: null,
    });
  }).join('');
}

// Ostatnia odpowiedz trzymana po to, zeby odliczanie do resetu dalo sie
// przerysowac bez ponownego strzalu do backendu
let usageLastData = null;

function renderUsage(d) {
  const main = document.getElementById('usageMain');
  if (!main) return; // panel moze byc usuniety z index.html
  const updated = document.getElementById('usageUpdated');
  const plan = d.plan;
  const grok = d.grok;

  let html = '';

  if (SHOW_CLAUDE) {
    if (plan && plan.session) {
      html += `<div class="usage-group claude">`
        + `<div class="usage-group-label">Claude Code</div>`
        + renderLimit('5 h', plan.session)
        + renderLimit('Tydzień', plan.weekly)
        + renderClaudeTokens(d)
        + `</div>`;
    } else if (d.planError) {
      html += `<div class="usage-group claude">`
        + `<div class="usage-group-label">Claude Code</div>`
        + `<div class="usage-sub">${escapeHtml(d.planError)}</div>`
        + `</div>`;
    }
  }

  if (SHOW_GROK) {
    if (grok && (grok.percent != null || (grok.products && grok.products.length))) {
      html += `<div class="usage-group grok">`
        + `<div class="usage-group-head">`
        + `<div class="usage-group-label">Grok</div>`
        + `<span class="usage-limit-reset">${escapeHtml(formatReset(grok.resetsAt))}</span>`
        + `</div>`
        + renderGrokProducts(grok.products)
        + `</div>`;
    } else if (d.grokError) {
      html += `<div class="usage-group grok">`
        + `<div class="usage-group-label">Grok</div>`
        + `<div class="usage-sub">${escapeHtml(d.grokError)}</div>`
        + `</div>`;
    }
  }

  if (!html) {
    if (!SHOW_CLAUDE && !SHOW_GROK) {
      html = '<div class="usage-sub">Limity wyłączone w konfiguracji</div>';
    } else {
      html = `<div class="usage-sub">${escapeHtml(d.planError || d.grokError || 'Brak danych o limicie')}</div>`
        + `<div class="usage-sub">Dziś na tym komputerze: ${formatTokens((d.today || {}).total)} tokenów</div>`;
    }
  }

  main.innerHTML = html;

  if (updated) {
    const mins = Math.round((d.ageSeconds ?? 0) / 60);
    const staleBits = [];
    if (SHOW_CLAUDE && plan && plan.stalePlan) staleBits.push(d.planError ? `Claude: ${d.planError}` : 'Claude');
    if (SHOW_GROK && grok && grok.stalePlan) staleBits.push(d.grokError ? `Grok: ${d.grokError}` : 'Grok');

    if (d.stale) {
      updated.textContent = `⚠ snapshot sprzed ${mins} min — sprawdź usługę usage`;
    } else if (staleBits.length) {
      updated.textContent = `⚠ limity z API nieaktualne — ${staleBits.join(' · ')}`;
    } else {
      updated.textContent = '';
    }
    updated.classList.toggle('stale', !!(d.stale || staleBits.length));
  }
}

async function fetchUsage() {
  const main = document.getElementById('usageMain');
  if (!main) return;

  try {
    const res = await fetch(USAGE_ENDPOINT, { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const d = await res.json();
    if (d.error) throw new Error(d.error);

    usageLastData = d;
    renderUsage(d);
  } catch (e) {
    usageLastData = null;
    main.innerHTML = '<div class="usage-sub">Brak danych o zużyciu</div>';
    const updated = document.getElementById('usageUpdated');
    if (updated) updated.textContent = '';
  }
}

if (panelOn('usage')) {
  fetchUsage();
  setInterval(fetchUsage, 60 * 1000); // co minute - tak samo czesto jak cron ze snapshotem
  setInterval(() => { if (usageLastData) renderUsage(usageLastData); }, 30 * 1000);
}
