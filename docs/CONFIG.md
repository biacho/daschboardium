# Konfiguracja dashboardu

Ustawienia siedzą w **`config/config.php`** (poza gitem). W repo jest tylko
`config/config.example.php`. Na świeżej instalacji: `php install.php`, potem modal
przy pierwszym otwarciu kiosku. Później — koło zębate albo `?config=1`.

Modal ma listę modułów i osobny widok dla każdego. **Aktywny** zdejmuje
moduł; kolejność zmienisz w menu → Edytuj układ.

Po zapisie odśwież stronę. Cache unieważnia się, gdy `config.php` jest nowszy
niż plik w `var/`.

---

## Pliki

| Plik | Rola |
|------|------|
| `config/config.php` | Magazyn (sekrety iCal + reszta). nginx zwraca 404. |
| `config/config.example.php` | Szablon do `install.php`. |
| `api/config-js.php` | Publiczna część do przeglądarki (`window.APP_CONFIG`). Nigdy iCal. |
| `api/get-config.php` / `save-config.php` | Modal. iCal jest w odpowiedzi — tylko LAN. |
| `api/get-events.php` | Scala kalendarze. |
| `api/check-domains.php` | HTTP / DNS / SSL / RDAP. |
| `api/lastfm.php` | Now playing / ostatnie scrobble. |
| `api/lastfm-friends.php` | Lista znajomych Last.fm (select w modalu). |
| `api/tidal.php` | Kolekcja TIDAL (po OAuth). |
| `api/tidal-auth.php` / `tidal-callback.php` | OAuth 2.1 + PKCE. |
| `api/save-layout.php` | Kolejność modułów w kolumnach (tryb edycji siatki). |

Frontend nie czyta PHP — pogoda, widoczność i układ modułów idą przez `api/config-js.php`.

---

## Układ modułów

W landscape (3 kolumny) menu → **Edytuj układ**. Moduły można chwycić i włożyć w inną kolumnę albo zmienić kolejność. Internet zostaje belką u góry.

Gdy w kolumnie robi się ciaśniej, każdy moduł sam ścina padding, czcionki i mniej ważne dane (np. wiatr w pogodzie). Last.fm, pogoda, zegar i odliczanie biorą wysokość z zawartości — resztę kolumny zabierają siatka kalendarza, lista wydarzeń, TIDAL. Jeden moduł w kolumnie wypełnia ją całą.

`LAYOUT` w `config.php` to trzy listy id: `left`, `mid`, `right`. Brak klucza = domyślny układ.

---

## Pogoda

Miasto to podpis modułu. Współrzędne: przycisk **Szukaj** w modalu albo
ręcznie z mapy (prawy klik → pierwsza liczba `LAT`, druga `LON`).

---

## Kalendarze

Każdy wpis: nazwa, publiczny link **`.ics`**, kolor paska na liście.

- **iCloud:** udostępnij kalendarz → skopiuj link → `webcal://` zamień na `https://` (modal robi to sam).
- **Outlook / Microsoft 365:** Ustawienia → Kalendarz → Publikuj → link **`.ics`**, nie `.html` (`.html` zwraca 417).
- Inny feed iCal też działa.
- Lista pokazuje tylko **dziś i jutro**.

Link iCal = odczyt całego kalendarza. Nie commituj `config.php`.

---

## Domeny

Wystarczy nazwa (`example.pl`). Opcjonalnie:

- `url` — co odpytywać (domyślnie `https://<domena>/`)
- `expect_a` — oczekiwane IPv4 rekordu A
- `expect_mx` — fragment oczekiwanego hosta MX

| Plakietka | Znaczenie |
|-----------|-----------|
| `WWW` | kod HTTP + czas. Czerwone przy błędzie lub kodzie ≥ 400. |
| `A` / `MX` | czy rekordy są (DoH Google, nie lokalny resolver). |
| `CAA` | obecność CAA. **Brak nie jest błędem** — szary, nie czerwony. |
| `SSL` | dni do wygaśnięcia certyfikatu. Żółte poniżej 14 dni. |
| `Domena` | dni do wygaśnięcia rejestracji (RDAP). Żółte poniżej 30 dni. |
| `Rozjazd` | `expect_a` / `expect_mx` nie zgadza się z DNS. |

Kropka: zielona = OK, żółta = uwaga, czerwona = błąd.

---

## Last.fm

Klucz z [last.fm/api/account/create](https://www.last.fm/api/account/create)
i nick konta, które scrobbluje. W Apple Music / TIDAL włącz scrobbling —
moduł pokazuje, co leci (z kilkunastosekundowym opóźnieniem). Bez sterowania.

Opcjonalnie **Obserwowany** (`LASTFM_FRIEND`): jedna osoba z listy znajomych
Last.fm (follow). Lista ładuje się w modalu; **Odśwież** pobiera ją na nowo.
Moduł doda jej now playing pod Twoim. Profil musi być publiczny — klucz API
wystarczy, logowanie tamtej osoby nie jest potrzebne.

`LASTFM_CACHE_TTL` (domyślnie 20 s) jest tylko w `config.php`.

---

## TIDAL

Moduł **TIDAL Player** odtwarza na iPadzie przez oficjalne Player SDK
(play / pauza / poprzedni / następny, pasek postępu, kolejka z kolekcji).
Nie steruje aplikacją na telefonie — TIDAL nie daje publicznego Connect API.

Po tej zmianie kliknij **Połącz** jeszcze raz. Zakresy OAuth: `user.read collection.read`
(stare `r_usr` / `w_usr` na nowym portalu dają błąd **11102**).

1. Aplikacja na [developer.tidal.com](https://developer.tidal.com/).
2. Client ID i Secret w modalu.
3. Redirect URI z pola w konfiguracji wklej 1:1 w dashboardzie TIDAL.
4. **Połącz** — OAuth 2.1 + PKCE. Token odświeża PHP, siedzi w `var/` (nie w `config.php`).

Z iPada na HTTP w LAN redirect zwykle nie przejdzie. Połącz z maszyny, na której
stoi PHP, przez `http://127.0.0.1/.../api/tidal-callback.php`.

`TIDAL_CACHE_TTL` (domyślnie 300 s) jest tylko w `config.php`.

---

## Limity Claude Code / Grok (opcjonalnie)

Moduł pokazuje procent limitu planu — to samo co `/usage` w CLI.
Procenty liczy serwer dostawcy, więc obejmują wszystkie klienty na koncie.

Dane zbiera **`usage-snapshot.php`** jako proces użytkownika (nie php-fpm):

```bash
# jednorazowo
php usage-snapshot.php

# albo unit z deploy/dashboard-usage.service.example
systemctl --user daemon-reload
systemctl --user enable --now dashboard-usage
loginctl enable-linger "$USER"
```

php-fpm nie czyta `~/.claude` / `~/.grok` (`700`/`600`) i nie powinien —
tam są transkrypty i token OAuth. Do katalogu WWW idą wyłącznie liczby.
Skrypt odmawia HTTP (403). W przykładzie moduł jest wyłączony.

Tokenów **nie odświeżamy do katalogu WWW**. Endpointy usage bywają nieudokumentowane
— przy awarii moduł trzyma ostatnią wartość.

---

## TTL-e (w `config.php`, nie w modalu)

```php
'CACHE_TTL'         => 300,   // scalony wynik kalendarzy (s)
'DAYS_AHEAD'        => 2,     // okno pobierania; lista i tak tnie do dziś+jutro
'DOMAINS_CACHE_TTL' => 300,   // HTTP + DNS + SSL
'DOMAINS_RDAP_TTL'  => 86400, // data wygaśnięcia domeny; nie schodź poniżej doby
```

---

## Bezpieczeństwo

- Nie wpisuj linków iCal do `api/config-js.php`.
- `config/`, `var/`, `bin/`, `lib/` — deny w nginx (wzór: `deploy/nginx.conf.example`).
- Pliki serwowane: `chmod 644` (czytelne dla usera `http`).
- php-fpm nie tworzy plików — tylko nadpisuje istniejące `666`. Dlatego jest `install.php`.
