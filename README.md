# Dashboardium

Kiosk na iPada (Safari albo aplikacja z ekranu głównego): zegar, kalendarz, wydarzenia z iCal, pogoda, status internetu, monitoring domen, opcjonalnie limity Claude Code / Grok.

Czysty HTML/CSS/JS, bez bundlera. Backend PHP tylko do kalendarzy, domen i zapisu konfiguracji.

**Tylko LAN.** URL-e iCal to sekrety (dają odczyt całego kalendarza). `get-config.php` oddaje je do przeglądarki — dlatego nginx ma `allow` sieci prywatnych i `deny all`. Nie wystawiaj tego na internet.

## Wymagania

- PHP 8.1+ z php-fpm (`curl`, `openssl`)
- nginx
- Composer
- sieć lokalna (RFC1918)

## Instalacja

```bash
git clone <repo> dashboardium
cd dashboardium
composer install
php install.php
```

`install.php` (tylko CLI) kopiuje `config.example.php` → `config.php`, tworzy puste pliki cache i ustawia `chmod 666`, żeby php-fpm mógł je nadpisać. **Nie rusza** istniejącego `config.php`.

Skopiuj `deploy/nginx.conf.example`, podstaw ścieżki i `server_name`, potem:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Otwórz kiosk. Przy pierwszym uruchomieniu modal nie da się zamknąć, dopóki nie zapiszesz przynajmniej miasta (pogoda). Kalendarze i domeny mogą zostać puste.

## Konfiguracja

Koło zębate w menu albo `?config=1`. How-to pól: [CONFIG.md](CONFIG.md).

`config.php` jest poza gitem. W repo jest tylko `config.example.php`.

## Kafelki

| Kafelek | Co robi |
|---|---|
| Internet | ping `generate_204`, czas awarii |
| Zegar | czas lokalny + pomodoro |
| Kalendarz | miesiąc, poniedziałek pierwszy |
| Wydarzenia | dziś i jutro z publicznych `.ics` |
| Odliczanie | do najbliższego wydarzenia |
| Pogoda | Open-Meteo |
| Domeny | HTTP, DNS (DoH), SSL, RDAP |
| Limity | opcjonalnie; wymaga demona CLI |

Kafelek limitów jest **wyłączony** w przykładzie. Czyta `~/.claude` i `~/.grok` procesem użytkownika, nie php-fpm — patrz `deploy/dashboard-usage.service.example` i CONFIG.md.

## Bezpieczeństwo

Nie commituj i nie serwuj:

- `config.php` (linki iCal)
- `cache_*.json` (wydarzenia, domeny, zużycie planu)
- `vendor/`

Przykładowy nginx zwraca 404 także na `install.php` i `usage-snapshot.php` (i tak odmawiają HTTP).

## Licencja

MIT — [LICENSE](LICENSE).
