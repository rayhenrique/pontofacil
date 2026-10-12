<?php

namespace App\Models;

use App\Enums\AttendanceReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendanceReview extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reservation_notes',
        'has_unresolved_occurrences',
        'occurrences_count',
        'occurrences_summary',
        'expected_minutes',
        'worked_minutes',
        'balance_minutes',
        'closed_period_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'status' => AttendanceReviewStatus::class,
        'reviewed_at' => 'datetime',
        'has_unresolved_occurrences' => 'boolean',
        'occurrences_count' => 'integer',
        'occurrences_summary' => 'array',
        'expected_minutes' => 'integer',
        'worked_minutes' => 'integer',
        'balance_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function closedPeriod(): BelongsTo
    {
        return $this->belongsTo(ClosedPeriod::class);
    }

    public function isReviewed(): bool
    {
        return $this->status?->isReviewed() ?? false;
    }
}
