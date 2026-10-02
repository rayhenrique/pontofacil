<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosedPeriod extends Model
{
    protected $fillable = [
        'year',
        'month',
        'policy_id',
        'policy_snapshot',
        'status',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'policy_snapshot' => 'array',
        'closed_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(TimeBankPolicy::class, 'policy_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public static function isClosed(int $year, int $month): bool
    {
        return self::where('year', $year)
            ->where('month', $month)
            ->where('status', 'closed')
            ->exists();
    }
}
