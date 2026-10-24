<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entity_type',
    'action',
    'entity_id',
    'entity_name',
    'details',
    'changes',
    'requested_by',
    'requester_name',
    'reviewed_by',
    'status',
    'reviewed_at',
])]
class DeletionRequest extends Model
{
    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
