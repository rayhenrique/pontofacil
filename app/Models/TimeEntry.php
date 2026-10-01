<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'timestamp', 'type', 'latitude', 'longitude', 'accuracy', 'is_manual'])]
class TimeEntry extends Model
{
    protected $fillable = [
        'user_id',
        'timestamp',
        'type',
        'latitude',
        'longitude',
        'accuracy',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_manual' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
