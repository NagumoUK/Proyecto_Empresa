<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string|null $budget
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Client $client
 */
#[Fillable(['client_id', 'name', 'description', 'status', 'budget', 'start_date', 'end_date'])]
class Project extends Model
{
    public const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
