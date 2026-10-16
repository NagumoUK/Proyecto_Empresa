<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\IndexFinancialDocumentRequest;
use App\Http\Requests\Management\StoreFinancialDocumentRequest;
use App\Http\Requests\Management\UpdateFinancialDocumentRequest;
use App\Http\Requests\Management\UpdateFinancialDocumentStatusRequest;
use App\Models\Client;
use App\Models\FinancialDocument;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FinancialDocumentController extends Controller
{
    public function index(IndexFinancialDocumentRequest $request): Response
    {
        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');
        $type = $filters['type'] ?? '';
        $status = $filters['status'] ?? '';

        $documents = FinancialDocument::query()
            ->with(['client:id,name,company', 'project:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('client', function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    })->orWhereHas('project', function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when(in_array($type, FinancialDocument::TYPES, true), fn (Builder $query) => $query->where('type', $type))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('management/documents/index', [
            'documents' => $documents,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'company']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'client_id', 'name']),
            'stats' => [
                'quotes' => FinancialDocument::query()->where('type', 'quote')->count(),
                'pendingQuotes' => FinancialDocument::query()
                    ->where('type', 'quote')
                    ->whereIn('status', ['draft', 'sent'])
                    ->count(),
                'invoices' => FinancialDocument::query()->where('type', 'invoice')->count(),
                'paidTotal' => FinancialDocument::query()
                    ->where('type', 'invoice')
                    ->where('status', 'paid')
                    ->sum('total'),
            ],
            'filters' => [
                'search' => $search,
                'type' => in_array($type, FinancialDocument::TYPES, true) ? $type : '',
                'status' => $status,
            ],
        ]);
    }

    public function store(StoreFinancialDocumentRequest $request): RedirectResponse
    {
        $document = DB::transaction(fn (): FinancialDocument => $this->saveDocument(
            $request->validated(),
            null,
            $request->user()->getKey(),
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $document->type === 'quote'
                ? 'Cotización creada correctamente.'
                : 'Factura interna creada correctamente.',
        ]);

        return to_route('management.documentos.show', $document);
    }

    public function show(FinancialDocument $documento): Response
    {
        $documento->load([
            'client:id,name,company,email,address',
            'project:id,name',
            'creator:id,name',
            'items',
            'sourceDocument:id,type',
            'invoice:id,source_document_id,type',
        ]);

        return Inertia::render('management/documents/show', [
            'document' => $documento,
            'nextStatuses' => FinancialDocument::TRANSITIONS[$documento->type][$documento->status],
            'invoice' => $documento->invoice->first(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'company']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'client_id', 'name']),
        ]);
    }

    public function update(
        UpdateFinancialDocumentRequest $request,
        FinancialDocument $documento,
    ): RedirectResponse {
        abort_unless($documento->status === 'draft', 409, 'Solo se pueden editar documentos en borrador.');

        DB::transaction(fn () => $this->saveDocument(
            $request->validated(),
            $documento,
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento actualizado correctamente.',
        ]);

        return to_route('management.documentos.show', $documento);
    }

    public function destroy(FinancialDocument $documento): RedirectResponse
    {
        abort_unless(
            $documento->status === 'draft' && ! $documento->invoice()->exists(),
            409,
            'Solo puedes eliminar borradores que no se hayan convertido en factura.',
        );

        $documento->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Borrador eliminado correctamente.',
        ]);

        return to_route('management.documentos.index');
    }

    public function convertToInvoice(FinancialDocument $documento): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($documento): FinancialDocument {
            $quote = FinancialDocument::query()
                ->whereKey($documento->getKey())
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            abort_unless($quote->type === 'quote' && $quote->status === 'approved', 409);
            abort_if($quote->invoice()->exists(), 409, 'Esta cotización ya fue convertida.');

            $invoice = FinancialDocument::query()->create([
                'client_id' => $quote->client_id,
                'project_id' => $quote->project_id,
                'created_by' => auth()->id(),
                'source_document_id' => $quote->getKey(),
                'type' => 'invoice',
                'status' => 'draft',
                'currency' => 'PEN',
                'issue_date' => today(),
                'notes' => $quote->notes,
                'subtotal' => $quote->subtotal,
                'tax_rate' => $quote->tax_rate,
                'tax_amount' => $quote->tax_amount,
                'total' => $quote->total,
            ]);

            $invoice->items()->createMany($quote->items->map(fn ($item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->all());

            return $invoice;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'La cotización fue convertida en factura interna.',
        ]);

        return to_route('management.documentos.show', $invoice);
    }

    public function updateStatus(
        UpdateFinancialDocumentStatusRequest $request,
        FinancialDocument $documento,
    ): RedirectResponse {
        $documento->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Estado del documento actualizado.',
        ]);

        return to_route('management.documentos.show', $documento);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveDocument(
        array $data,
        ?FinancialDocument $document,
        ?int $creatorId = null,
    ): FinancialDocument {
        $itemRows = $data['items'];
        $subtotalCents = 0;
        $lines = [];

        foreach ($itemRows as $item) {
            $quantityHundredths = (int) round((float) $item['quantity'] * 100);
            $unitPriceCents = (int) round((float) $item['unit_price'] * 100);
            $lineTotalCents = intdiv(($quantityHundredths * $unitPriceCents) + 50, 100);
            $subtotalCents += $lineTotalCents;

            $lines[] = [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $this->formatCents($lineTotalCents),
            ];
        }

        $taxAmountCents = intdiv(($subtotalCents * FinancialDocument::IGV_RATE) + 50, 100);
        $attributes = [
            'client_id' => $data['client_id'],
            'project_id' => $data['project_id'] ?? null,
            'type' => $document?->type ?? $data['type'],
            'status' => $document?->status ?? 'draft',
            'currency' => 'PEN',
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $this->formatCents($subtotalCents),
            'tax_rate' => FinancialDocument::IGV_RATE,
            'tax_amount' => $this->formatCents($taxAmountCents),
            'total' => $this->formatCents($subtotalCents + $taxAmountCents),
        ];

        if ($document === null) {
            $attributes['created_by'] = $creatorId;
            $document = FinancialDocument::query()->create($attributes);
        } else {
            $document->update($attributes);
            $document->items()->delete();
        }

        $document->items()->createMany($lines);

        return $document;
    }

    private function formatCents(int $cents): string
    {
        if ($cents < 0) {
            throw new RuntimeException('Financial document amounts cannot be negative.');
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
