<?php

namespace App\Models;

use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\WorkBehavior;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'name',
        'event_date',
        'type',
        'scope',
        'state',
        'city',
        'establishment_id',
        'work_behavior',
        'all_day',
        'starts_at',
        'ends_at',
        'requires_compensation',
        'legal_reference',
        'notes',
        'active',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'type' => CalendarEventType::class,
        'scope' => CalendarEventScope::class,
        'work_behavior' => WorkBehavior::class,
        'all_day' => 'boolean',
        'requires_compensation' => 'boolean',
        'active' => 'boolean',
    ];

    // ─── Relacionamentos ───────────────────────────────────────

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class, 'establishment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ────────────────────────────────────────────────

    /**
     * 20.18.7 — Resolve eventos aplicáveis a um estabelecimento com hierarquia territorial.
     *
     * Um evento é aplicável se:
     * - É NATIONAL (aplica a todos), ou
     * - É STATE e o estado coincide com o do estabelecimento, ou
     * - É MUNICIPAL e estado + cidade coincidem, ou
     * - É ESTABLISHMENT e o establishment_id coincide.
     */
    public function scopeApplicableTo(Builder $query, ?Establishment $establishment): Builder
    {
        return $query->where('active', true)->where(function (Builder $q) use ($establishment) {
            $q->where('scope', CalendarEventScope::National);

            if ($establishment) {
                if ($establishment->state) {
                    $q->orWhere(function (Builder $sq) use ($establishment) {
                        $sq->where('scope', CalendarEventScope::State)
                            ->where('state', $establishment->state);
                    });
                }

                if ($establishment->state && $establishment->city) {
                    $q->orWhere(function (Builder $sq) use ($establishment) {
                        $sq->where('scope', CalendarEventScope::Municipal)
                            ->where('state', $establishment->state)
                            ->where('city', $establishment->city);
                    });
                }

                $q->orWhere(function (Builder $sq) use ($establishment) {
                    $sq->where('scope', CalendarEventScope::Establishment)
                        ->where('establishment_id', $establishment->id);
                });
            }
        });
    }

    /**
     * Eventos ativos para uma data específica, aplicáveis a um estabelecimento.
     */
    public function scopeForDate(Builder $query, CarbonInterface|string $date, ?Establishment $establishment = null): Builder
    {
        $dateStr = $date instanceof CarbonInterface ? $date->format('Y-m-d') : $date;

        return $query->whereDate('event_date', $dateStr)
            ->applicableTo($establishment);
    }

    /**
     * Eventos ativos para um mês/ano, aplicáveis a um estabelecimento.
     */
    public function scopeForMonth(Builder $query, int $year, int $month, ?Establishment $establishment = null): Builder
    {
        return $query->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->applicableTo($establishment);
    }

    /**
     * Eventos ativos para um ano, aplicáveis a um estabelecimento.
     */
    public function scopeForYear(Builder $query, int $year, ?Establishment $establishment = null): Builder
    {
        return $query->whereYear('event_date', $year)
            ->applicableTo($establishment);
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Retorna representação para snapshot de fechamento (20.18.18).
     *
     * @return array{date: string, type: string, name: string, scope: string, work_behavior: string, all_day: bool, starts_at: ?string, ends_at: ?string, requires_compensation: bool, legal_reference: ?string}
     */
    public function toSnapshotArray(): array
    {
        return [
            'date' => $this->event_date->format('Y-m-d'),
            'type' => $this->type->value,
            'name' => $this->name,
            'scope' => $this->scope->value,
            'state' => $this->state,
            'city' => $this->city,
            'work_behavior' => $this->work_behavior->value,
            'all_day' => $this->all_day,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'requires_compensation' => $this->requires_compensation,
            'legal_reference' => $this->legal_reference,
        ];
    }
}
