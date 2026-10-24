<?php

use App\Enums\UserRole;
use App\Mail\ManagementActivityMail;
use App\Mail\ManagementChangeRequestMail;
use App\Models\Client;
use App\Models\DeletionRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('guests cannot access client management', function () {
    $this->get(route('management.clientes.index'))
        ->assertRedirect(route('login'));

    $this->post(route('management.clientes.store'), [])
        ->assertRedirect(route('login'));
});

test('authenticated users can create, view, search and update clients', function () {
    Mail::fake();
    config(['mail.activity_recipient' => 'notifications@example.com']);

    $user = User::factory()->create(['role' => UserRole::Admin]);
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

    Mail::assertSent(ManagementActivityMail::class, fn (ManagementActivityMail $mail): bool => $mail->hasTo('notifications@example.com')
        && $mail->action === 'Creación de'
        && $mail->entityName === 'María Cortés');

    Mail::assertSent(ManagementActivityMail::class, fn (ManagementActivityMail $mail): bool => $mail->hasTo('notifications@example.com')
        && $mail->action === 'Actualización de'
        && $mail->entityName === 'María C. Cortés');
});

test('managers submit client creation and updates for administrator approval', function () {
    Mail::fake();
    config(['app.admin_email' => 'admin@example.com']);

    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($manager)
        ->post(route('management.clientes.store'), [
            'name' => 'Cliente pendiente',
            'company' => 'Empresa pendiente',
            'email' => 'pendiente@example.com',
            'status' => 'active',
        ])
        ->assertRedirect(route('management.clientes.index'));

    $creationRequest = DeletionRequest::query()->where('action', 'create')->firstOrFail();
    expect($creationRequest->entity_id)->toBeNull();
    $this->assertDatabaseMissing('clients', ['email' => 'pendiente@example.com']);

    $reviewMail = null;
    Mail::assertSent(ManagementChangeRequestMail::class, function (ManagementChangeRequestMail $mail) use (&$reviewMail): bool {
        $reviewMail = $mail;

        return $mail->hasTo('admin@example.com')
            && str_contains($mail->reviewUrl, 'solicitud=');
    });

    $this->actingAs($admin)
        ->get($reviewMail->reviewUrl)
        ->assertInertia(fn ($page) => $page
            ->where('selectedRequestId', $creationRequest->id)
            ->where('requests.data.0.action', 'create')
            ->where('requests.data.0.changes.proposed.company', 'Empresa pendiente')
        );

    $this->post(route('management.solicitudes-eliminacion.aprobar', $creationRequest))
        ->assertRedirect();

    $client = Client::query()->where('email', 'pendiente@example.com')->firstOrFail();
    $this->assertDatabaseHas('deletion_requests', [
        'id' => $creationRequest->id,
        'entity_id' => $client->id,
        'status' => 'approved',
    ]);

    $this->actingAs($manager)
        ->put(route('management.clientes.update', $client), [
            'name' => 'Cliente actualizado',
            'company' => 'Empresa aprobada',
            'email' => 'pendiente@example.com',
            'status' => 'inactive',
        ])
        ->assertRedirect(route('management.clientes.index'));

    $updateRequest = DeletionRequest::query()->where('action', 'update')->firstOrFail();
    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'name' => 'Cliente pendiente',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('management.solicitudes-eliminacion.aprobar', $updateRequest))
        ->assertRedirect();

    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'name' => 'Cliente actualizado',
        'company' => 'Empresa aprobada',
        'status' => 'inactive',
    ]);
    Mail::assertSentTimes(ManagementChangeRequestMail::class, 2);
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
    Mail::fake();
    config(['mail.activity_recipient' => 'notifications@example.com']);

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
    Mail::assertNothingSent();
});

test('clients without dependencies require an admin deletion approval', function () {
    Mail::fake();
    config(['app.admin_email' => 'notifications@example.com']);

    $manager = User::factory()->create();
    $client = Client::query()->create([
        'name' => 'Cliente eliminable',
        'status' => 'active',
    ]);

    $this->actingAs($manager)
        ->delete(route('management.clientes.destroy', $client))
        ->assertRedirect(route('management.clientes.index'));

    $this->assertDatabaseHas('clients', ['id' => $client->id]);
    $deletionRequest = DeletionRequest::query()
        ->where('entity_type', 'client')
        ->where('entity_id', $client->id)
        ->firstOrFail();

    expect($deletionRequest->status)->toBe('pending');
    Mail::assertSent(ManagementChangeRequestMail::class, fn (ManagementChangeRequestMail $mail): bool => $mail->hasTo('notifications@example.com')
        && $mail->entityName === 'Cliente eliminable'
        && $mail->requesterName === $manager->name
        && $mail->action === 'eliminar');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)
        ->post(route('management.solicitudes-eliminacion.aprobar', $deletionRequest))
        ->assertRedirect();

    $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    $this->assertDatabaseHas('deletion_requests', [
        'id' => $deletionRequest->id,
        'status' => 'approved',
        'reviewed_by' => $admin->id,
    ]);
});

test('duplicate deletion requests send one email and an admin can reject the request', function () {
    Mail::fake();
    config(['app.admin_email' => 'admin@example.com']);

    $manager = User::factory()->create();
    $client = Client::query()->create([
        'name' => 'Cliente conserva',
        'status' => 'active',
    ]);

    $this->actingAs($manager)
        ->delete(route('management.clientes.destroy', $client))
        ->assertRedirect(route('management.clientes.index'));
    $this->delete(route('management.clientes.destroy', $client))
        ->assertRedirect(route('management.clientes.index'));

    $deletionRequest = DeletionRequest::query()->firstOrFail();
    $this->assertDatabaseCount('deletion_requests', 1);
    Mail::assertSentTimes(ManagementChangeRequestMail::class, 1);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)
        ->post(route('management.solicitudes-eliminacion.rechazar', $deletionRequest))
        ->assertRedirect();

    $this->assertDatabaseHas('clients', ['id' => $client->id]);
    $this->assertDatabaseHas('deletion_requests', [
        'id' => $deletionRequest->id,
        'status' => 'rejected',
        'reviewed_by' => $admin->id,
    ]);
});

test('client logos are processed, replaced and served from private storage', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $this->post(route('management.clientes.store'), [
        'name' => 'Cliente con logotipo',
        'status' => 'active',
        'logo' => UploadedFile::fake()->image('logo.png', 120, 80),
    ])->assertRedirect(route('management.clientes.index'));

    $client = Client::query()->where('name', 'Cliente con logotipo')->firstOrFail();
    $oldLogoPath = $client->logo_path;

    expect(pathinfo($oldLogoPath, PATHINFO_EXTENSION))->toBe('webp');
    Storage::disk('local')->assertExists($oldLogoPath);

    $this->post(route('management.clientes.update', $client), [
        '_method' => 'put',
        'name' => $client->name,
        'status' => 'active',
        'logo' => UploadedFile::fake()->image('new-logo.png', 200, 100),
    ])->assertRedirect(route('management.clientes.index'));

    $newLogoPath = $client->fresh()->logo_path;

    expect($newLogoPath)->not->toBe($oldLogoPath);
    Storage::disk('local')->assertMissing($oldLogoPath);
    Storage::disk('local')->assertExists($newLogoPath);

    $this->get(route('management.clientes.logo', $client))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

test('client logo uploads reject non-image files', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());

    $this->post(route('management.clientes.store'), [
        'name' => 'Cliente sin archivo válido',
        'status' => 'active',
        'logo' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('logo');

    $this->assertDatabaseMissing('clients', ['name' => 'Cliente sin archivo válido']);
});
