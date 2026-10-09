<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $activitiesChannel = Broadcast::getChannels()->get('activities');

    Config::set('broadcasting.default', 'ably');
    Config::set('broadcasting.connections.ably.key', 'test-app:test-secret');
    Broadcast::channel('activities', $activitiesChannel);
});

it('issues a subscribe-only token for authenticated users', function () {
    $response = $this->actingAs(User::factory()->create())
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private:activities',
            'socket_id' => 'ably-test-connection',
        ])
        ->assertOk()
        ->assertJsonStructure(['token']);

    $encodedPayload = explode('.', $response->json('token'))[1];
    $token = json_decode(
        base64_decode(
            strtr($encodedPayload, '-_', '+/')
            .str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4),
        ),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $capabilities = json_decode($token['x-ably-capability'], true, flags: JSON_THROW_ON_ERROR);

    expect($capabilities['private:activities'] ?? null)->toBe(['subscribe']);
});

it('returns 403 when a guest requests a token for the private activity channel', function () {
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private:activities',
        'socket_id' => 'ably-test-connection',
    ])->assertForbidden();
});
