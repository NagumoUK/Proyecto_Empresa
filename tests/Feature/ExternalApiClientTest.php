<?php

use App\Services\ExternalApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

test('sends authenticated JSON requests through the configured API client', function () {
    config()->set('services.external_api.base_url', 'https://api.example.test');
    config()->set('services.external_api.token', 'test-token');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/v1/status*' => Http::response(['ready' => true]),
    ]);

    $response = app(ExternalApiClient::class)->get('/v1/status', ['source' => 'app']);

    expect($response->json('ready'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->url() === 'https://api.example.test/v1/status?source=app'
        && $request->hasHeader('Accept', 'application/json'));
});

test('throws when the external API responds with an error status', function () {
    config()->set('services.external_api.base_url', 'https://api.example.test');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/v1/failure' => Http::response(['message' => 'Unavailable'], 503),
    ]);

    expect(fn () => app(ExternalApiClient::class)->get('/v1/failure'))
        ->toThrow(RequestException::class);
});

test('requires a configured external API base URL', function () {
    config()->set('services.external_api.base_url', null);

    expect(fn () => app(ExternalApiClient::class)->get('/v1/status'))
        ->toThrow(LogicException::class, 'The external API base URL is not configured.');
});
