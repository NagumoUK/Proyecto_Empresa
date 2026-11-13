<?php

use App\Models\CompanyActivity;
use App\Models\User;
use App\Notifications\ActivityCreatedNotification;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function (): void {
    config([
        'webpush.vapid.public_key' => 'test-public-key',
        'webpush.vapid.private_key' => 'test-private-key',
        'webpush.vapid.subject' => 'mailto:push@example.test',
    ]);
});

test('guests cannot register a push subscription', function () {
    $this->postJson(route('notifications.push-subscriptions.store'), [])
        ->assertUnauthorized();
});

test('users can register a supported push subscription', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/user-endpoint';

    $this->actingAs($user)
        ->postJson(route('notifications.push-subscriptions.store'), [
            'endpoint' => $endpoint,
            'public_key' => 'public_key-123',
            'auth_token' => 'auth_token-456',
            'content_encoding' => 'aes128gcm',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Notificaciones push activadas.');

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => $endpoint,
        'subscribable_type' => $user->getMorphClass(),
        'subscribable_id' => $user->getKey(),
    ]);
});

test('users cannot take over a push subscription owned by another user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/user-endpoint';
    $owner->updatePushSubscription($endpoint, 'public_key-123', 'auth_token-456');

    $this->actingAs($otherUser)
        ->postJson(route('notifications.push-subscriptions.store'), [
            'endpoint' => $endpoint,
            'public_key' => 'public_key-other',
            'auth_token' => 'auth_token-other',
            'content_encoding' => 'aes128gcm',
        ])
        ->assertConflict();

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => $endpoint,
        'subscribable_type' => $owner->getMorphClass(),
        'subscribable_id' => $owner->getKey(),
    ]);
    $this->assertDatabaseCount('push_subscriptions', 1);
});

test('push subscription registration rejects untrusted endpoints', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('notifications.push-subscriptions.store'), [
            'endpoint' => 'https://attacker.example/push',
            'public_key' => 'public_key-123',
            'auth_token' => 'auth_token-456',
            'content_encoding' => 'aes128gcm',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');

    $this->assertDatabaseCount('push_subscriptions', 0);
});

test('push subscription registration returns 503 when VAPID is not configured', function () {
    config(['webpush.vapid.private_key' => null]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('notifications.push-subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/user-endpoint',
            'public_key' => 'public_key-123',
            'auth_token' => 'auth_token-456',
            'content_encoding' => 'aes128gcm',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath(
            'message',
            'Las notificaciones push no están configuradas en el servidor.',
        );

    $this->assertDatabaseCount('push_subscriptions', 0);
});

test('users can only delete their own push subscriptions', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/user-endpoint';
    $owner->updatePushSubscription($endpoint, 'public_key-123', 'auth_token-456');

    $this->actingAs($otherUser)
        ->deleteJson(route('notifications.push-subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])
        ->assertOk();

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => $endpoint,
        'subscribable_type' => $owner->getMorphClass(),
        'subscribable_id' => $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('notifications.push-subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])
        ->assertOk();

    $this->assertDatabaseMissing('push_subscriptions', [
        'endpoint' => $endpoint,
    ]);
});

test('activity notifications use push only for configured subscribers', function () {
    $user = User::factory()->create();
    $notification = new ActivityCreatedNotification(new CompanyActivity);

    config(['webpush.vapid.private_key' => null]);
    expect($notification->via($user))->not->toContain(WebPushChannel::class);

    config([
        'webpush.vapid.private_key' => 'test-private-key',
        'webpush.vapid.public_key' => 'test-public-key',
        'webpush.vapid.subject' => 'mailto:push@example.test',
    ]);
    $user->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/user-endpoint',
        'public_key-123',
        'auth_token-456',
    );

    expect($notification->via($user))->toContain(WebPushChannel::class);
});

test('activity push messages do not expose activity descriptions', function () {
    $user = User::factory()->create();
    $notification = new ActivityCreatedNotification(new CompanyActivity([
        'description' => 'Confidential project detail',
    ]));

    $message = $notification->toWebPush($user, $notification)->toArray();

    expect($message)->toMatchArray([
        'title' => 'Nueva actividad en el equipo',
        'body' => 'Se ha registrado una nueva actividad.',
        'data' => ['url' => route('dashboard')],
    ]);
});
