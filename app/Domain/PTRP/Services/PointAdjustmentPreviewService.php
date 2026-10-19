<?php

namespace App\Domain\PTRP\Services;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PointAdjustmentPreviewService
{
    /**
     * Gera a prévia da sequência de marcações para a data especificada,
     * combinando registros eletrônicos originais, tratamentos aprovados,
     * solicitações pendentes e a proposta de correção em edição.
     *
     * @return array{items: array, summary: string, has_justified_absence: bool, available_punches: array}
     */
    public function generatePreview(
        Employee $employee,
        CarbonInterface|string $date,
        ?string $proposedAction = null,
        ?string $proposedTime = null,
        ?string $proposedRefPunchId = null,
    ): array {
        $timezone = $employee->sector?->establishment?->timezone
            ?? $employee->establishment?->timezone
            ?? config('app.timezone', 'America/Maceio');

        $carbonDate = is_string($date)
            ? Carbon::parse($date, $timezone)
            : $date->copy()->setTimezone($timezone);

        $dateStr = $carbonDate->toDateString();
        $dayStart = $carbonDate->copy()->startOfDay();
        $dayEnd = $carbonDate->copy()->endOfDay();

        // 1. Marcações originais brutas (REP-P)
        $rawPunches = PunchEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('user_id', $employee->user_id);
        })
            ->whereBetween('occurred_at_local', [$dayStart, $dayEnd])
            ->orderBy('occurred_at_local', 'asc')
            ->get();

        // Fallback legado TimeEntry se não houver registros REP
        $legacyEntries = collect();
        if ($rawPunches->isEmpty() && $employee->user_id) {
            $legacyEntries = TimeEntry::where('user_id', $employee->user_id)
                ->whereBetween('timestamp', [$dayStart, $dayEnd])
                ->orderBy('timestamp', 'asc')
                ->get();
        }

        // 2. Tratamentos do PTRP para a data (aprovados e pendentes)
        $treatments = TreatmentEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->whereBetween('effective_at', [$dayStart, $dayEnd])
            ->get();

        $approvedDisregardedPunchIds = $treatments
            ->where('status', TreatmentEventStatus::Approved)
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->pluck('reference_punch_id')
            ->filter()
            ->all();

        $pendingDisregardedPunchIds = $treatments
            ->where('status', TreatmentEventStatus::Pending)
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->pluck('reference_punch_id')
            ->filter()
            ->all();

        $hasApprovedAbsence = $treatments
            ->where('status', TreatmentEventStatus::Approved)
            ->where('type', TreatmentEventType::AbsenceJustified)
            ->isNotEmpty();

        $hasPendingAbsence = $treatments
            ->where('status', TreatmentEventStatus::Pending)
            ->where('type', TreatmentEventType::AbsenceJustified)
            ->isNotEmpty();

        $items = [];
        $availablePunches = [];

        // Inserir batidas brutas do REP
        foreach ($rawPunches as $punch) {
            $punchId = (string) $punch->id;
            $timeStr = $punch->occurred_at_local ? $punch->occurred_at_local->format('H:i') : '--:--';
            $isDisregarded = in_array($punchId, $approvedDisregardedPunchIds, true);
            $isPendingDisregard = in_array($punchId, $pendingDisregardedPunchIds, true);
            $isBeingProposedToDisregard = ($proposedAction === 'disregard_punch' && (string) $proposedRefPunchId === $punchId);

            $availablePunches[] = [
                'id' => $punchId,
                'time' => $timeStr,
                'direction' => $punch->direction === 'in' ? 'Entrada' : 'Saída',
                'label' => sprintf('%s às %s (NSR #%s)', $punch->direction === 'in' ? 'Entrada' : 'Saída', $timeStr, str_pad((string) $punch->nsr, 6, '0', STR_PAD_LEFT)),
                'is_disregarded' => $isDisregarded,
            ];

            if ($isBeingProposedToDisregard) {
                $statusType = 'proposed_disregard';
                $statusBadge = 'bg-rose-100 text-rose-800 border-rose-300 line-through';
                $description = sprintf('Marcação original #%s às %s (PROPOSTA DE DESCONSIDERAÇÃO)', $punch->nsr, $timeStr);
            } elseif ($isDisregarded) {
                $statusType = 'disregarded';
                $statusBadge = 'bg-gray-100 text-gray-500 border-gray-200 line-through';
                $description = sprintf('Marcação original #%s às %s (Desconsiderada pelo RH)', $punch->nsr, $timeStr);
            } elseif ($isPendingDisregard) {
                $statusType = 'pending_disregard';
                $statusBadge = 'bg-amber-50 text-amber-800 border-amber-200';
                $description = sprintf('Marcação original #%s às %s (Desconsideração aguardando análise)', $punch->nsr, $timeStr);
            } else {
                $statusType = 'original';
                $statusBadge = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                $description = sprintf('Registro eletrônico REP-P (NSR #%s)', $punch->nsr);
            }

            $items[] = [
                'id' => $punchId,
                'time' => $timeStr,
                'direction' => $punch->direction ?? 'in',
                'type' => $statusType,
                'description' => $description,
                'badge_class' => $statusBadge,
                'effective' => ! $isDisregarded && ! $isBeingProposedToDisregard,
            ];
        }

        // Inserir registros legados (se aplicável)
        foreach ($legacyEntries as $entry) {
            $entryId = (string) $entry->id;
            $timeStr = Carbon::parse($entry->timestamp)->format('H:i');
            $dir = $entry->type === 'in' ? 'in' : 'out';
            $isDisregarded = in_array($entryId, $approvedDisregardedPunchIds, true);
            $isBeingProposedToDisregard = ($proposedAction === 'disregard_punch' && (string) $proposedRefPunchId === $entryId);

            $availablePunches[] = [
                'id' => $entryId,
                'time' => $timeStr,
                'direction' => $dir === 'in' ? 'Entrada' : 'Saída',
                'label' => sprintf('%s às %s (Registro Histórico)', $dir === 'in' ? 'Entrada' : 'Saída', $timeStr),
                'is_disregarded' => $isDisregarded,
            ];

            if ($isBeingProposedToDisregard) {
                $statusType = 'proposed_disregard';
                $statusBadge = 'bg-rose-100 text-rose-800 border-rose-300 line-through';
                $description = sprintf('Registro histórico às %s (PROPOSTA DE DESCONSIDERAÇÃO)', $timeStr);
            } elseif ($isDisregarded) {
                $statusType = 'disregarded';
                $statusBadge = 'bg-gray-100 text-gray-500 border-gray-200 line-through';
                $description = sprintf('Registro histórico às %s (Desconsiderado pelo RH)', $timeStr);
            } else {
                $statusType = 'original';
                $statusBadge = 'bg-sky-50 text-sky-800 border-sky-200';
                $description = 'Registro histórico';
            }

            $items[] = [
                'id' => $entryId,
                'time' => $timeStr,
                'direction' => $dir,
                'type' => $statusType,
                'description' => $description,
                'badge_class' => $statusBadge,
                'effective' => ! $isDisregarded && ! $isBeingProposedToDisregard,
            ];
        }

        // Inserir tratamentos manuais existentes (aprovados e pendentes)
        foreach ($treatments->where('type', TreatmentEventType::ManualPunchAdded) as $tr) {
            $timeStr = $tr->effective_at ? $tr->effective_at->format('H:i') : '--:--';
            $dir = $tr->new_value_json['direction'] ?? 'in';
            $isApproved = ($tr->status === TreatmentEventStatus::Approved);

            $items[] = [
                'id' => (string) $tr->id,
                'time' => $timeStr,
                'direction' => $dir,
                'type' => $isApproved ? 'approved_manual' : 'pending_manual',
                'description' => $isApproved
                    ? sprintf('Batida manual aprovada (%s)', $tr->reason_text)
                    : sprintf('Batida manual pendente de análise (%s)', $tr->reason_text),
                'badge_class' => $isApproved
                    ? 'bg-indigo-50 text-indigo-800 border-indigo-200'
                    : 'bg-amber-50 text-amber-800 border-amber-200',
                'effective' => $isApproved,
            ];
        }

        // Inserir a NOVA PROPOSTA DE CORREÇÃO (se fornecida e válida)
        if (($proposedAction === 'add_entry' || $proposedAction === 'add_exit') && ! empty($proposedTime)) {
            $dir = $proposedAction === 'add_entry' ? 'in' : 'out';
            $timeFormatted = Carbon::hasFormat($proposedTime, 'H:i')
                ? Carbon::createFromFormat('H:i', $proposedTime)->format('H:i')
                : $proposedTime;

            $items[] = [
                'id' => 'proposed_new',
                'time' => $timeFormatted,
                'direction' => $dir,
                'type' => 'proposed_addition',
                'description' => sprintf('PROPOSTA: Inclusão de %s manual às %s', $dir === 'in' ? 'entrada' : 'saída', $timeFormatted),
                'badge_class' => 'bg-indigo-600 text-white border-indigo-700 shadow-sm font-bold',
                'effective' => true,
            ];
        }

        // Ordenar cronologicamente por horário
        usort($items, function ($a, $b) {
            $cmp = strcmp($a['time'], $b['time']);
            if ($cmp !== 0) {
                return $cmp;
            }

            // Desconsideradas ficam por último se no mesmo minuto
            return ($a['effective'] ? 0 : 1) <=> ($b['effective'] ? 0 : 1);
        });

        // 3. Montar Resumo / Diagnóstico da Sequência
        $effectiveItems = array_values(array_filter($items, fn ($i) => ! empty($i['effective'])));
        $effectiveCount = count($effectiveItems);

        $hasAbsence = $hasApprovedAbsence || ($proposedAction === 'justify_absence');

        if ($hasAbsence) {
            $summary = 'Ausência justificada legalmente para a data.';
        } elseif ($effectiveCount === 0) {
            $summary = 'Nenhum registro efetivo para esta data.';
        } elseif ($effectiveCount % 2 !== 0) {
            $last = end($effectiveItems);
            $summary = sprintf(
                'Sequência incompleta (%d batidas): Última marcação é %s às %s (saída pendente).',
                $effectiveCount,
                $last['direction'] === 'in' ? 'Entrada' : 'Saída',
                $last['time']
            );
        } else {
            $summary = sprintf('Sequência equilibrada: %d marcações efetivas registradas no dia.', $effectiveCount);
        }

        return [
            'items' => $items,
            'summary' => $summary,
            'has_justified_absence' => $hasAbsence,
            'available_punches' => $availablePunches,
        ];
    }
}
