const LASTFM_ENDPOINT = 'api/lastfm.php';

function lastfmInitials(title, artist) {
  const src = (artist || title || '?').trim();
  return (src[0] || '?').toUpperCase();
}

function lastfmArt(url, title, artist, cls, phCls) {
  if (url) {
    return `<img class="${cls}" src="${escapeAttr(url)}" alt="" width="88" height="88">`;
  }
  return `<div class="${phCls}" aria-hidden="true">${escapeHtml(lastfmInitials(title, artist))}</div>`;
}

function lastfmAgo(uts) {
  if (!uts) return '';
  const mins = Math.max(0, Math.round((Date.now() / 1000 - uts) / 60));
  if (mins < 1) return 'przed chwilą';
  if (mins < 60) return mins + ' min temu';
  const h = Math.floor(mins / 60);
  if (h < 24) return h + ' h temu';
  const d = Math.floor(h / 24);
  return d + ' d temu';
}

function lastfmNowBlock(track, badge, extraCls) {
  const live = !!(track && track.nowPlaying);
  const cls = extraCls ? ('lastfm-now ' + extraCls) : 'lastfm-now';
  return `<div class="${cls}">`
    + lastfmArt(track.image, track.title, track.artist, 'lastfm-art', 'lastfm-art-ph')
    + `<div class="lastfm-meta">`
    + `<div class="lastfm-badge${live ? '' : ' is-last'}">${escapeHtml(badge)}</div>`
    + `<div class="lastfm-title">${escapeHtml(track.title)}</div>`
    + `<div class="lastfm-artist">${escapeHtml(track.artist || track.album || '')}</div>`
    + `</div></div>`;
}

function renderLastfm(d) {
  const main = document.getElementById('lastfmMain');
  const userEl = document.getElementById('lastfmUser');
  if (!main) return;

  if (userEl) {
    userEl.textContent = d.user || '';
  }

  if (d.error) {
    main.innerHTML = `<div class="lastfm-empty">${escapeHtml(d.error)}</div>`;
    return;
  }

  const tracks = Array.isArray(d.tracks) ? d.tracks : [];
  const now = tracks.find((t) => t && t.nowPlaying) || tracks[0];
  if (!now || !now.title) {
    main.innerHTML = '<div class="lastfm-empty">Brak scrobble’y</div>';
    return;
  }

  const live = !!now.nowPlaying;
  const badge = live ? 'Teraz' : ('Ostatnio · ' + lastfmAgo(now.playedAt));
  let html = lastfmNowBlock(now, badge);

  const friend = (d.friend || '').trim();
  const friendTracks = Array.isArray(d.friendTracks) ? d.friendTracks : [];
  const friendNow = friendTracks.find((t) => t && t.nowPlaying) || friendTracks[0];
  if (friend) {
    if (d.friendError) {
      html += `<div class="lastfm-friend-empty">Follow · ${escapeHtml(friend)} — ${escapeHtml(d.friendError)}</div>`;
    } else if (friendNow && friendNow.title) {
      const fLive = !!friendNow.nowPlaying;
      const fBadge = (fLive ? 'Teraz · ' : 'Ostatnio · ') + friend
        + (fLive ? '' : (' · ' + lastfmAgo(friendNow.playedAt)));
      html += lastfmNowBlock(friendNow, fBadge, 'lastfm-friend');
    } else {
      html += `<div class="lastfm-friend-empty">Follow · ${escapeHtml(friend)} — nic nie leci</div>`;
    }
  }

  if (!friend) {
    const rest = tracks.filter((t) => t && t !== now && t.title).slice(0, 3);
    if (rest.length) {
      html += '<div class="lastfm-recent">';
      rest.forEach((t) => {
        html += `<div class="lastfm-row">`
          + lastfmArt(t.image, t.title, t.artist, 'lastfm-row-art', 'lastfm-row-ph')
          + `<div class="lastfm-row-txt">${escapeHtml(t.title)}`
          + (t.artist ? ` <span class="who">· ${escapeHtml(t.artist)}</span>` : '')
          + `</div></div>`;
      });
      html += '</div>';
    }
  }

  main.innerHTML = html;
}

async function fetchLastfm() {
  const main = document.getElementById('lastfmMain');
  if (!main) return;
  try {
    const res = await fetch(LASTFM_ENDPOINT, { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const d = await res.json();
    renderLastfm(d);
  } catch (e) {
    renderLastfm({ error: 'Brak danych Last.fm' });
  }
}

if (panelOn('lastfm')) {
  fetchLastfm();
  setInterval(fetchLastfm, 25 * 1000);
}
