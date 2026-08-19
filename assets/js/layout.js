/* ============== GESTOSC KAFELKOW + EDYCJA SIATKI ============== */
/* iPad Mini 4 (iOS 15) nie ma container queries — gęstość z ResizeObserver.
   Tryb edycji jak na pulpicie iOS: chwytasz moduł i wkładasz w kolumnę.
   Żywy panel zostaje w slocie (ściska się z sąsiadami), duch idzie za palcem. */
(function initLayout() {
  const COLS = ['left', 'mid', 'right'];
  const SAVE = 'api/save-layout.php';

  const kiosk = document.querySelector('.kiosk');
  if (!kiosk) return;

  function densityFor(h) {
    if (h < 168) return 'tight';
    if (h < 240) return 'compact';
    if (h < 270) return 'snug';
    return 'roomy';
  }

  function visibleColumnPanels(col) {
    return Array.from(col.querySelectorAll(':scope > .panel[data-panel]')).filter((p) => !p.hidden);
  }

  /* Hug-moduły sa niskie z zawartosci. Gęstość ma zależeć od miejsca
     w kolumnie (równy udział), inaczej tight chowa Pomodoro / statystyki
     i moduł nigdy nie urosnie. */
  function densityHeight(panel) {
    const col = panel.parentElement;
    let h = panel.clientHeight;
    if (!col || !col.hasAttribute('data-col')) return h;
    const vis = visibleColumnPanels(col);
    if (vis.length < 1) return h;
    const gap = parseFloat(getComputedStyle(col).rowGap || getComputedStyle(col).gap) || 0;
    const share = (col.clientHeight - gap * Math.max(0, vis.length - 1)) / vis.length;
    if (share > h) return share;
    return h;
  }

  function applyDensity(panel) {
    if (!panel || panel.classList.contains('net-panel')) return;
    const h = densityHeight(panel);
    if (h < 8) return;
    const next = densityFor(h);
    if (panel.dataset.density !== next) panel.dataset.density = next;
  }

  function columnPanels() {
    return kiosk.querySelectorAll('.col-left > .panel, .col-mid > .panel, .col-right > .panel');
  }

  function observeDensity() {
    const run = () => columnPanels().forEach(applyDensity);
    run();
    if (typeof ResizeObserver === 'undefined') {
      window.addEventListener('resize', run);
      return null;
    }
    const ro = new ResizeObserver(run);
    columnPanels().forEach((p) => ro.observe(p));
    kiosk.querySelectorAll('[data-col]').forEach((col) => ro.observe(col));
    return ro;
  }

  const densityRo = observeDensity();

  function canEditLayout() {
    try {
      return window.matchMedia('(min-width: 960px) and (min-height: 520px) and (min-aspect-ratio: 11/10)').matches;
    } catch (e) {
      return true;
    }
  }

  function colEl(id) {
    return kiosk.querySelector('[data-col="' + id + '"]');
  }

  function visibleIn(col) {
    return Array.from(col.querySelectorAll(':scope > .panel[data-panel]')).filter((p) => !p.hidden);
  }

  function readLayout() {
    const out = { left: [], mid: [], right: [] };
    COLS.forEach((id) => {
      const col = colEl(id);
      if (!col) return;
      col.querySelectorAll(':scope > .panel[data-panel]').forEach((p) => {
        out[id].push(p.dataset.panel);
      });
    });
    return out;
  }

  function sameLayout(a, b) {
    return JSON.stringify(a) === JSON.stringify(b);
  }

  let editing = false;
  let drag = null;
  let savedOnEnter = null;
  let saveTimer = 0;

  function persistLayout() {
    const payload = readLayout();
    if (APP) APP.layout = payload;
    if (sameLayout(payload, savedOnEnter)) return;
    savedOnEnter = payload;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      fetch(SAVE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        cache: 'no-store',
        body: JSON.stringify(payload),
      }).catch(() => {});
    }, 180);
  }

  function setDropCol(col) {
    kiosk.querySelectorAll('[data-col]').forEach((el) => {
      el.classList.toggle('is-drop', el === col);
    });
  }

  function columnAt(x, y) {
    let best = null;
    let bestDist = Infinity;
    COLS.forEach((id) => {
      const col = colEl(id);
      if (!col) return;
      const r = col.getBoundingClientRect();
      if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) {
        best = col;
        bestDist = 0;
        return;
      }
      const cx = (r.left + r.right) / 2;
      const cy = (r.top + r.bottom) / 2;
      const d = Math.hypot(x - cx, y - cy);
      if (d < bestDist) {
        best = col;
        bestDist = d;
      }
    });
    return best;
  }

  function placePanel(panel, col, y) {
    const others = visibleIn(col).filter((p) => p !== panel);
    let target = others.length;
    for (let i = 0; i < others.length; i++) {
      const r = others[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) {
        target = i;
        break;
      }
    }
    const current = panel.parentNode === col ? visibleIn(col).indexOf(panel) : -1;
    if (current === target && panel.parentNode === col) return;

    const ref = others[target] || null;
    if (ref) col.insertBefore(panel, ref);
    else if (others.length) {
      const last = others[others.length - 1];
      if (last.nextSibling) col.insertBefore(panel, last.nextSibling);
      else col.appendChild(panel);
    } else {
      col.appendChild(panel);
    }
    if (typeof syncPanelLayout === 'function') syncPanelLayout();
  }

  function syncGhost() {
    if (!drag) return;
    applyDensity(drag.panel);
    drag.clone.dataset.density = drag.panel.dataset.density || '';
    const slot = drag.panel.getBoundingClientRect();
    if (slot.width > 8 && slot.height > 8) {
      drag.clone.style.width = Math.round(slot.width) + 'px';
      drag.clone.style.height = Math.round(slot.height) + 'px';
    }
  }

  function startDrag(panel, e) {
    if (drag) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();

    const rect = panel.getBoundingClientRect();
    const clone = panel.cloneNode(true);
    clone.classList.add('is-layout-ghost');
    clone.classList.remove('is-layout-slot');
    clone.removeAttribute('id');
    clone.removeAttribute('data-panel');
    clone.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
    clone.style.left = Math.round(rect.left) + 'px';
    clone.style.top = Math.round(rect.top) + 'px';
    clone.style.width = Math.round(rect.width) + 'px';
    clone.style.height = Math.round(rect.height) + 'px';
    document.body.appendChild(clone);

    panel.classList.add('is-layout-slot');
    try { panel.setPointerCapture(e.pointerId); } catch (err) {}

    drag = {
      panel: panel,
      clone: clone,
      pointerId: e.pointerId,
      offsetX: e.clientX - rect.left,
      offsetY: e.clientY - rect.top,
    };

    onMove(e);
  }

  function onMove(e) {
    if (!drag) return;
    if (e.pointerId != null && e.pointerId !== drag.pointerId) return;
    e.preventDefault();

    const x = e.clientX;
    const y = e.clientY;
    drag.clone.style.left = Math.round(x - drag.offsetX) + 'px';
    drag.clone.style.top = Math.round(y - drag.offsetY) + 'px';

    const col = columnAt(x, y);
    setDropCol(col);
    if (col) placePanel(drag.panel, col, y);
    syncGhost();
  }

  function endDrag(e) {
    if (!drag) return;
    if (e && e.pointerId != null && e.pointerId !== drag.pointerId) return;
    const panel = drag.panel;
    try { panel.releasePointerCapture(drag.pointerId); } catch (err) {}
    if (drag.clone && drag.clone.parentNode) drag.clone.parentNode.removeChild(drag.clone);
    panel.classList.remove('is-layout-slot');
    setDropCol(null);
    drag = null;
    persistLayout();
  }

  function setEditing(on) {
    on = !!on && canEditLayout();
    if (editing === on) return;
    if (!on && drag) endDrag();
    editing = on;
    kiosk.classList.toggle('is-editing', on);
    const bar = document.getElementById('layoutBar');
    if (bar) bar.hidden = !on;
    if (on) {
      savedOnEnter = readLayout();
      if (typeof closePathMenuNow === 'function') closePathMenuNow();
      columnPanels().forEach((p, i) => {
        p.style.animationDelay = ((i % 5) * -0.05) + 's';
      });
    } else {
      kiosk.querySelectorAll('[data-col]').forEach((el) => el.classList.remove('is-drop'));
    }
    if (typeof syncPanelLayout === 'function') syncPanelLayout();
  }

  kiosk.addEventListener('pointerdown', (e) => {
    if (!editing) return;
    const panel = e.target.closest('.col-left > .panel, .col-mid > .panel, .col-right > .panel');
    if (!panel || panel.hidden) return;
    startDrag(panel, e);
  });

  window.addEventListener('pointermove', onMove, { passive: false });
  window.addEventListener('pointerup', endDrag);
  window.addEventListener('pointercancel', endDrag);

  const layoutBtn = document.getElementById('layoutBtn');
  const layoutDone = document.getElementById('layoutDone');
  if (layoutBtn) {
    layoutBtn.addEventListener('click', () => {
      if (!canEditLayout()) return;
      setEditing(true);
    });
  }
  if (layoutDone) layoutDone.addEventListener('click', () => setEditing(false));

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && editing) {
      e.stopPropagation();
      setEditing(false);
    }
  });

  function syncEditAvailability() {
    const on = canEditLayout();
    if (layoutBtn) {
      layoutBtn.hidden = !on;
      const li = layoutBtn.closest('li');
      if (li) li.hidden = !on;
    }
    if (typeof refreshPathMenu === 'function') refreshPathMenu();
    if (editing && !on) setEditing(false);
  }
  syncEditAvailability();
  window.addEventListener('resize', syncEditAvailability);
  try {
    const mq = window.matchMedia('(min-width: 960px) and (min-height: 520px) and (min-aspect-ratio: 11/10)');
    if (mq.addEventListener) mq.addEventListener('change', syncEditAvailability);
    else if (mq.addListener) mq.addListener(syncEditAvailability);
  } catch (e) {}

  window.applyPanelDensity = function () {
    columnPanels().forEach(applyDensity);
  };
  if (densityRo) {
    /* RO zyje do konca sesji — panele nie sa usuwane, tylko ukrywane. */
  }
})();
