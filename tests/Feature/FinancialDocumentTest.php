<?php

use App\Models\Client;
use App\Models\FinancialDocument;
use App\Models\Project;
use App\Models\User;

test('guests cannot access financial documents', function () {
    $this->get(route('management.documentos.index'))
        ->assertRedirect(route('login'));

    $this->post(route('management.documentos.store'), [])
        ->assertRedirect(route('login'));
});

test('users can create and view an internal invoice with soles and IGV', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create([
        'name' => 'Ana López',
        'company' => 'Empresa Andina',
        'status' => 'active',
    ]);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Portal web',
        'status' => 'active',
        'budget' => '12500.00',
    ]);

    $this->post(route('management.documentos.store'), [
        'type' => 'invoice',
        'status' => 'draft',
        'client_id' => $client->id,
        'project_id' => $project->id,
        'issue_date' => '2026-10-16',
        'items' => [
            ['description' => 'Diseño', 'quantity' => '2', 'unit_price' => '100.00'],
            ['description' => 'Implementación', 'quantity' => '1.5', 'unit_price' => '80.00'],
        ],
    ])->assertRedirect();

    $document = FinancialDocument::query()->with('items')->firstOrFail();

    expect($document->type)->toBe('invoice')
        ->and($document->reference)->toBe('FAC-'.str_pad((string) $document->id, 6, '0', STR_PAD_LEFT))
        ->and($document->currency)->toBe('PEN')
        ->and($document->subtotal)->toBe('320.00')
        ->and($document->tax_rate)->toBe('18.00')
        ->and($document->tax_amount)->toBe('57.60')
        ->and($document->total)->toBe('377.60')
        ->and($document->items)->toHaveCount(2);

    $this->assertDatabaseHas('financial_document_items', [
        'financial_document_id' => $document->id,
        'description' => 'Implementación',
        'quantity' => '1.50',
        'line_total' => '120.00',
    ]);

    $this->get(route('management.documentos.show', $document))
        ->assertInertia(fn ($page) => $page
            ->component('management/documents/show')
            ->where('document.currency', 'PEN')
            ->where('document.total', '377.60')
            ->where('document.client.name', 'Ana López')
        );
});

test('users can update a draft quote and its line items', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create(['name' => 'Cliente editable', 'status' => 'active']);
    $quote = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'type' => 'quote',
        'status' => 'draft',
        'issue_date' => '2026-10-16',
        'currency' => 'PEN',
        'subtotal' => '10.00',
        'tax_rate' => '18.00',
        'tax_amount' => '1.80',
        'total' => '11.80',
    ]);
    $quote->items()->create([
        'description' => 'Antiguo',
        'quantity' => '1',
        'unit_price' => '10',
        'line_total' => '10',
    ]);

    $this->put(route('management.documentos.update', $quote), [
        'client_id' => $client->id,
        'issue_date' => '2026-10-16',
        'items' => [
            ['description' => 'Nuevo servicio', 'quantity' => '3', 'unit_price' => '50.25'],
        ],
    ])->assertRedirect(route('management.documentos.show', $quote));

    $this->patch(route('management.documentos.estado', $quote), [
        'status' => 'sent',
    ])->assertRedirect(route('management.documentos.show', $quote));

    $quote->refresh()->load('items');

    expect($quote->status)->toBe('sent')
        ->and($quote->subtotal)->toBe('150.75')
        ->and($quote->tax_amount)->toBe('27.14')
        ->and($quote->total)->toBe('177.89')
        ->and($quote->items)->toHaveCount(1)
        ->and($quote->items->first()->description)->toBe('Nuevo servicio');
});

test('approved quotes can be converted to one invoice with copied line items', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create(['name' => 'Cliente cotización', 'status' => 'active']);
    $quote = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'type' => 'quote',
        'status' => 'approved',
        'issue_date' => '2026-10-16',
        'currency' => 'PEN',
        'subtotal' => '200.00',
        'tax_rate' => '18.00',
        'tax_amount' => '36.00',
        'total' => '236.00',
    ]);
    $quote->items()->create([
        'description' => 'Servicio aprobado',
        'quantity' => '2',
        'unit_price' => '100',
        'line_total' => '200',
    ]);

    $this->post(route('management.documentos.convertir-a-factura', $quote))
        ->assertRedirect();

    $invoice = FinancialDocument::query()
        ->where('source_document_id', $quote->id)
        ->with('items')
        ->firstOrFail();

    expect($invoice->type)->toBe('invoice')
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->currency)->toBe('PEN')
        ->and($invoice->total)->toBe('236.00')
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->description)->toBe('Servicio aprobado');

    $this->post(route('management.documentos.convertir-a-factura', $quote))
        ->assertConflict();

    $this->assertDatabaseCount('financial_documents', 2);
});

test('documents reject status changes that are not allowed', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create(['name' => 'Cliente estado', 'status' => 'active']);
    $quote = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'type' => 'quote',
        'status' => 'draft',
        'issue_date' => '2026-10-16',
        'currency' => 'PEN',
    ]);

    $this->patch(route('management.documentos.estado', $quote), [
        'status' => 'approved',
    ])->assertSessionHasErrors('status');

    $this->assertDatabaseHas('financial_documents', [
        'id' => $quote->id,
        'status' => 'draft',
    ]);
});

test('documents reject projects owned by another client and ignore submitted totals', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create(['name' => 'Cliente A', 'status' => 'active']);
    $otherClient = Client::query()->create(['name' => 'Cliente B', 'status' => 'active']);
    $project = Project::query()->create([
        'client_id' => $otherClient->id,
        'name' => 'Proyecto B',
        'status' => 'planning',
    ]);

    $this->post(route('management.documentos.store'), [
        'type' => 'quote',
        'status' => 'draft',
        'client_id' => $client->id,
        'project_id' => $project->id,
        'issue_date' => '2026-10-16',
        'items' => [
            ['description' => 'Trabajo', 'quantity' => '1', 'unit_price' => '100'],
        ],
        'subtotal' => '1',
        'tax_amount' => '0',
        'total' => '1',
    ])->assertSessionHasErrors('project_id');

    $this->assertDatabaseCount('financial_documents', 0);

    $this->post(route('management.documentos.store'), [
        'type' => 'quote',
        'status' => 'draft',
        'client_id' => $client->id,
        'issue_date' => '2026-10-16',
        'items' => [
            ['description' => 'Trabajo', 'quantity' => '1', 'unit_price' => '100'],
        ],
        'subtotal' => '1',
        'tax_amount' => '0',
        'total' => '1',
    ])->assertRedirect();

    $this->assertDatabaseHas('financial_documents', [
        'client_id' => $client->id,
        'subtotal' => '100.00',
        'tax_amount' => '18.00',
        'total' => '118.00',
    ]);
});

test('only draft documents without invoices can be deleted', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create(['name' => 'Cliente protegido', 'status' => 'active']);
    $document = FinancialDocument::query()->create([
        'client_id' => $client->id,
        'type' => 'invoice',
        'status' => 'paid',
        'issue_date' => '2026-10-16',
        'currency' => 'PEN',
    ]);

    $this->delete(route('management.documentos.destroy', $document))
        ->assertConflict();

    $this->assertModelExists($document);
});

test('clients and projects with financial documents cannot be deleted', function () {
    $this->actingAs(User::factory()->create());
    $client = Client::query()->create(['name' => 'Cliente con factura', 'status' => 'active']);
    $project = Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto con factura',
        'status' => 'active',
    ]);
    FinancialDocument::query()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'type' => 'invoice',
        'status' => 'draft',
        'issue_date' => '2026-10-16',
        'currency' => 'PEN',
    ]);

    $this->delete(route('management.proyectos.destroy', $project))
        ->assertSessionHasErrors('delete');

    $this->delete(route('management.clientes.destroy', $client))
        ->assertSessionHasErrors('delete');

    $this->assertModelExists($project);
    $this->assertModelExists($client);
});
