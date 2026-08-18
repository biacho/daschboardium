// Naglowek: "Pogoda · <miasto>"
{
  const lbl = document.getElementById('weatherLabel');
  if (lbl) lbl.textContent = 'Pogoda · ' + WEATHER_CITY;
}

const weatherIcons = {
  0: '☀️', 1: '🌤️', 2: '⛅', 3: '☁️',
  45: '🌫️', 48: '🌫️',
  51: '🌦️', 53: '🌦️', 55: '🌦️', 56: '🌦️', 57: '🌦️',
  61: '🌧️', 63: '🌧️', 65: '🌧️', 66: '🌧️', 67: '🌧️',
  71: '🌨️', 73: '🌨️', 75: '🌨️', 77: '🌨️',
  80: '🌧️', 81: '🌧️', 82: '🌧️',
  95: '⛈️', 96: '⛈️', 99: '⛈️'
};

async function fetchWeather() {
  try {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${WEATHER_LAT}&longitude=${WEATHER_LON}`
      + `&current=temperature_2m,apparent_temperature,relative_humidity_2m,wind_speed_10m,weather_code`
      + `&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max`
      + `&timezone=Europe%2FWarsaw`;
    const res = await fetch(url);
    const data = await res.json();

    const c = data.current;
    const temp  = Math.round(c.temperature_2m);
    const code  = c.weather_code;
    const feels = Math.round(c.apparent_temperature);
    const hum   = Math.round(c.relative_humidity_2m);
    const wind  = Math.round(c.wind_speed_10m);
    const tMin  = Math.round(data.daily.temperature_2m_min[0]);
    const tMax  = Math.round(data.daily.temperature_2m_max[0]);
    const pop   = data.daily.precipitation_probability_max?.[0];

    document.getElementById('weatherIcon').textContent = weatherIcons[code] || '☁️';
    document.getElementById('weatherTemp').textContent = `${temp}°`;
    document.getElementById('weatherSub').textContent = `Dziś od ${tMin}° do ${tMax}°`;

    const stat = (k, v) => `<div class="weather-stat"><span class="k">${k}</span><span class="v">${v}</span></div>`;
    document.getElementById('weatherStats').innerHTML =
        stat('Odczuwalna', `${feels}°`)
      + stat('Wilgotność', `${hum}%`)
      + stat('Wiatr', `${wind} km/h`)
      + stat('Opady', pop != null ? `${pop}%` : '—');
  } catch (e) {
    document.getElementById('weatherSub').textContent = 'Brak danych pogodowych';
    document.getElementById('weatherStats').innerHTML = '';
  }
}
if (panelOn('weather')) {
  fetchWeather();
  setInterval(fetchWeather, 15 * 60 * 1000); // co 15 minut
}
