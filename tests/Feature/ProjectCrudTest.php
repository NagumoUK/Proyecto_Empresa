<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;

test('guests cannot access project management', function () {
    $this->get(route('management.proyectos.index'))
        ->assertRedirect(route('login'));

    $this->post(route('management.proyectos.store'), [])
        ->assertRedirect(route('login'));
});

test('authenticated users can create, view, filter and update projects', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create([
        'name' => 'Ana López',
        'company' => 'Inversiones Delta',
        'status' => 'active',
    ]);

    $this->post(route('management.proyectos.store'), [
        'client_id' => $client->id,
        'name' => 'Portal de clientes',
        'description' => 'Desarrollo del nuevo portal.',
        'status' => 'planning',
        'budget' => '12500.50',
        'start_date' => '2026-10-10',
        'end_date' => '2026-12-20',
    ])->assertRedirect(route('management.proyectos.index'));

    $project = Project::query()->where('name', 'Portal de clientes')->firstOrFail();

    $this->get(route('management.proyectos.index', ['search' => 'Delta', 'status' => 'planning']))
        ->assertInertia(fn ($page) => $page
            ->component('management/projects/index')
            ->where('projects.data.0.name', 'Portal de clientes')
            ->where('projects.data.0.client.company', 'Inversiones Delta')
        );

    $this->get(route('management.proyectos.show', $project))
        ->assertInertia(fn ($page) => $page
            ->component('management/projects/show')
            ->where('project.name', 'Portal de clientes')
            ->where('project.client.id', $client->id)
        );

    $this->put(route('management.proyectos.update', $project), [
        'client_id' => $client->id,
        'name' => 'Portal de clientes v2',
        'description' => 'Alcance actualizado.',
        'status' => 'active',
        'budget' => '15000.00',
        'start_date' => '2026-10-10',
        'end_date' => '2026-12-31',
    ])->assertRedirect(route('management.proyectos.index'));

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Portal de clientes v2',
        'status' => 'active',
        'budget' => '15000.00',
    ]);
});

test('project filters accept only string values', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('management.proyectos.index', [
            'search' => ['invalid'],
        ]))
        ->assertSessionHasErrors('search');

    $this->actingAs(User::factory()->create())
        ->get(route('management.proyectos.index', [
            'status' => ['invalid'],
        ]))
        ->assertSessionHasErrors('status');
});

test('projects require a valid client, status, budget and date order', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('management.proyectos.store'), [
        'client_id' => 999,
        'name' => '',
        'status' => 'unknown',
        'budget' => '-10',
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-01',
    ])->assertSessionHasErrors([
        'client_id',
        'name',
        'status',
        'budget',
        'end_date',
    ]);

    $this->assertDatabaseCount('projects', 0);
});

test('projects can be deleted', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create([
        'name' => 'Cliente de prueba',
        'status' => 'active',
    ]);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto eliminable',
        'status' => 'planning',
    ]);

    $this->delete(route('management.proyectos.destroy', $project))
        ->assertRedirect(route('management.proyectos.index'));

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseHas('clients', ['id' => $client->id]);
});
