<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class WeatherService
{
    /**
     * @return array{location: string, temperatureC: float, feelsLikeC: float, humidity: int, windKph: float, weatherCode: int, observedAt: string}
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws UnexpectedValueException
     */
    public function current(): array
    {
        return Cache::remember('dashboard.weather.current', now()->addMinutes(10), function (): array {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(4)
                ->get(config('services.weather.endpoint'), [
                    'latitude' => config('services.weather.latitude'),
                    'longitude' => config('services.weather.longitude'),
                    'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m',
                    'timezone' => config('services.weather.timezone'),
                ])
                ->throw();

            $current = $response->json('current');

            if (! is_array($current)
                || ! is_numeric($current['temperature_2m'] ?? null)
                || ! is_numeric($current['apparent_temperature'] ?? null)
                || ! is_numeric($current['relative_humidity_2m'] ?? null)
                || ! is_numeric($current['weather_code'] ?? null)
                || ! is_numeric($current['wind_speed_10m'] ?? null)
                || ! is_string($current['time'] ?? null)) {
                throw new UnexpectedValueException('The weather API returned an invalid current conditions payload.');
            }

            return [
                'location' => (string) config('services.weather.location'),
                'temperatureC' => (float) $current['temperature_2m'],
                'feelsLikeC' => (float) $current['apparent_temperature'],
                'humidity' => (int) $current['relative_humidity_2m'],
                'windKph' => (float) $current['wind_speed_10m'],
                'weatherCode' => (int) $current['weather_code'],
                'observedAt' => $current['time'],
            ];
        });
    }
}
