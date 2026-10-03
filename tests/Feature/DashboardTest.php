<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard summaries and project list use saved business records', function () {
    $user = User::factory()->create();
    $client = Client::query()->create([
        'name' => 'Cliente del dashboard',
        'status' => 'active',
    ]);
    Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto del dashboard',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('metrics.clients', 1)
            ->where('metrics.activeProjects', 1)
            ->where('projectStatuses.active', 1)
            ->where('projects.0.name', 'Proyecto del dashboard')
        );
});
