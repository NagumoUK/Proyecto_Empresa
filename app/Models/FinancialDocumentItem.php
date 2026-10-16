<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $financial_document_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $line_total
 */
#[Fillable(['financial_document_id', 'description', 'quantity', 'unit_price', 'line_total'])]
class FinancialDocumentItem extends Model
{
    /** @return BelongsTo<FinancialDocument, $this> */
    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(FinancialDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
