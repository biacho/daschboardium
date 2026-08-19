# Dashbordium

**1.0-beta**

Aplikacja webowa na iPada — fullscreen w przeglądarce albo dodana do ekranu głównego.

Zegar, kalendarz, wydarzenia z iCal, pogoda, status internetu i domen. Działa tylko w sieci lokalnej — nie wystawiaj jej na internet.

## Uruchomienie

Potrzebujesz PHP 8.1+ (php-fpm, `curl`, `openssl`), nginx i Composera.

```bash
git clone https://github.com/biacho/dashbordium.git
cd dashbordium
composer install
php install.php
```

Skopiuj [`deploy/nginx.conf.example`](deploy/nginx.conf.example), podstaw ścieżki i `server_name`, potem:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

## Moduły

| Moduł | Co pokazuje |
|---|---|
| Internet | czy jest sieć i od ilu trwa awaria |
| Zegar | czas lokalny, data, pomodoro |
| Kalendarz | bieżący miesiąc |
| Wydarzenia | dziś i jutro z publicznych `.ics` |
| Odliczanie | do najbliższego wydarzenia |
| Pogoda | Open-Meteo |
| Domeny | HTTP, DNS, SSL, wygaśnięcie rejestracji |
| Last.fm | teraz leci / ostatnie scrobble, opcjonalnie follow |
| TIDAL Player | odtwarzacz na iPadzie (kolekcja + play/pauza/next) |

W landscape: menu → **Edytuj układ** — przeciągasz moduły między kolumnami (jak ikony na iOS). Gdy kolumna się ściska, zawartość modułu sama się dopasowuje.

## Licencja

[MIT](LICENSE)
