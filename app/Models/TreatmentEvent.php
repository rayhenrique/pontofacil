<?php

namespace App\Models;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TreatmentEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'employee_id',
        'employment_id',
        'reference_punch_id',
        'type',
        'status',
        'effective_at',
        'old_value_json',
        'new_value_json',
        'reason_code',
        'reason_text',
        'attachment_path',
        'requested_by',
        'approved_by',
        'rejected_by',
        'decided_at',
        'rejection_reason',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'decided_at' => 'datetime',
        'type' => TreatmentEventType::class,
        'status' => TreatmentEventStatus::class,
        'old_value_json' => 'array',
        'new_value_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }

            if (empty($model->employment_id) && ! empty($model->employee_id)) {
                $model->employment_id = $model->employee_id;
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function referencePunch(): BelongsTo
    {
        return $this->belongsTo(PunchEvent::class, 'reference_punch_id', 'id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TreatmentEventStatus::Pending->value);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', TreatmentEventStatus::Approved->value);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', TreatmentEventStatus::Rejected->value);
    }

    public function getPayloadAttribute(): ?array
    {
        return $this->new_value_json;
    }
}
