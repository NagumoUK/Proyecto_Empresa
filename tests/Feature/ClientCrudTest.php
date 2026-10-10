<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;

test('guests cannot access client management', function () {
    $this->get(route('management.clientes.index'))
        ->assertRedirect(route('login'));

    $this->post(route('management.clientes.store'), [])
        ->assertRedirect(route('login'));
});

test('authenticated users can create, view, search and update clients', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('management.clientes.store'), [
        'name' => 'María Cortés',
        'company' => 'Grupo Andina',
        'email' => 'maria@andina.example',
        'phone' => '+52 555 123 4567',
        'address' => 'Ciudad de México',
        'status' => 'active',
    ])->assertRedirect(route('management.clientes.index'));

    $client = Client::query()->where('email', 'maria@andina.example')->firstOrFail();

    $this->get(route('management.clientes.index', ['search' => 'Andina']))
        ->assertInertia(fn ($page) => $page
            ->component('management/clients/index')
            ->where('clients.data.0.company', 'Grupo Andina')
            ->where('clients.total', 1)
        );

    $this->get(route('management.clientes.show', $client))
        ->assertInertia(fn ($page) => $page
            ->component('management/clients/show')
            ->where('client.name', 'María Cortés')
            ->where('client.projects_count', 0)
        );

    $this->put(route('management.clientes.update', $client), [
        'name' => 'María C. Cortés',
        'company' => 'Grupo Andina',
        'email' => 'maria@andina.example',
        'phone' => '+52 555 000 0000',
        'address' => 'Guadalajara',
        'status' => 'inactive',
    ])->assertRedirect(route('management.clientes.index'));

    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'name' => 'María C. Cortés',
        'status' => 'inactive',
    ]);
});

test('client search accepts only a string of up to 255 characters', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('management.clientes.index', ['search' => ['invalid']]))
        ->assertSessionHasErrors('search');
});

test('client emails must be unique and required fields must be valid', function () {
    $this->actingAs(User::factory()->create());
    Client::query()->create([
        'name' => 'Cliente existente',
        'email' => 'existente@example.com',
        'status' => 'active',
    ]);

    $this->post(route('management.clientes.store'), [
        'name' => '',
        'email' => 'existente@example.com',
        'status' => 'unknown',
    ])->assertSessionHasErrors(['name', 'email', 'status']);
});

test('clients with projects cannot be deleted', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create([
        'name' => 'Cliente con proyecto',
        'status' => 'active',
    ]);
    Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto asociado',
        'status' => 'planning',
    ]);

    $this->delete(route('management.clientes.destroy', $client))
        ->assertSessionHasErrors('delete');

    $this->assertDatabaseHas('clients', ['id' => $client->id]);
});

test('clients without projects can be deleted', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create([
        'name' => 'Cliente eliminable',
        'status' => 'active',
    ]);

    $this->delete(route('management.clientes.destroy', $client))
        ->assertRedirect(route('management.clientes.index'));

    $this->assertDatabaseMissing('clients', ['id' => $client->id]);
});
