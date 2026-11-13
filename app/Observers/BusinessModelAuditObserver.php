<?php

namespace App\Observers;

use App\Models\BusinessModelAudit;
use App\Models\Client;
use App\Models\FinancialDocument;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BusinessModelAuditObserver
{
    /** @var \WeakMap<Model, array<string, mixed>> */
    private \WeakMap $beforeMutation;

    public function __construct()
    {
        $this->beforeMutation = new \WeakMap;
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $this->businessValues($model));
    }

    public function updated(Model $model): void
    {
        $oldValues = $this->businessValues($model, $model->getPrevious());
        $newValues = $this->businessValues($model, $model->getChanges());

        if ($newValues !== []) {
            $this->record($model, 'updated', $oldValues, $newValues);
        }
    }

    public function deleting(Model $model): void
    {
        $this->beforeMutation[$model] = $this->businessValues($model);
    }

    public function deleted(Model $model): void
    {
        $values = $this->beforeMutation[$model] ?? $this->businessValues($model);
        unset($this->beforeMutation[$model]);

        $this->record($model, 'deleted', $values, $values);
    }

    public function restoring(Model $model): void
    {
        $this->beforeMutation[$model] = $this->businessValues($model, $model->getAttributes());
    }

    public function restored(Model $model): void
    {
        $oldValues = $this->beforeMutation[$model] ?? [];
        unset($this->beforeMutation[$model]);

        $this->record($model, 'restored', $oldValues, $this->businessValues($model));
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, mixed>
     */
    private function businessValues(Model $model, ?array $attributes = null): array
    {
        $attributes ??= $model->getAttributes();

        $allowed = match ($model::class) {
            Client::class => ['name', 'company', 'status'],
            Project::class => ['client_id', 'name', 'status', 'budget', 'budget_currency', 'start_date', 'end_date'],
            FinancialDocument::class => [
                'client_id',
                'project_id',
                'type',
                'status',
                'currency',
                'issue_date',
                'due_date',
                'subtotal',
                'tax_rate',
                'tax_amount',
                'total',
                'source_document_id',
            ],
            default => [],
        };

        return array_intersect_key($attributes, array_flip($allowed));
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function record(Model $model, string $action, array $oldValues, array $newValues): void
    {
        BusinessModelAudit::query()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $model->getMorphClass(),
            'entity_id' => $model->getKey(),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'created_at' => now(),
        ]);
    }
}
