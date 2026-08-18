# Dashbordium

**1.0-beta**

Kiosk na iPada — fullscreen w Safari albo jako aplikacja z ekranu głównego.

Zegar, kalendarz, wydarzenia z iCloud / Outlook / dowolnego `.ics`, pogoda, status internetu, monitoring domen. Opcjonalnie limity Claude Code i Grok. Konfiguracja z przeglądarki, bez edycji plików po pierwszym `install.php`.

Czysty HTML / CSS / JS, bez bundlera i bez frameworka. PHP tylko do kalendarzy, domen i zapisu ustawień.

> **Tylko sieć lokalna.** Linki iCal dają odczyt całego kalendarza, a panel ustawień je zwraca. Przykładowy nginx wpuszcza RFC1918 i zamyka resztę. Nie wystawiaj tego na internet.

[Wymagania](#wymagania) · [Instalacja](#instalacja) · [Pierwsze uruchomienie](#pierwsze-uruchomienie) · [Kafelki](#kafelki) · [Bezpieczeństwo](#bezpieczeństwo)

## Wymagania

- PHP 8.1+ z php-fpm (rozszerzenia `curl`, `openssl`)
- nginx
- Composer
- host w LAN (nie publiczny interfejs)

## Instalacja

```bash
git clone https://github.com/<konto>/dashbordium.git
cd dashbordium
composer install
php install.php
```

`php install.php` (albo `php bin/install.php`) działa **tylko z CLI**. Kopiuje `config/config.example.php` → `config/config.php`, tworzy puste pliki w `var/` i ustawia `chmod 666`, żeby php-fpm mógł je nadpisać. Istniejącego `config.php` nie rusza.

Skopiuj [`deploy/nginx.conf.example`](deploy/nginx.conf.example), podstaw ścieżki i `server_name`:

```bash
sudo cp deploy/nginx.conf.example /etc/nginx/sites-enabled/dashbordium
# edytuj alias / SCRIPT_FILENAME
sudo nginx -t && sudo systemctl reload nginx
```

Otwórz kiosk w przeglądarce. Na iPadzie: Udostępnij → **Dodaj do ekranu początkowego**.

## Pierwsze uruchomienie

Brak `config/config.php` pokazuje kartę z komendą `php install.php` — nie 500.

Po instalacji kiosk otwiera konfigurację i nie da się jej zamknąć, dopóki nie zapiszesz **miasta** (pogoda). Kalendarze i domeny mogą zostać puste.

Później: koło zębate w menu albo `?config=1`.

## Kafelki

Każdy da się wyłączyć w ustawieniach. Sąsiedzi biorą jego miejsce.

| Kafelek | Co pokazuje |
|---|---|
| Internet | czy jest sieć, od ilu trwa awaria |
| Zegar | czas lokalny, data, presety pomodoro |
| Kalendarz | bieżący miesiąc, tydzień od poniedziałku |
| Wydarzenia | dziś i jutro z publicznych feedów `.ics` |
| Odliczanie | do najbliższego wydarzenia |
| Pogoda | Open-Meteo (wyszukiwanie miasta w ustawieniach) |
| Domeny | HTTP, DNS (DoH), SSL, wygaśnięcie rejestracji (RDAP) |
| Limity | zużycie planu Claude Code / Grok — **opcjonalnie** |

### Limity (opcjonalnie)

Wyłączone w przykładzie. Procenty liczy serwer dostawcy (`/usage` z CLI), więc obejmują wszystkie klienty na koncie — nie tylko tę maszynę.

Snapshot zbiera `usage-snapshot.php` jako **Twój** użytkownik, nie php-fpm. php-fpm nie czyta `~/.claude` / `~/.grok` i nie powinien: tam są transkrypty i token.

```bash
# kopia deploy/dashboard-usage.service.example → ~/.config/systemd/user/
systemctl --user daemon-reload
systemctl --user enable --now dashboard-usage
loginctl enable-linger "$USER"
```

Szczegóły: [docs/CONFIG.md](docs/CONFIG.md).

## Bezpieczeństwo

Nie commituj i nie serwuj z weba:

| Ścieżka | Dlaczego |
|---|---|
| `config/config.php` | linki iCal |
| `var/cache_*.json` | wydarzenia, domeny, zużycie planu |
| `vendor/` | zależność Composera |
| `bin/` | tylko CLI |

Przykładowy nginx zwraca 404 na `config/`, `var/`, `bin/`, `lib/`. Endpointy w `api/` są dostępne, bo **cały** vhost jest LAN-only.

## Stack

```
index.php              wejście
assets/css|js|img/     chrome
tiles/<nazwa>/         kafelek = html + css + js
api/                   endpointy JSON
lib/                   load-config, ścieżki
config/                ustawienia (config.php poza gitem)
var/                   cache
bin/                   install + snapshot limitów
docs/                  CONFIG.md
```

PHP 8.1, jedna zależność: [`johngrogg/ics-parser`](https://github.com/u01jmg3/ics-parser).

## Licencja

[MIT](LICENSE)
