<?php

namespace App\Models;

use App\Domain\PTRP\Enums\TimeBankTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TimeBankTransaction extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'time_bank_account_id',
        'type',
        'minutes',
        'reference_date',
        'source_type',
        'source_id',
        'description',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'type' => TimeBankTransactionType::class,
        'minutes' => 'integer',
        'reference_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TimeBankAccount::class, 'time_bank_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function formattedMinutes(): string
    {
        return TimeBankAccount::formatMinutes($this->minutes);
    }
}
