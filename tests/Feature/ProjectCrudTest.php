<?php

use App\Enums\UserRole;
use App\Mail\DeletionApprovalRequestMail;
use App\Mail\ManagementActivityMail;
use App\Models\Client;
use App\Models\DeletionRequest;
use App\Models\FinancialDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('guests cannot access project management', function () {
    $this->get(route('management.proyectos.index'))
        ->assertRedirect(route('login'));

    $this->post(route('management.proyectos.store'), [])
        ->assertRedirect(route('login'));
});

test('authenticated users can create, view, filter and update projects', function () {
    Mail::fake();
    config(['mail.activity_recipient' => 'notifications@example.com']);

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

    Mail::assertSent(ManagementActivityMail::class, fn (ManagementActivityMail $mail): bool => $mail->hasTo('notifications@example.com')
        && $mail->action === 'Creación de'
        && $mail->entityName === 'Portal de clientes');

    Mail::assertSent(ManagementActivityMail::class, fn (ManagementActivityMail $mail): bool => $mail->hasTo('notifications@example.com')
        && $mail->action === 'Actualización de'
        && $mail->entityName === 'Portal de clientes v2');
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

test('projects require admin approval before deletion', function () {
    Mail::fake();
    config(['app.admin_email' => 'notifications@example.com']);

    $manager = User::factory()->create();
    $client = Client::query()->create([
        'name' => 'Cliente de prueba',
        'status' => 'active',
    ]);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto eliminable',
        'status' => 'planning',
    ]);

    $this->actingAs($manager)
        ->delete(route('management.proyectos.destroy', $project))
        ->assertRedirect(route('management.proyectos.index'));

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
    $this->assertDatabaseHas('clients', ['id' => $client->id]);
    $deletionRequest = DeletionRequest::query()
        ->where('entity_type', 'project')
        ->where('entity_id', $project->id)
        ->firstOrFail();

    expect($deletionRequest->status)->toBe('pending');
    Mail::assertSent(DeletionApprovalRequestMail::class, fn (DeletionApprovalRequestMail $mail): bool => $mail->hasTo('notifications@example.com')
        && $mail->entityName === 'Proyecto eliminable'
        && $mail->requesterName === $manager->name);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)
        ->post(route('management.solicitudes-eliminacion.aprobar', $deletionRequest))
        ->assertRedirect();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseHas('deletion_requests', [
        'id' => $deletionRequest->id,
        'status' => 'approved',
        'reviewed_by' => $admin->id,
    ]);
});

test('an administrator cannot approve deletion after a financial document is created', function () {
    Mail::fake();
    config(['app.admin_email' => 'admin@example.com']);

    $manager = User::factory()->create();
    $client = Client::query()->create([
        'name' => 'Cliente con cotización',
        'status' => 'active',
    ]);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto protegido',
        'status' => 'planning',
    ]);

    $this->actingAs($manager)
        ->delete(route('management.proyectos.destroy', $project))
        ->assertRedirect(route('management.proyectos.index'));

    $deletionRequest = DeletionRequest::query()->firstOrFail();
    FinancialDocument::query()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'type' => 'quote',
        'status' => 'draft',
        'issue_date' => '2026-10-16',
        'currency' => 'PEN',
        'subtotal' => '10.00',
        'tax_rate' => '18.00',
        'tax_amount' => '1.80',
        'total' => '11.80',
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)
        ->post(route('management.solicitudes-eliminacion.aprobar', $deletionRequest))
        ->assertSessionHasErrors('approval');

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
    $this->assertDatabaseHas('deletion_requests', [
        'id' => $deletionRequest->id,
        'status' => 'rejected',
        'reviewed_by' => $admin->id,
    ]);
});

test('management activity emails render the action performer and details', function () {
    $html = (new ManagementActivityMail(
        action: 'Creación de',
        entityType: 'proyecto',
        entityName: 'Portal de clientes',
        actorName: 'David',
        details: [
            'Cliente' => 'Inversiones Delta',
            'Presupuesto' => 'S/ 12500.50',
        ],
    ))->render();

    expect($html)
        ->toContain('Creación de proyecto')
        ->toContain('Portal de clientes')
        ->toContain('David')
        ->toContain('Inversiones Delta')
        ->toContain('S/ 12500.50');
});
