<?php

use App\Enums\UserRole;
use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('administrators create users and invite them to establish a verified password', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('management.usuarios.store'), [
            'name' => 'Nuevo usuario',
            'email' => 'nuevo@example.com',
            'role' => UserRole::Manager->value,
        ])
        ->assertRedirect(route('management.usuarios.index'));

    $user = User::query()->where('email', 'nuevo@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Manager)
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeFalse();

    $invitation = null;
    Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$invitation): bool {
        $invitation = $mail;

        return $mail->hasTo('nuevo@example.com')
            && $mail->role === 'Gestor'
            && str_contains($mail->setupUrl, '/reset-password/');
    });

    $this->post(route('logout'))->assertRedirect();
    parse_str((string) parse_url($invitation->setupUrl, PHP_URL_QUERY), $query);
    $this->get($invitation->setupUrl)->assertOk();

    $this->post(route('password.update'), [
        'token' => basename((string) parse_url($invitation->setupUrl, PHP_URL_PATH)),
        'email' => $query['email'],
        'password' => 'New-Secure-Password-123!',
        'password_confirmation' => 'New-Secure-Password-123!',
    ])->assertRedirect();

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and(Hash::check('New-Secure-Password-123!', $user->fresh()->password))->toBeTrue();
});

test('non-administrators cannot create users', function () {
    Mail::fake();
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)
        ->post(route('management.usuarios.store'), [
            'name' => 'No autorizado',
            'email' => 'prohibido@example.com',
            'role' => UserRole::Viewer->value,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'prohibido@example.com']);
    Mail::assertNothingSent();
});

test('administrators can resend invitations to unverified users', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($admin)
        ->post(route('management.usuarios.invitacion', $user))
        ->assertRedirect(route('management.usuarios.index'));

    Mail::assertSent(UserInvitationMail::class, fn (UserInvitationMail $mail): bool => $mail->hasTo($user->email));
});
