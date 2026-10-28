<?php

namespace App\Services;

use App\Models\Trip;
use App\Support\DayWeather;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wettervorhersage pro Reisetag über Open-Meteo (frei nutzbar, ohne Schlüssel).
 * Übertragen wird nur das Reiseziel bzw. dessen Koordinaten – keine Personendaten.
 *
 * Die Vorhersage reicht 16 Tage voraus; weiter entfernte Tage bleiben ohne Wetter.
 * Fällt der Dienst aus, fehlt das Wetter einfach, die Seite funktioniert weiter.
 */
class WeatherService
{
    public const FORECAST_DAYS = 16;

    private const GEOCODING_URL = 'https://geocoding-api.open-meteo.com/v1/search';

    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    /**
     * days: Datum (Y-m-d) => Wetter; hint erklärt, warum (manche) Tage ohne Wetter sind.
     *
     * @return array{place: ?string, days: array<string, DayWeather>, hint: ?string}
     */
    public function forTrip(Trip $trip): array
    {
        $result = ['place' => null, 'days' => [], 'hint' => null];

        if (blank($trip->location)) {
            return [...$result, 'hint' => 'Mit einem Reiseziel zeigen wir hier das Wetter.'];
        }

        $place = $this->geocode($trip->location);
        if ($place === null) {
            return [...$result, 'hint' => 'Das Wetter ist gerade nicht abrufbar.'];
        }
        if ($place === false) {
            return [...$result, 'hint' => "„{$trip->location}“ haben wir nicht gefunden – mit einem Ortsnamen klappt das Wetter."];
        }
        $result['place'] = $place['name'];

        $today = today();
        $last = $today->copy()->addDays(self::FORECAST_DAYS - 1);
        $from = $trip->start_date->max($today->copy()->subDays(30));
        $to = $trip->end_date->min($last);

        if ($from->gt($to)) {
            return [...$result, 'hint' => $trip->end_date->lt($today) ? null : 'Die Vorhersage gibt es ab '.self::FORECAST_DAYS.' Tagen vor der Reise.'];
        }

        $result['days'] = $this->forecast($place['lat'], $place['lon'], $from, $to);

        if ($result['days'] === []) {
            $result['hint'] = 'Das Wetter ist gerade nicht abrufbar.';
        } elseif ($trip->end_date->gt($last)) {
            $result['hint'] = 'Für spätere Tage gibt es noch keine Vorhersage.';
        }

        return $result;
    }

    /**
     * Ort zu Koordinaten. „Lissabon, Portugal“ wird als „Lissabon“ gesucht, weil der
     * Dienst nur Ortsnamen kennt.
     *
     * @return array{name: string, lat: float, lon: float}|false|null false: Ort unbekannt, null: Dienst nicht erreichbar
     */
    public function geocode(string $location): array|false|null
    {
        $query = trim(explode(',', $location)[0]);
        if (mb_strlen($query) < 2) {
            return false;
        }

        $key = 'weather:geo:'.md5(mb_strtolower($query));
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $hit = Http::timeout(4)->get(self::GEOCODING_URL, ['name' => $query, 'count' => 1, 'language' => 'de', 'format' => 'json'])
                ->throw()->json('results.0');
        } catch (Throwable $e) {
            Log::warning('Ortssuche für Wetter fehlgeschlagen', ['error' => $e->getMessage()]);

            return null; // nicht zwischenspeichern, beim nächsten Aufruf erneut versuchen
        }

        $place = $hit ? [
            'name' => collect([$hit['name'] ?? $query, $hit['country'] ?? null])->filter()->unique()->join(', '),
            'lat' => (float) $hit['latitude'],
            'lon' => (float) $hit['longitude'],
        ] : false;

        Cache::put($key, $place, now()->addDays(30));

        return $place;
    }

    /** @return array<string, DayWeather> */
    private function forecast(float $lat, float $lon, Carbon $from, Carbon $to): array
    {
        $params = [
            'latitude' => round($lat, 3),
            'longitude' => round($lon, 3),
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
            'timezone' => 'auto',
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ];

        $daily = Cache::get($key = 'weather:fc:'.md5(serialize($params)));

        if ($daily === null) {
            try {
                $daily = Http::timeout(4)->get(self::FORECAST_URL, $params)->throw()->json('daily') ?? [];
            } catch (Throwable $e) {
                Log::warning('Wettervorhersage fehlgeschlagen', ['error' => $e->getMessage()]);

                return [];
            }
            Cache::put($key, $daily, now()->addHours(3));
        }

        $days = [];
        foreach ($daily['time'] ?? [] as $i => $date) {
            if (! isset($daily['weather_code'][$i], $daily['temperature_2m_min'][$i], $daily['temperature_2m_max'][$i])) {
                continue;
            }
            $rain = $daily['precipitation_probability_max'][$i] ?? null;
            $days[$date] = new DayWeather(
                (int) $daily['weather_code'][$i],
                (int) round($daily['temperature_2m_min'][$i]),
                (int) round($daily['temperature_2m_max'][$i]),
                $rain === null ? null : (int) $rain,
            );
        }

        return $days;
    }
}
