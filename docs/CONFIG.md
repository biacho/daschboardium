# Konfiguracja dashboardu

Ustawienia siedzą w **`config/config.php`** (poza gitem). W repo jest tylko
`config/config.example.php`. Na świeżej instalacji: `php install.php`, potem modal
przy pierwszym otwarciu kiosku. Później — koło zębate albo `?config=1`.

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

Frontend nie czyta PHP — pogoda i widoczność kafelków idą przez `api/config-js.php`.

---

## Pogoda

Miasto to podpis kafelka. Współrzędne: przycisk **Szukaj** w modalu albo
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

## Limity Claude Code / Grok (opcjonalnie)

Kafelek pokazuje procent limitu planu — to samo co `/usage` w CLI.
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
Skrypt odmawia HTTP (403). W przykładzie kafelek jest wyłączony.

Tokenów **nie odświeżamy do katalogu WWW**. Endpointy usage bywają nieudokumentowane
— przy awarii kafelek trzyma ostatnią wartość.

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
