<?php

use App\Models\User;
use App\Notifications\ActivityCreatedNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('the notification bell shares only the current users recent notifications', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Proyecto actualizado',
            'url' => route('dashboard'),
        ],
        'read_at' => now(),
        'created_at' => now()->subMinute(),
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Documento recibido',
            'url' => route('dashboard'),
        ],
    ]);
    $otherUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Privada de otro usuario',
            'url' => route('dashboard'),
        ],
    ]);
    fakeDashboardWeather();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unreadCount', 1)
            ->has('notifications.items', 2)
            ->where('notifications.items.0.message', 'Documento recibido')
            ->where('notifications.items.0.readAt', null)
            ->where('notifications.items.1.readAt', fn ($value) => is_string($value))
        );

    Http::assertSentCount(1);
});

test('a guest is redirected when attempting to read a notification', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Proyecto actualizado',
            'url' => route('dashboard'),
        ],
    ]);

    $this->patch(route('notifications.read', $notification->getKey()))
        ->assertRedirect(route('login'));

    $this->assertDatabaseHas('notifications', [
        'id' => $notification->getKey(),
        'read_at' => null,
    ]);
});

test('a user cannot read another users notification', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Proyecto actualizado',
            'url' => route('dashboard'),
        ],
    ]);

    $this->actingAs($otherUser)
        ->patch(route('notifications.read', $notification->getKey()))
        ->assertNotFound();

    $this->assertDatabaseHas('notifications', [
        'id' => $notification->getKey(),
        'read_at' => null,
    ]);
});

test('a user can mark their own notification as read', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Proyecto actualizado',
            'url' => route('dashboard'),
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->getKey()))
        ->assertRedirect();

    $this->assertDatabaseMissing('notifications', [
        'id' => $notification->getKey(),
        'read_at' => null,
    ]);
});

test('marking all notifications as read only affects the current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Proyecto actualizado',
            'url' => route('dashboard'),
        ],
    ]);
    $otherNotification = $otherUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ActivityCreatedNotification::class,
        'data' => [
            'title' => 'Nueva actividad',
            'message' => 'Privada de otro usuario',
            'url' => route('dashboard'),
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('notifications.read-all'))
        ->assertRedirect();

    $this->assertDatabaseMissing('notifications', [
        'id' => $notification->getKey(),
        'read_at' => null,
    ]);
    $this->assertDatabaseHas('notifications', [
        'id' => $otherNotification->getKey(),
        'read_at' => null,
    ]);
});
