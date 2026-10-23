<?php

use App\Enums\UserRole;
use App\Models\User;

test('viewers can read management pages but cannot change records', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $this->actingAs($viewer);

    $this->get(route('management.clientes.index'))->assertOk();
    $this->post(route('management.clientes.store'), [
        'name' => 'Cliente no autorizado',
        'status' => 'active',
    ])->assertForbidden();

    $this->get(route('management.usuarios.index'))->assertForbidden();
});

test('only administrators can open administration pages', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($manager);

    $this->get(route('management.usuarios.index'))->assertForbidden();
    $this->get(route('management.solicitudes-eliminacion.index'))->assertForbidden();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $this->get(route('management.usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('management/users/index')
            ->where('roles.admin', 'Administrador')
        );

    $this->get(route('management.solicitudes-eliminacion.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('management/deletion-requests/index')
        );
});

test('administrators cannot demote themselves or the last administrator', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $this->patch(route('management.usuarios.rol', $admin), [
        'role' => UserRole::Manager->value,
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'role' => UserRole::Admin->value,
    ]);

    $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);
    $this->patch(route('management.usuarios.rol', $otherAdmin), [
        'role' => UserRole::Viewer->value,
    ])->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $otherAdmin->id,
        'role' => UserRole::Viewer->value,
    ]);
});
