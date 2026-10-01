<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['time_entry_id', 'adjusted_by', 'old_timestamp', 'new_timestamp', 'justification'])]
class TimeAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'old_timestamp' => 'datetime',
            'new_timestamp' => 'datetime',
        ];
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
