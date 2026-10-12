<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendanceReportLog extends Model
{
    protected $fillable = [
        'year',
        'month',
        'establishment_id',
        'sector_id',
        'generated_by',
        'format',
        'is_provisional',
        'filters',
        'totals_snapshot',
        'dispatched_to_payroll_at',
        'dispatched_by',
        'dispatch_notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'is_provisional' => 'boolean',
        'filters' => 'array',
        'totals_snapshot' => 'array',
        'dispatched_to_payroll_at' => 'datetime',
    ];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
