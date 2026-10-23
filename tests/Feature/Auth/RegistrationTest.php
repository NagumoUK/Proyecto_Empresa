<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Manager)
        ->and($user->email_verified_at)->toBeNull();

    $this->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('the configured administrator registers with the admin role', function () {
    config(['app.admin_email' => 'admin@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('email', 'admin@example.com')->firstOrFail()->role)
        ->toBe(UserRole::Admin);
});
