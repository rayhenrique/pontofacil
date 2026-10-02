<?php

namespace App\Models;

use App\Domain\PTRP\Enums\TimeBankClosingMode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeBankPolicy extends Model
{
    protected $fillable = [
        'name',
        'enabled',
        'closing_mode',
        'valid_from',
        'valid_until',
        'created_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'closing_mode' => TimeBankClosingMode::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $d = $date ? $date->format('Y-m-d') : now()->format('Y-m-d');

        return $query->where('enabled', true)
            ->where('valid_from', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $d);
            });
    }

    /**
     * Retorna a política aplicável para a data especificada (mesmo que inativa).
     */
    public static function forDate(?CarbonInterface $date = null): ?self
    {
        $d = $date ? $date->format('Y-m-d') : now()->format('Y-m-d');

        return self::where('valid_from', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $d);
            })
            ->latest('valid_from')
            ->first();
    }

    /**
     * Retorna a política atual ou padrão (desativada por padrão conforme diretriz de UX).
     */
    public static function current(): self
    {
        $policy = self::latest('id')->first();

        if ($policy) {
            return $policy;
        }

        return new self([
            'name' => 'Regra Geral de Banco de Horas',
            'enabled' => false,
            'closing_mode' => TimeBankClosingMode::CarryOver,
            'valid_from' => now()->startOfYear()->toDateString(),
        ]);
    }
}
