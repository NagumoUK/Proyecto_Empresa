<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('renders and caches the current Lima weather for the dashboard', function () {
    Cache::forget('dashboard.weather.current');
    Http::preventStrayRequests();
    Http::fake([
        config('services.weather.endpoint').'*' => Http::response([
            'current' => [
                'temperature_2m' => 22.4,
                'apparent_temperature' => 23.1,
                'relative_humidity_2m' => 75,
                'weather_code' => 2,
                'wind_speed_10m' => 12.5,
                'time' => '2025-01-15T12:00',
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('weather.location', 'Lima, Perú')
            ->where('weather.temperatureC', 22.4)
            ->where('weather.feelsLikeC', 23.1)
            ->where('weather.humidity', 75)
            ->where('weather.windKph', 12.5)
            ->where('weather.weatherCode', 2)
        );

    $this->get(route('dashboard'))->assertOk();

    Http::assertSentCount(1);
});

it('renders the dashboard without weather when the weather service is unavailable', function () {
    Cache::forget('dashboard.weather.current');
    Http::preventStrayRequests();
    Http::fake([
        config('services.weather.endpoint').'*' => Http::response([], 503),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('weather', null)
        );

    Http::assertSentCount(1);
});

it('renders the dashboard without weather when the weather connection fails', function () {
    Cache::forget('dashboard.weather.current');
    Http::preventStrayRequests();
    Http::fake([
        config('services.weather.endpoint').'*' => Http::failedConnection(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('weather', null)
        );

    Http::assertSentCount(1);
});

it('renders the dashboard without weather when the API response is malformed', function () {
    Cache::forget('dashboard.weather.current');
    Http::preventStrayRequests();
    Http::fake([
        config('services.weather.endpoint').'*' => Http::response([
            'current' => ['temperature_2m' => 'unknown'],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('weather', null)
        );

    Http::assertSentCount(1);
});
