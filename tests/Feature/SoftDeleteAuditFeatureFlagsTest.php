<?php

use App\Enums\UserRole;
use App\Models\BusinessModelAudit;
use App\Models\Client;
use App\Models\DeletionRequest;
use App\Models\FinancialDocument;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;

test('audits retain actor and allowlisted values without private or file data', function () {
    Storage::fake('local');
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($actor);

    $client = Client::query()->create([
        'name' => 'Cliente confidencial',
        'company' => 'Empresa privada',
        'email' => 'private@example.test',
        'address' => 'Dirección privada',
        'logo_path' => 'private/logo-secret.webp',
        'status' => 'active',
    ]);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto inicial',
        'description' => 'password=not-for-audit',
        'status' => 'planning',
    ]);
    $document = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'type' => 'quote',
        'status' => 'draft',
        'issue_date' => '2026-11-13',
        'notes' => 'token=not-for-audit',
    ]);
    Storage::disk('local')->put('projects/private/file-secret.txt', 'file-content-not-for-audit');
    $project->files()->create([
        'disk' => 'local',
        'path' => 'projects/private/file-secret.txt',
        'original_name' => 'file-secret.txt',
        'mime_type' => 'text/plain',
        'size' => 26,
    ]);

    $client->update([
        'name' => 'Cliente actualizado',
        'email' => 'updated-private@example.test',
        'address' => 'Otra dirección privada',
        'logo_path' => 'private/new-logo-secret.webp',
        'status' => 'inactive',
    ]);
    $client->delete();
    $client->restore();

    $clientAudit = BusinessModelAudit::query()
        ->where('entity_type', Client::class)
        ->where('entity_id', $client->id)
        ->where('action', 'created')
        ->firstOrFail();
    $updateAudit = BusinessModelAudit::query()
        ->where('entity_type', Client::class)
        ->where('entity_id', $client->id)
        ->where('action', 'updated')
        ->firstOrFail();

    expect($clientAudit->actor_id)->toBe($actor->id)
        ->and($clientAudit->entity_type)->toBe(Client::class)
        ->and($clientAudit->created_at)->not->toBeNull()
        ->and($clientAudit->old_values)->toBeNull()
        ->and($clientAudit->new_values)->toBe([
            'name' => 'Cliente confidencial',
            'company' => 'Empresa privada',
            'status' => 'active',
        ])
        ->and($updateAudit->old_values)->toBe([
            'name' => 'Cliente confidencial',
            'status' => 'active',
        ])
        ->and($updateAudit->new_values)->toBe([
            'name' => 'Cliente actualizado',
            'status' => 'inactive',
        ]);

    $serializedAudit = BusinessModelAudit::query()->get()->toJson();

    expect($serializedAudit)
        ->not->toContain('private@example.test')
        ->not->toContain('Dirección privada')
        ->not->toContain('logo-secret')
        ->not->toContain('password=not-for-audit')
        ->not->toContain('token=not-for-audit')
        ->not->toContain('file-secret')
        ->not->toContain('file-content-not-for-audit');

    expect(BusinessModelAudit::query()
        ->where('entity_type', Client::class)
        ->where('entity_id', $client->id)
        ->pluck('action')
        ->all())->toContain('deleted', 'restored');
});

test('restoring selected records leaves related soft-deleted records unchanged', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::query()->create(['name' => 'Cliente recuperable', 'status' => 'active']);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto recuperable',
        'status' => 'planning',
    ]);
    $document = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'type' => 'quote',
        'status' => 'draft',
        'issue_date' => '2026-11-13',
    ]);
    $item = $document->items()->create([
        'description' => 'Servicio conservado',
        'quantity' => '1',
        'unit_price' => '100',
        'line_total' => '100',
    ]);
    $path = "projects/{$project->id}/private.pdf";
    Storage::disk('local')->put($path, 'attachment-content');
    $file = $project->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'private.pdf',
        'mime_type' => 'application/pdf',
        'size' => 18,
    ]);

    $client->delete();
    $project->delete();
    $document->delete();

    $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->patch(route('management.clientes.restore', $client->id))
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('management.clientes.restore', $client->id))
        ->assertRedirect(route('management.clientes.index'));

    $this->assertModelExists(Client::query()->findOrFail($client->id));
    $this->assertSoftDeleted('projects', ['id' => $project->id]);
    $this->assertSoftDeleted('financial_documents', ['id' => $document->id]);

    $this->patch(route('management.documentos.restore', $document->id))
        ->assertRedirect(route('management.documentos.index'));

    $this->assertModelExists(FinancialDocument::query()->findOrFail($document->id));
    $this->assertDatabaseHas('financial_document_items', ['id' => $item->id]);
    $this->assertSoftDeleted('projects', ['id' => $project->id]);

    $this->patch(route('management.proyectos.restore', $project->id))
        ->assertRedirect(route('management.proyectos.index'));

    $this->assertModelExists(Project::query()->findOrFail($project->id));
    $this->assertModelExists(ProjectFile::query()->findOrFail($file->id));
    Storage::disk('local')->assertExists($path);
});

test('approving project deletion keeps attachment references and contents', function () {
    Mail::fake();
    Storage::fake('local');
    config(['app.admin_email' => 'admin@example.test']);

    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $client = Client::query()->create(['name' => 'Cliente del proyecto', 'status' => 'active']);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto con adjunto',
        'status' => 'planning',
    ]);
    $path = "projects/{$project->id}/stored.pdf";
    Storage::disk('local')->put($path, 'stored-project-content');
    $file = $project->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'stored.pdf',
        'mime_type' => 'application/pdf',
        'size' => 22,
    ]);

    $this->actingAs($manager)
        ->delete(route('management.proyectos.destroy', $project))
        ->assertRedirect(route('management.proyectos.index'));

    $deletionRequest = DeletionRequest::query()->firstOrFail();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('management.solicitudes-eliminacion.aprobar', $deletionRequest))
        ->assertRedirect();

    $this->assertSoftDeleted('projects', ['id' => $project->id]);
    $this->assertDatabaseHas('project_files', ['id' => $file->id, 'project_id' => $project->id]);
    Storage::disk('local')->assertExists($path);
});

test('draft documents are soft deleted and restored without removing line items', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::query()->create(['name' => 'Cliente del documento', 'status' => 'active']);
    $document = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'type' => 'quote',
        'status' => 'draft',
        'issue_date' => '2026-11-13',
    ]);
    $item = $document->items()->create([
        'description' => 'Partida conservada',
        'quantity' => '1',
        'unit_price' => '250',
        'line_total' => '250',
    ]);

    $this->actingAs($admin)
        ->delete(route('management.documentos.destroy', $document))
        ->assertRedirect(route('management.documentos.index'));

    $this->assertSoftDeleted('financial_documents', ['id' => $document->id]);
    $this->assertDatabaseHas('financial_document_items', ['id' => $item->id]);

    $this->patch(route('management.documentos.restore', $document->id))
        ->assertRedirect(route('management.documentos.index'));

    $this->assertModelExists(FinancialDocument::query()->findOrFail($document->id));
    $this->assertDatabaseHas('financial_document_items', ['id' => $item->id]);
});

test('soft deleting a source document preserves its invoice references', function () {
    $client = Client::query()->create(['name' => 'Cliente con documentos', 'status' => 'active']);
    $quote = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'type' => 'quote',
        'status' => 'draft',
        'issue_date' => '2026-11-13',
    ]);
    $invoice = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'type' => 'invoice',
        'status' => 'draft',
        'issue_date' => '2026-11-13',
        'source_document_id' => $quote->id,
    ]);

    $quote->delete();

    $this->assertSoftDeleted('financial_documents', ['id' => $quote->id]);
    $this->assertModelExists(FinancialDocument::query()->findOrFail($invoice->id));

    $invoice->delete();
    $trashedInvoice = FinancialDocument::withTrashed()->findOrFail($invoice->id);

    expect($trashedInvoice->sourceDocument?->getKey())->toBe($quote->id)
        ->and($quote->invoice()->whereKey($invoice->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('financial_documents', [
        'id' => $invoice->id,
        'source_document_id' => $quote->id,
    ]);
});

test('management feature flags are enabled by default and can hide routes and UI data', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::query()->create(['name' => 'Cliente de flags', 'status' => 'active']);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto de flags',
        'status' => 'planning',
    ]);
    $file = $project->files()->create([
        'disk' => 'local',
        'path' => "projects/{$project->id}/flag-test.pdf",
        'original_name' => 'flag-test.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('management.documentos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('features.documentManagement', true)
        );

    $this->get(route('management.proyectos.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('features.projectAttachments', true)
            ->where('files.0.id', $file->id)
        );

    Feature::globally()->deactivate('document-management');
    Feature::globally()->deactivate('project-attachments');

    $this->get(route('management.documentos.index'))->assertNotFound();

    $this->get(route('management.proyectos.show', $project))
        ->assertInertia(fn ($page) => $page
            ->where('features.projectAttachments', false)
            ->where('files', [])
        );

    $this->post(route('management.proyectos.archivos.store', $project), [])
        ->assertNotFound();
});
