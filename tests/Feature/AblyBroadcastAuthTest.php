<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('broadcasting.default', 'ably');
    Config::set('broadcasting.connections.ably.key', 'test-app:test-secret');
    require base_path('routes/channels.php');
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

it('renders a CSRF token for Echo authorization requests', function () {
    $this->get('/login')
        ->assertSee('<meta name="csrf-token" content="', false);
});

it('returns 403 when a guest requests a token for the private activity channel', function () {
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private:activities',
        'socket_id' => 'ably-test-connection',
    ])->assertForbidden();
});

it('authorizes a notification channel only for its owner', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private:App.Models.User.{$user->id}",
            'socket_id' => 'ably-test-connection',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private:App.Models.User.{$otherUser->id}",
            'socket_id' => 'ably-test-connection',
        ])
        ->assertForbidden();
});

it('authorizes verified users to join the workspace with presence and whisper capabilities', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence:workspace',
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
    $workspaceCapabilities = $capabilities['presence:workspace'] ?? [];
    sort($workspaceCapabilities);

    expect($workspaceCapabilities)->toBe(['presence', 'publish', 'subscribe']);
});

it('forbids unverified users from joining the workspace', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence:workspace',
            'socket_id' => 'ably-test-connection',
        ])
        ->assertForbidden();
});
