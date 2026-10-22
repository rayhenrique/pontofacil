<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\WorkSchedule\Services\ShiftMidnightService;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\ShiftAssignment;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolvedor Centralizado da Sequência Efetiva de Marcações por Jornada (PTRP).
 *
 * Responsabilidade:
 * - Unificar fatos brutos imutáveis do REP-P (PunchEvent/TimeEntry legado) com
 *   eventos de tratamento homologados no PTRP (inclusões manuais aprovadas e desconsiderações).
 * - Desconsiderar estritamente marcações com desconsideração aprovada.
 * - Reconstituir a sequência cronológica da jornada [dia civil ou plantão].
 * - Recalcular a direção efetiva ('in' / 'out') de cada batida quando inserções retroativas
 *   ou reversões alterarem a ordem da jornada.
 * - Identificar marcações duplicadas acidentais (ex: batidas no mesmo minuto) sem inverter
 *   indevidamente o restante da sequência.
 * - Preservar 100% íntegros os registros originais, NSRs, timestamps, comprovantes e hashes fiscais.
 * - Não considerar solicitações pendentes como fatos consumados.
 */
class ResolveEffectiveJourneySequenceAction
{
    /**
     * Limite em segundos para detecção de batida duplicada acidental (<= 120s).
     */
    public const RAPID_DUPLICATE_THRESHOLD_SECONDS = 120;

    public function __construct(
        protected ?ShiftMidnightService $shiftMidnightService = null
    ) {
        $this->shiftMidnightService ??= app(ShiftMidnightService::class);
    }

    /**
     * Resolve a sequência de marcações efetivas para a jornada do colaborador na data.
     *
     * @param  Employee  $employee  Colaborador alvo
     * @param  CarbonInterface  $date  Data da jornada
     * @param  Collection<int, PunchEvent>|null  $rawPunches  Marcações brutas pré-carregadas (opcional)
     * @param  Collection<int, TimeEntry>|null  $legacyEntries  Registros legados pré-carregados (opcional)
     * @param  Collection<int, TreatmentEvent>|null  $approvedTreatments  Tratamentos aprovados pré-carregados (opcional)
     * @param  ShiftAssignment|null  $coveringShift  Plantão cobrindo o dia (opcional)
     * @param  Establishment|null  $establishment  Estabelecimento de lotação (opcional)
     * @param  CarbonInterface|null  $cutoffTime  Instante limite para inspeção (opcional, para auto-determinação da próxima batida)
     * @return array<int, array<string, mixed>> Lista cronológica de batidas com sentido original e sentido efetivo
     */
    public function execute(
        Employee $employee,
        CarbonInterface $date,
        ?Collection $rawPunches = null,
        ?Collection $legacyEntries = null,
        ?Collection $approvedTreatments = null,
        ?ShiftAssignment $coveringShift = null,
        ?Establishment $establishment = null,
        ?CarbonInterface $cutoffTime = null,
    ): array {
        $est = $establishment ?? $employee->sector?->establishment ?? CurrentCompany::defaultEstablishment();
        $tz = $est?->resolvedTimezone() ?? config('app.timezone', 'America/Maceio');

        $dateStr = $date->format('Y-m-d');
        $localDay = Carbon::parse($dateStr, $tz);
        $dayStart = $localDay->copy()->startOfDay();
        $dayEnd = $localDay->copy()->addDay()->startOfDay();

        // 1. Carregar tratamentos aprovados se não fornecidos
        if ($approvedTreatments === null) {
            $approvedTreatments = TreatmentEvent::where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhere('employment_id', $employee->id);
            })
                ->where('status', TreatmentEventStatus::Approved)
                ->where('effective_at', '>=', $dayStart)
                ->where('effective_at', '<', $dayEnd)
                ->get();
        }

        // 2. Mapear IDs de marcações desconsideradas por justificativa aprovada
        $disregardedPunchIds = $approvedTreatments
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->filter(fn ($t) => empty($t->reference_source) || $t->reference_source === 'punch_event' || ! empty($t->reference_punch_id))
            ->map(fn ($t) => (string) $t->reference_punch_id)
            ->filter()
            ->all();

        $disregardedTimeEntryIds = $approvedTreatments
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->filter(fn ($t) => $t->reference_source === 'time_entry' || ! empty($t->reference_time_entry_id))
            ->map(fn ($t) => (string) ($t->reference_time_entry_id ?? $t->reference_punch_id))
            ->filter()
            ->all();

        // 3. Carregar marcações brutas se não fornecidas
        if ($rawPunches === null) {
            $rawPunches = PunchEvent::where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
                if ($employee->user_id) {
                    $q->orWhere('user_id', $employee->user_id);
                }
            })
                ->where('occurred_at_local', '>=', $dayStart)
                ->where('occurred_at_local', '<', $dayEnd)
                ->orderBy('occurred_at_local', 'asc')
                ->get();
        }

        $items = [];

        // Inserir marcações brutas do REP-P
        foreach ($rawPunches as $punch) {
            $punchId = (string) $punch->id;
            $isDisregarded = in_array($punchId, $disregardedPunchIds, true);
            $ts = $punch->occurred_at_local instanceof CarbonInterface
                ? $punch->occurred_at_local
                : Carbon::parse($punch->occurred_at_local);

            if ($cutoffTime !== null && $ts->gt($cutoffTime)) {
                continue;
            }

            $rawDir = $punch->direction ?? 'in';
            if ($rawDir === 'clock_in') {
                $rawDir = 'in';
            } elseif ($rawDir === 'clock_out') {
                $rawDir = 'out';
            }

            $items[] = [
                'id' => $punchId,
                'nsr' => (int) $punch->nsr,
                'time' => $ts->format('H:i'),
                'timestamp' => $ts,
                'original_direction' => $rawDir,
                'effective_direction' => $rawDir,
                'is_direction_reclassified' => false,
                'reclassification_reason' => null,
                'type' => $rawDir,
                'direction' => $rawDir,
                'source' => 'rep_p',
                'is_manual' => false,
                'is_disregarded' => $isDisregarded,
                'is_duplicate' => false,
                'reason' => null,
                'is_previous_day_exit' => false,
                'punch_event' => $punch,
                'treatment_event' => null,
            ];
        }

        // Fallback para histórico legado em TimeEntry se não houver registros em PunchEvent
        if ($rawPunches->isEmpty() && $employee->user_id) {
            if ($legacyEntries === null) {
                $legacyEntries = TimeEntry::where('user_id', $employee->user_id)
                    ->where('timestamp', '>=', $dayStart)
                    ->where('timestamp', '<', $dayEnd)
                    ->orderBy('timestamp', 'asc')
                    ->get();
            }

            foreach ($legacyEntries as $entry) {
                $entryId = (string) $entry->id;
                $isDisregarded = in_array($entryId, $disregardedTimeEntryIds, true)
                    || in_array((int) $entry->id, $disregardedTimeEntryIds, true);

                $ts = $entry->timestamp instanceof CarbonInterface
                    ? $entry->timestamp
                    : Carbon::parse($entry->timestamp);

                if ($cutoffTime !== null && $ts->gt($cutoffTime)) {
                    continue;
                }

                $rawDir = match ($entry->type) {
                    'clock_in', 'in' => 'in',
                    'clock_out', 'out' => 'out',
                    default => 'in',
                };

                $items[] = [
                    'id' => $entryId,
                    'nsr' => 0,
                    'time' => $ts->format('H:i'),
                    'timestamp' => $ts,
                    'original_direction' => $rawDir,
                    'effective_direction' => $rawDir,
                    'is_direction_reclassified' => false,
                    'reclassification_reason' => null,
                    'type' => $rawDir,
                    'direction' => $rawDir,
                    'source' => 'time_entry_projection',
                    'is_manual' => false,
                    'is_disregarded' => $isDisregarded,
                    'is_duplicate' => false,
                    'reason' => null,
                    'is_previous_day_exit' => false,
                    'punch_event' => null,
                    'treatment_event' => null,
                ];
            }
        }

        // Adicionar batidas manuais aprovadas pelo RH (PTRP)
        $approvedManuals = $approvedTreatments
            ->where('type', TreatmentEventType::ManualPunchAdded)
            ->filter(fn ($t) => $t->status === TreatmentEventStatus::Approved);

        foreach ($approvedManuals as $manual) {
            $ts = $manual->effective_at instanceof CarbonInterface
                ? $manual->effective_at
                : Carbon::parse($manual->effective_at);

            if ($cutoffTime !== null && $ts->gt($cutoffTime)) {
                continue;
            }

            $rawDir = $manual->new_value_json['direction'] ?? 'in';
            if ($rawDir === 'clock_in') {
                $rawDir = 'in';
            } elseif ($rawDir === 'clock_out') {
                $rawDir = 'out';
            }

            $items[] = [
                'id' => (string) $manual->id,
                'nsr' => null, // Nunca inventar NSR fictício para inclusão manual
                'time' => $ts->format('H:i'),
                'timestamp' => $ts,
                'original_direction' => $rawDir,
                'effective_direction' => $rawDir,
                'is_direction_reclassified' => false,
                'reclassification_reason' => null,
                'type' => $rawDir,
                'direction' => $rawDir,
                'source' => 'ptrp_manual',
                'is_manual' => true,
                'is_disregarded' => false,
                'is_duplicate' => false,
                'reason' => $manual->reason_text,
                'is_previous_day_exit' => false,
                'punch_event' => null,
                'treatment_event' => $manual,
            ];
        }

        // Ordenar cronologicamente por timestamp real (ordenação canônica)
        usort($items, function ($a, $b) {
            $tA = $a['timestamp'] instanceof CarbonInterface ? $a['timestamp']->getTimestamp() : Carbon::parse($a['timestamp'])->getTimestamp();
            $tB = $b['timestamp'] instanceof CarbonInterface ? $b['timestamp']->getTimestamp() : Carbon::parse($b['timestamp'])->getTimestamp();
            if ($tA === $tB) {
                // Desconsideradas ficam por último se no mesmo timestamp
                if ($a['is_disregarded'] !== $b['is_disregarded']) {
                    return $a['is_disregarded'] ? 1 : -1;
                }

                return ((int) ($a['nsr'] ?? 0)) <=> ((int) ($b['nsr'] ?? 0));
            }

            return $tA <=> $tB;
        });

        // 4. Recalcular os sentidos efetivos na sequência da jornada
        $this->recalculateEffectiveDirections($items);

        // Apenas marcações efetivas (não desconsideradas) compõem a sequência efetiva da jornada
        return array_values(array_filter($items, fn ($item) => empty($item['is_disregarded'])));
    }

    /**
     * Recalcula a sequência efetiva de direções considerando o estado de trabalho da jornada.
     *
     * Regras:
     * 1. Marcações desconsideradas são ignoradas na sequência de alternância.
     * 2. Marcações marcadas como encerramento de plantão da véspera (is_previous_day_exit)
     *    pertencem à jornada anterior e não iniciam a sequência de hoje.
     * 3. Batidas repetidas em curto intervalo (<= 120s) com o mesmo sentido original
     *    são marcadas como duplicadas e NÃO invertem o restante da sequência.
     * 4. Batidas automáticas do REP-P são reclassificadas administrativamente no PTRP apenas
     *    quando precedidas por inclusões manuais que alteram a paridade da jornada.
     *    Sem inclusões manuais prévias, os sentidos originais são preservados para apontar
     *    ocorrências reais (ex: saída sem entrada ou entradas consecutivas).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function recalculateEffectiveDirections(array &$items): void
    {
        $currentState = 'out'; // Estado inicial: colaborador fora do trabalho
        $lastEffectiveTs = null;
        $lastEffectiveDir = null;
        $manualPunchesEncountered = 0;

        foreach ($items as &$item) {
            // Ignorar marcações desconsideradas ou saídas pertencentes ao dia anterior
            if (! empty($item['is_disregarded']) || ! empty($item['is_previous_day_exit'])) {
                continue;
            }

            $ts = $item['timestamp'] instanceof CarbonInterface ? $item['timestamp'] : Carbon::parse($item['timestamp']);
            $origDir = $item['original_direction'] ?? $item['direction'] ?? $item['type'] ?? 'in';

            // Verificação de batida duplicada em curto intervalo (<= 120 segundos)
            if ($lastEffectiveTs !== null && $lastEffectiveDir !== null) {
                $diffSec = abs($ts->getTimestamp() - $lastEffectiveTs->getTimestamp());
                if ($diffSec <= self::RAPID_DUPLICATE_THRESHOLD_SECONDS && ($origDir === $lastEffectiveDir || ! ($item['is_manual'] ?? false))) {
                    // Batida duplicada: preserva o sentido da batida anterior e não altera o estado da jornada
                    $item['is_duplicate'] = true;
                    $item['effective_direction'] = $lastEffectiveDir;
                    $item['type'] = $lastEffectiveDir;
                    $item['direction'] = $lastEffectiveDir;
                    if ($item['effective_direction'] !== $origDir) {
                        $item['is_direction_reclassified'] = true;
                        $item['reclassification_reason'] = 'Marcação duplicada em curto intervalo de tempo (mantido sentido da batida anterior).';
                    }

                    continue;
                }
            }

            $isManual = ! empty($item['is_manual']);

            if ($isManual) {
                $manualPunchesEncountered++;
                $effectiveDir = $origDir;
                $item['effective_direction'] = $effectiveDir;
                $item['type'] = $effectiveDir;
                $item['direction'] = $effectiveDir;
                $item['is_direction_reclassified'] = false;
                $currentState = $effectiveDir;
            } else {
                if ($manualPunchesEncountered > 0) {
                    // Há pelo menos uma inclusão manual prévia que alterou a paridade da sequência
                    $expectedDir = ($currentState === 'out') ? 'in' : 'out';
                    $effectiveDir = $expectedDir;
                    $item['effective_direction'] = $effectiveDir;
                    $item['type'] = $effectiveDir;
                    $item['direction'] = $effectiveDir;

                    if ($effectiveDir !== $origDir) {
                        $item['is_direction_reclassified'] = true;
                        $item['reclassification_reason'] = sprintf(
                            'Reclassificado administrativamente no PTRP de %s para %s após inclusão/reversão de marcações na jornada.',
                            $origDir === 'in' ? 'Entrada' : 'Saída',
                            $effectiveDir === 'in' ? 'Entrada' : 'Saída'
                        );
                    }

                    $currentState = $effectiveDir;
                } else {
                    // Nenhuma inclusão manual prévia na jornada: preserva a direção original da batida
                    $effectiveDir = $origDir;
                    $item['effective_direction'] = $effectiveDir;
                    $item['type'] = $effectiveDir;
                    $item['direction'] = $effectiveDir;
                    $item['is_direction_reclassified'] = false;
                    $currentState = $effectiveDir;
                }
            }

            $lastEffectiveTs = $ts;
            $lastEffectiveDir = $currentState;
        }
        unset($item);
    }
}
