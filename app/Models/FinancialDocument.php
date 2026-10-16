<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $created_by
 * @property string $type
 * @property string $status
 * @property string $currency
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property string|null $notes
 * @property string $subtotal
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $total
 * @property int|null $source_document_id
 * @property string $reference
 */
#[Fillable([
    'client_id',
    'project_id',
    'created_by',
    'type',
    'status',
    'currency',
    'issue_date',
    'due_date',
    'notes',
    'subtotal',
    'tax_rate',
    'tax_amount',
    'total',
    'source_document_id',
])]
class FinancialDocument extends Model
{
    protected $appends = ['reference'];

    public const TYPES = ['quote', 'invoice'];

    public const STATUSES = [
        'quote' => ['draft', 'sent', 'approved', 'rejected', 'cancelled'],
        'invoice' => ['draft', 'sent', 'paid', 'cancelled'],
    ];

    public const TRANSITIONS = [
        'quote' => [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['approved', 'rejected', 'cancelled'],
            'approved' => [],
            'rejected' => [],
            'cancelled' => [],
        ],
        'invoice' => [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['paid', 'cancelled'],
            'paid' => [],
            'cancelled' => [],
        ],
    ];

    public const IGV_RATE = 18;

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<FinancialDocumentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(FinancialDocumentItem::class);
    }

    /** @return BelongsTo<FinancialDocument, $this> */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    /** @return HasMany<FinancialDocument, $this> */
    public function invoice(): HasMany
    {
        return $this->hasMany(self::class, 'source_document_id');
    }

    /** @return Attribute<string, never> */
    protected function reference(): Attribute
    {
        return Attribute::get(fn (): string => sprintf(
            '%s-%06d',
            $this->type === 'invoice' ? 'FAC' : 'COT',
            $this->getKey(),
        ));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
