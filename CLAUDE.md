# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A zero-dependency kiosk dashboard intended to run fullscreen in an iPad's Safari
(or as a home-screen web app). UI text is in Polish.

The frontend is plain vanilla JS/CSS (no bundler). `index.php` is the shell:
shared chrome in `assets/css/style.css` / `assets/js/scripts.js`, each module in `modules/<name>/<name>.{html,css,js}`.
`index.php` includes the HTML fragments and links CSS/JS with a cache-busting
timestamp. Entry is `index.php` (there is no `index.html`). There is
a small PHP backend for two features only — pulling iCloud/iCal calendar events
(`get-events.php` + `load-config.php`, depends on `johngrogg/ics-parser` via Composer)
and checking domain health (`check-domains.php`, no dependencies).
There are no tests. To work on the frontend, edit the relevant file and reload in a
browser; the events panel additionally requires the page to be served over HTTP by
PHP-FPM (not `file://`), because it `fetch()`es `get-events.php` same-origin. The
`apple-mobile-web-app-*` meta tags and `viewport-fit=cover` exist specifically for
the iOS standalone/kiosk use case. A floating `↻` refresh button (`#refreshBtn` in
`scripts.js`) hard-reloads via `location.replace(pathname + '?_=' + Date.now())`.

**Two fragility traps live here:**
- *All modules run top-level and sequentially*, so one `getElementById(id)` returning
  `null` (e.g. a panel element edited out / commented out in `modules/<name>/<name>.html`) throws and
  **kills every module after it** — the whole dashboard freezes. This actually
  happened when `#clockDate` was commented out and `updateClock` set `.textContent` on
  `null`. Guard DOM writes for optional elements (`const el = ...; if (el) el...`).
- *nginx serves the static files with no `Cache-Control`* and iOS standalone caches
  `style.css`/`scripts.js` heuristically. To avoid serving stale assets during
  development, `index.php` links `assets/css/style.css`, `assets/js/scripts.js` and `modules/<name>/<name>.{css,js}` with a per-load timestamp. (A server-side alternative is `Cache-Control: no-store` on the dashboard nginx location.)

## Backend: iCloud calendar events

`get-events.php` fetches one or more public iCal URLs from iCloud (and other iCal
providers — e.g. published Outlook/Microsoft 365 `.ics` feeds), parses each with
`ICal\ICal`, **merges** all events, sorts them chronologically, and returns JSON.
Events are filtered to **today and tomorrow only** (computed in `Europe/Warsaw`);
`DAYS_AHEAD` is the fetch window, but the precise day filter is what cuts off
"pojutrze". The result is cached to `cache_events.json` for `CACHE_TTL` seconds so
the sources are not hit on every kiosk refresh. A single failing calendar does not
break the response (caught per-calendar via `\Throwable`); only if *every* calendar
fails and produces nothing does it fall back to the stale cache (and it won't cache
an all-error result). All config lives in `config/config.php` (gitignored; ship
`config/config.example.php`). Scripts read it through `lib/load-config.php` — missing file
returns JSON 503 instead of a fatal.

- Microsoft/Outlook published calendars expose both a `.html` (web view) and a
  `.ics` (feed) link — always use the **`.ics`** one; the `.html` URL returns
  `417 Expectation Failed` to PHP and parses to nothing.

- `composer require johngrogg/ics-parser` provides `vendor/` — required, the script
  `require`s `vendor/autoload.php`. NB: this version has no `isAllDayEvent()`; all-day
  is detected inline by DTSTART being a DATE value (no `T` time part).
- Calendars live in `config.php` under `ICAL_URLS` — a name⇒`['url'=>…, 'color'=>…]`
  map. `color` tints the event's left bar on the kiosk (passed through as `ev.color`).
  Entries whose URL still contains `ZAMIEN-NA-LINK` are skipped as unfilled
  placeholders. Published iCloud links are `webcal://` → `https://`. (A legacy single
  `ICAL_URL` string is still honored as a fallback.)
- The iCal URLs are effectively secrets (read access to whole calendars). `config.php`
  and `cache_events.json` must NOT be web-accessible — this is enforced in nginx
  (deny rules), since this is nginx, not Apache (no `.htaccess`).

## Backend: domain monitoring

`check-domains.php` checks each domain in `config.php`'s `DOMAINS` map and returns
JSON for the "Status domen" panel. Per domain it does four things:

- **HTTP** — cURL GET (follows redirects), reports code + total time in ms.
- **DNS** — A / MX / CAA via **DNS-over-HTTPS** (`https://dns.google/resolve?…`)
  rather than `dns_get_record()`, so results don't come from the host resolver's
  cache. A missing CAA record is *not* an error (it just means no issuance
  restriction) — it renders as a neutral grey badge, never red.
- **SSL** — days until cert expiry via `stream_socket_client('ssl://…')` +
  `openssl_x509_parse`. `verify_peer` is deliberately **false** so an already-expired
  cert still reports its date instead of failing the handshake.
- **Domain expiry** — RDAP (JSON successor to WHOIS): `https://rdap.org/domain/<d>`
  bootstraps via a 302 to the right registry (hence `CURLOPT_FOLLOWLOCATION`), with
  `https://rdap.dns.pl/domain/<d>` as a `.pl` fallback.

Each domain gets a rolled-up `status` (`ok` / `warn` / `error`) computed **in PHP** —
`scripts.js` only renders it. Optional `expect_a` / `expect_mx` per domain flag DNS
drift as `warn`.

Two caches: `cache_domains.json` (`DOMAINS_CACHE_TTL`, 5 min) for the whole result,
and `cache_domains_rdap.json` (`DOMAINS_RDAP_TTL`, 24 h) for RDAP alone — registries
rate-limit and expiry dates change once a year. The RDAP cache is written once per
request via `register_shutdown_function`, and a registry timeout keeps the previous
value rather than blanking it. Both cache files follow the same TTL-plus-mtime
invalidation as `cache_events.json` (a newer `config.php` busts the cache immediately).

⚠️ **php-fpm (user `http`) cannot create files in this directory** — it can only
overwrite ones that already exist and are `chmod 666`. `php install.php` (CLI, as
the deploy user) creates `config/config.php` from the example plus empty `var/`
cache files and chmods them. Without that, `file_put_contents` silently fails
and every kiosk request pays the full ~1.5 s of network checks.

## Backend: Claude Code usage / plan limits

The "Limit Claude Code" panel answers *how much of my plan is left*. Two data
sources, both collected by **`usage-snapshot.php`, which runs as a long-lived PHP
daemon under the user's own systemd instance** — unit example
`deploy/dashboard-usage.service.example`, `ExecStart=… --loop=60`,
`Restart=always`, with `loginctl enable-linger $USER` so it survives reboot and
logout. It writes `var/cache_usage.json`; `api/get-usage.php` only serves that file and adds
`ageSeconds`/`stale` (stale after 10 min). Run it without `--loop` for a one-shot
refresh. The daemon handles SIGTERM/SIGINT so `systemctl --user restart` is clean,
and `emptyBucket()` **must stay outside `runSnapshot()`** — redeclaring a function on
the loop's second pass is a fatal error. The split is a **permission
boundary, not a scheduling preference**: php-fpm runs as `http` and cannot read
`~/.claude` (`700`/`600`) — and must not, since transcripts hold full conversation
text and `.credentials.json` holds an OAuth token to the user's account. Making the
endpoint self-refreshing would mean exposing that token to every PHP script on the
host; don't "simplify" it that way. Only aggregate numbers reach the web directory,
and `usage-snapshot.php` refuses to run unless `PHP_SAPI === 'cli'` (403 over HTTP).

- **Plan limits (the percentages)** — `GET https://api.anthropic.com/api/oauth/usage`
  with the OAuth access token from `~/.claude/.credentials.json` plus header
  `anthropic-beta: oauth-2025-04-20`. This is the same endpoint the `/usage` command
  uses; the path was found in the CLI bundle at
  `~/.local/share/claude/versions/<v>`. Response gives `five_hour.utilization`,
  `seven_day.utilization`, `resets_at`, and `spend.used` (extra-usage credits, minor
  units + `exponent`). ⚠️ **Internal, undocumented endpoint** — treat breakage as
  expected; on any failure the script keeps the previous `plan` block from the old
  snapshot and sets `stalePlan`.
  **Why this and not local counting:** the percentages are computed server-side, so
  they cover *every* client on the account (two editors, the macOS app, claude.ai).
  Anything derived from local `.jsonl` files only ever sees this one machine. That
  distinction is the whole reason the panel exists — don't "simplify" it back to
  local-only math.
  The token is **read-only** here: never refresh it (that could invalidate the CLI
  session) and never write it anywhere under the web root.
- **Local token counts (the footer)** — parses `~/.claude/projects/*/*.jsonl`,
  taking `type: "assistant"` records' `message.usage`, deduped on
  `requestId|message.id` (the same response appears on several lines). Buckets:
  rolling 5 h, today, 7 days, in `Europe/Warsaw`. Cost is an API-equivalent estimate
  from the `PRICING` constant in the script (cache writes 1.25× input for 5 m TTL and
  2× for 1 h TTL, cache reads 0.1×) — not what the subscription actually bills.

### Config bridge to the frontend

`config/config.php` is the single config file (PHP array — deliberately not `.env`, which
would need a parser this zero-framework project doesn't have). It is gitignored;
`config/config.example.php` ships in the repo. `SETUP_COMPLETE === false` (or a missing
config file) forces the existing settings modal as first-run; a missing
`SETUP_COMPLETE` key means an already-configured install. The static `scripts.js`
can't read PHP, so `api/config-js.php` reads config server-side and echoes **only the
public keys** (weather, panel flags, `setupComplete`) as `window.APP_CONFIG = {...}`
with `Content-Type: application/javascript` + `Cache-Control: no-store`. `index.php`
loads `<script src="api/config-js.php">` **before** `assets/js/scripts.js`. ⚠️ Never echo
`ICAL_URLS` (or anything secret) from `config-js.php` — it goes straight to the
browser. `api/config-js.php` is intentionally *not* in the nginx deny list (unlike
`config/`), so it must stay secret-free. End-user how-to lives in `docs/CONFIG.md`.

## Deployment (nginx)

LAN-only. Copy `deploy/nginx.conf.example` and adjust root / `server_name`.
Isolation is `allow` RFC1918 + `deny all` — do **not** `listen` on a LAN IP
(that splits nginx sockets and breaks other vhosts / HTTP-01). PHP via
`fastcgi_pass unix:/run/php-fpm/php-fpm.sock`. Deny `config/`, `var/`, `bin/`,
`lib/`, `vendor/`, `composer.*`, `install.php`. Reload:
`sudo nginx -t && sudo systemctl reload nginx`.

## Architecture

`index.html` is markup only; all behaviour is in `scripts.js`, all styling in
`style.css`. It renders five `.panel`s in a CSS grid (`.kiosk`), each driven by an
independent vanilla-JS module. Each module updates the DOM by element `id` and
re-runs itself on its own `setInterval`:

- **Clock** (`updateClock`, 1s) — local time + Polish long-form date.
- **Calendar** (`renderCalendar`, 60s) — current month grid, Monday-first
  (`getDay()` is remapped so Monday = 0). Also re-rendered each minute so "today"
  rolls over at midnight.
- **Weather** (`fetchWeather`, 15m) — Open-Meteo `current=…&daily=…` API. Coordinates
  and city come from `config.php` (`WEATHER_LAT`/`LON`/`CITY`) via `window.APP_CONFIG`
  (see config bridge below), with a Wrocław fallback. `weather_code` → emoji via
  `weatherIcons`; the wide panel also shows a `#weatherStats` grid (apparent temp,
  humidity, wind, precip probability).
- **Internet status** (`checkInternet`, 5s) — a `no-cors` fetch to
  `https://www.gstatic.com/generate_204`; success/failure toggles the panel's
  visual state and tracks an outage start time (`downSinceTimestamp`) to display
  outage duration.
- **Domain status** (`fetchDomains`, 5m) — fetches `check-domains.php` (same-origin
  via `DOMAINS_ENDPOINT`) and renders one row per domain: a status dot + name above
  a row of badges (WWW / A / MX / CAA / SSL / Rozjazd). Days until *registration*
  expiry are a bare number in parentheses after the domain name (`example.pl (364)`)
  rather than a badge, and SSL shows a bare day count — the tile is space-constrained
  and the user reads those unlabelled by design. The panel is purely presentational;
  the ok/warn/error decision comes from PHP.
- **Events** (`fetchEvents`, 5m) — fetches `get-events.php` (same-origin via
  `EVENTS_ENDPOINT`) and renders a flat chronological list, each item showing the
  time range above the title with a left bar tinted by the calendar's `color`. No
  day-group headers (the date lives in the adjacent calendar panel). Titles go
  through `escapeHtml()`, so no XSS from calendar content.

State is module-level globals; there is no framework, bundler, or shared state
layer. **Grid layout:** `grid-template-rows: 0.25fr 0.35fr 0.85fr 1fr` over three
columns, and **every panel is placed explicitly** (no auto-placement), so the 3×4
cell grid is exactly covered — nothing relies on flow order:

| | col 1 | col 2 | col 3 |
|---|---|---|---|
| row 1 | internet status (spans cols 1–2) | ← | clock (spans rows 1–2) |
| row 2 | Claude Code limit (spans rows 2–3) | calendar (spans rows 2–3) | ↑ |
| row 3 | ↑ | ↑ | events (spans rows 3–4) |
| row 4 | domain status | weather | ↑ |

When moving a panel, re-check that the 12 cells stay covered without overlap —
an unplaced panel gets auto-placed somewhere surprising instead of erroring.
Rows 2 and 3 are deliberately lopsided (`0.35fr` / `0.85fr`): that keeps the clock
(rows 1–2) flat while giving events (rows 3–4) more room, and it costs the limit
and calendar tiles nothing because both span rows 2–3, so their combined height is
unchanged. Resizing a tile that spans two rows is usually done this way rather than
by touching the total.
Panels are `overflow: hidden`, so a tile that outgrows its row is silently cropped
rather than pushing the layout — that's why `.domains-panel` overrides `padding`
and the domain rows are deliberately compact. The whole thing collapses to one column via a
`max-aspect-ratio: 1/1` media query (which also resets the multi-column spans).

## Conventions

- The frontend is `index.php` + `assets/` + `modules/<moduł>/`
  (html, css, js; no bundler) — keep modules in their own folders; don't re-inline.
  The PHP backend is deliberately minimal (calendar fetch only); don't grow it
  into a framework.
- New files served out of this dir must be readable by the `http` user — `chmod 644`
  (see the [[nginx-http-user-perms]] gotcha; `o+r` alone is not enough).
- Theme colors are CSS custom properties under `:root`; reuse them rather than
  hardcoding hex values.
- Font sizes use `clamp()` for fluid scaling across iPad sizes.
- To retarget the weather, use the settings modal (or `WEATHER_*` in `config.php`).
  The panel label `Pogoda · <city>` is set from `WEATHER_CITY` by `scripts.js`.
  All user-facing config lives in `config.php` / `CONFIG.md`.
