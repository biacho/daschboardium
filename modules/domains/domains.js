/* ============== STATUS DOMEN ============== */
// Backend (check-domains.php) sprawdza HTTP + DNS (A/MX/CAA przez DoH) + SSL + RDAP.
// Tutaj tylko render - caly stan zbiorczy (ok/warn/error) liczy PHP.
const DOMAINS_ENDPOINT = 'api/check-domains.php';

// Kropka statusu przy nazwie: zielona / bursztynowa / czerwona
function domainBadge(label, state, value) {
  return `<span class="domain-badge ${state}">${escapeHtml(label)}`
    + (value != null ? `<span class="domain-badge-v">${escapeHtml(String(value))}</span>` : '')
    + '</span>';
}

function renderDomain(d) {
  const badges = [];

  // HTTP: kod + czas odpowiedzi (albo blad polaczenia)
  const httpVal = d.http.ok ? `${d.http.code} · ${d.http.ms} ms` : (d.http.code || 'brak');
  badges.push(domainBadge('WWW', d.http.ok ? 'ok' : 'error', httpVal));

  badges.push(domainBadge('A', d.dns.aOk ? 'ok' : 'error', null));
  badges.push(domainBadge('MX', d.dns.mxOk ? 'ok' : 'warn', null));
  // Brak CAA to nie blad, tylko brak restrykcji - stan neutralny
  badges.push(domainBadge('CAA', d.dns.caaPresent ? 'ok' : 'idle', null));

  if (d.sslDays != null) {
    const state = d.sslDays < 0 ? 'error' : (d.sslDays < 14 ? 'warn' : 'ok');
    badges.push(domainBadge('SSL ·', state, d.sslDays < 0 ? 'wygasł' : String(d.sslDays) + " dni"));
  }
  // Rozjazd z wartosciami oczekiwanymi z config.php (expect_a / expect_mx)
  if (d.mismatch && d.mismatch.length) {
    badges.push(domainBadge('Rozjazd', 'warn', d.mismatch.join(', ')));
  }

  // Waznosc rejestracji domeny (dni) w nawiasie przy nazwie - sama liczba,
  // zamiast osobnej plakietki; oszczedza caly rzad w module
  let expiry = '';
  if (d.domDays != null) {
    const state = d.domDays < 0 ? 'error' : (d.domDays < 30 ? 'warn' : 'ok');
    expiry = `<span class="domain-expiry ${state}">(${d.domDays < 0 ? 'wygasła' : d.domDays} dni)</span>`;
  }

  return `
    <div class="domain-item ${d.status}">
      <div class="domain-head">
        <span class="domain-dot ${d.status}"></span>
        <span class="domain-name">${escapeHtml(d.name)}</span>
        ${expiry}
      </div>
      <div class="domain-badges">${badges.join('')}</div>
    </div>`;
}

async function fetchDomains() {
  const list = document.getElementById('domainsList');
  if (!list) return; // panel moze byc usuniety z index.html
  const updated = document.getElementById('domainsUpdated');

  try {
    const res = await fetch(DOMAINS_ENDPOINT, { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    if (!data.domains || data.domains.length === 0) {
      list.innerHTML = '<div class="domains-empty">Brak domen w konfiguracji</div>';
      if (updated) updated.textContent = '';
      return;
    }

    list.innerHTML = data.domains.map(renderDomain).join('');
    if (updated && data.generated) {
      const t = new Date(data.generated).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });
      updated.textContent = `sprawdzono: ${t}`;
    }
  } catch (e) {
    list.innerHTML = '<div class="domains-empty">Nie udało się sprawdzić domen</div>';
    if (updated) updated.textContent = '';
  }
}

if (panelOn('domains')) {
  fetchDomains();
  setInterval(fetchDomains, 5 * 60 * 1000); // co 5 minut - i tak cache'owane po stronie PHP
}
