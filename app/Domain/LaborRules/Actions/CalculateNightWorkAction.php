<?php

namespace App\Domain\LaborRules\Actions;

use App\Domain\LaborRules\DTOs\NightWorkCalculationResult;
use App\Domain\LaborRules\DTOs\WorkInterval;
use App\Domain\LaborRules\Enums\NightCalculationStatus;
use App\Enums\LegalRegime;
use App\Enums\ShiftType;
use App\Enums\WorkScheduleModality;
use App\Models\Employee;
use App\Models\LaborRuleProfile;
use App\Models\ShiftAssignment;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CalculateNightWorkAction
{
    /**
     * Executa a apuração de trabalho noturno com precisão matemática em segundos.
     * Separa rigorosamente o tempo físico trabalhado da equivalência jurídica legal.
     *
     * @param  array<int, WorkInterval|array{start: CarbonInterface|string, end: CarbonInterface|string}>  $workedIntervals
     * @param  array<int, WorkInterval|array{start: CarbonInterface|string, end: CarbonInterface|string}>  $breakIntervals
     * @param  array<string, mixed>  $context
     */
    public function execute(
        array $workedIntervals,
        string $timezone = 'America/Sao_Paulo',
        ?LaborRuleProfile $profile = null,
        ?WorkSchedule $schedule = null,
        array $breakIntervals = [],
        array $context = [],
    ): NightWorkCalculationResult {
        $warnings = [];

        // 1. Normalizar e ordenar intervalos de trabalho no timezone especificado
        $normalizedWorked = $this->normalizeIntervals($workedIntervals, $timezone);
        $normalizedBreaks = $this->normalizeIntervals($breakIntervals, $timezone);

        // 2. Deduzir intervalos de descanso usufruídos do tempo trabalhado
        $effectiveIntervals = $this->deductBreaksFromWorkedIntervals($normalizedWorked, $normalizedBreaks);

        // 3. Tempo físico total trabalhado em segundos
        $physicalWorkedSeconds = 0;
        foreach ($effectiveIntervals as $interval) {
            $physicalWorkedSeconds += $interval->durationInSeconds();
        }

        // 4. Parâmetros da janela noturna
        $nightStartStr = $profile ? $profile->getNightStartTime() : '22:00';
        $nightEndStr = $profile ? $profile->getNightEndTime() : '05:00';

        // 5. Calcular tempo físico noturno cronológico (physical_night_seconds)
        $physicalNightSeconds = 0;
        $nightBreakdown = [];

        foreach ($effectiveIntervals as $interval) {
            $nightOverlapSeconds = $this->calculateNightWindowOverlap(
                $interval->start,
                $interval->end,
                $nightStartStr,
                $nightEndStr,
                $timezone
            );

            if ($nightOverlapSeconds > 0) {
                $physicalNightSeconds += $nightOverlapSeconds;
                $nightBreakdown[] = [
                    'start' => $interval->start->toIso8601String(),
                    'end' => $interval->end->toIso8601String(),
                    'night_seconds' => $nightOverlapSeconds,
                    'night_minutes' => (int) round($nightOverlapSeconds / 60),
                ];
            }
        }

        // 6. Verificar se o regime/perfil possui base jurídica validada
        if ($profile === null || ! $profile->isApproved()) {
            $legalRegime = $context['legal_regime'] ?? null;
            $isStatutory = $legalRegime instanceof LegalRegime ? $legalRegime->isStatutory() : false;

            if ($isStatutory || $legalRegime === LegalRegime::MunicipalStatutory || $legalRegime === LegalRegime::StateStatutory) {
                $warnings[] = 'Regime estatutário sem perfil normativo validado: tempo físico noturno apurado cronologicamente, mas adicional e hora reduzida pendentes de parametrização legal pelo RH.';
            } else {
                $warnings[] = 'Perfil de regras jurídicas ausente ou pendente de aprovação: apuração noturna mantida em estado preliminar.';
            }

            return new NightWorkCalculationResult(
                physicalWorkedSeconds: $physicalWorkedSeconds,
                physicalNightSeconds: $physicalNightSeconds,
                legalNightEquivalentSeconds: $physicalNightSeconds, // Proporção 1:1, sem inventar hora ficta
                nightExtensionSeconds: 0,
                nightAdditionalPercentage: null,
                nightRuleProfileId: $profile?->id,
                ruleVersion: $profile?->version,
                calculationStatus: NightCalculationStatus::PendingConfiguration,
                warnings: $warnings,
                ruleName: $profile?->name,
                legalReference: $profile?->legal_reference,
                appliedParameters: [
                    'timezone' => $timezone,
                    'night_start' => $nightStartStr,
                    'night_end' => $nightEndStr,
                    'status' => 'pending_configuration',
                ],
                breakdown: $nightBreakdown,
            );
        }

        // 7. Avaliar particularidades de escala (ex: 12x36 Art. 59-A CLT)
        $is12x36 = $this->is12x36Schedule($schedule, $context);
        $is24x72 = $this->is24x72Schedule($schedule, $context);

        if ($is24x72 && ! $profile->isModalityAllowed('24x72')) {
            $warnings[] = 'Aviso de conformidade: modalidade 24x72 sem autorização expressa no perfil jurídico deste regime.';
        }

        // 8. Calcular Prorrogação Noturna (Art. 73, § 5º CLT / Súmula 60 TST / Lei 8.112)
        $nightExtensionSeconds = 0;

        if ($profile->shouldApplyExtension()) {
            if ($is12x36 && $profile->shouldScale12x36CompensateExtension()) {
                // Art. 59-A, parágrafo único da CLT:
                // "serão considerados compensados os feriados e as prorrogações de trabalho noturno..."
                $warnings[] = 'Escala 12x36: prorrogações após as 05:00 consideradas compensadas pela folga de 36 horas (CLT, Art. 59-A, parágrafo único).';
            } else {
                $nightExtensionSeconds = $this->calculateNightExtension(
                    $effectiveIntervals,
                    $nightStartStr,
                    $nightEndStr,
                    $timezone
                );
            }
        }

        // 9. Calcular Equivalência Legal Noturna (Art. 73, § 1º CLT e Art. 75 Lei 8.112)
        $reducedHourSeconds = $profile->getReducedHourSeconds();
        $applyReducedHour = $profile->shouldApplyReducedHour();

        $baseNightSeconds = $physicalNightSeconds + $nightExtensionSeconds;

        if ($applyReducedHour && $reducedHourSeconds > 0) {
            // Conversão com hora de 52min30s (3.150s): Fator 3600 / 3150 = 8 / 7
            $legalNightEquivalentSeconds = (int) round(($baseNightSeconds * 3600) / $reducedHourSeconds);
        } else {
            $legalNightEquivalentSeconds = $baseNightSeconds;
        }

        // 10. Status da apuração
        $status = ($baseNightSeconds > 0)
            ? NightCalculationStatus::Calculated
            : NightCalculationStatus::NotApplicable;

        return new NightWorkCalculationResult(
            physicalWorkedSeconds: $physicalWorkedSeconds,
            physicalNightSeconds: $physicalNightSeconds,
            legalNightEquivalentSeconds: $legalNightEquivalentSeconds,
            nightExtensionSeconds: $nightExtensionSeconds,
            nightAdditionalPercentage: $profile->getAdditionalPercentage(),
            nightRuleProfileId: $profile->id,
            ruleVersion: $profile->version,
            calculationStatus: $status,
            warnings: $warnings,
            ruleName: $profile->name,
            legalReference: $profile->legal_reference,
            appliedParameters: [
                'timezone' => $timezone,
                'night_start' => $nightStartStr,
                'night_end' => $nightEndStr,
                'additional_percentage' => $profile->getAdditionalPercentage(),
                'reduced_hour_seconds' => $reducedHourSeconds,
                'apply_reduced_hour' => $applyReducedHour,
                'apply_extension' => $profile->shouldApplyExtension(),
                'is_12x36' => $is12x36,
                'is_24x72' => $is24x72,
            ],
            breakdown: $nightBreakdown,
        );
    }

    /**
     * Helper de conveniência para apurar trabalho noturno para um colaborador em uma data.
     */
    public function executeForEmployee(
        Employee $employee,
        CarbonInterface $date,
        array $effectivePunches,
        ?WorkSchedule $schedule = null,
        array $context = []
    ): NightWorkCalculationResult {
        $establishment = $employee->sector?->establishment;
        $timezone = $establishment?->timezone ?? 'America/Sao_Paulo';

        $profile = $employee->getLaborRuleProfileForDate($date);
        $workSchedule = $schedule ?? $employee->getWorkScheduleForDate($date);

        // Transformar batidas efetivas em intervalos de trabalho e intervalos de descanso
        $workedIntervals = [];
        $breakIntervals = [];
        $count = count($effectivePunches);
        $dateStr = $date->format('Y-m-d');

        for ($i = 0; $i + 1 < $count; $i += 2) {
            $entryRaw = $effectivePunches[$i]['timestamp'] ?? ($dateStr.' '.$effectivePunches[$i]['time']);
            $exitRaw = $effectivePunches[$i + 1]['timestamp'] ?? ($dateStr.' '.$effectivePunches[$i + 1]['time']);

            $entry = Carbon::parse($entryRaw, $timezone)->setTimezone($timezone);
            $exit = Carbon::parse($exitRaw, $timezone)->setTimezone($timezone);

            if ($exit->gt($entry)) {
                $workedIntervals[] = new WorkInterval($entry, $exit, 'worked');
            }

            // Intervalo intrajornada usufruído entre saídas e próximas entradas
            if ($i + 2 < $count) {
                $nextEntryRaw = $effectivePunches[$i + 2]['timestamp'] ?? ($dateStr.' '.$effectivePunches[$i + 2]['time']);
                $nextEntry = Carbon::parse($nextEntryRaw, $timezone)->setTimezone($timezone);

                if ($nextEntry->gt($exit)) {
                    $breakIntervals[] = new WorkInterval($exit, $nextEntry, 'break', true);
                }
            }
        }

        $context['legal_regime'] = $employee->legal_regime;

        return $this->execute(
            workedIntervals: $workedIntervals,
            timezone: $timezone,
            profile: $profile,
            schedule: $workSchedule,
            breakIntervals: $breakIntervals,
            context: $context
        );
    }

    /**
     * Normaliza e valida a lista de intervalos em objetos WorkInterval com timezone unificado.
     *
     * @return array<int, WorkInterval>
     */
    private function normalizeIntervals(array $intervals, string $timezone): array
    {
        $normalized = [];

        foreach ($intervals as $item) {
            if ($item instanceof WorkInterval) {
                $start = $item->start->copy()->setTimezone($timezone);
                $end = $item->end->copy()->setTimezone($timezone);
                if ($end->gt($start)) {
                    $normalized[] = new WorkInterval($start, $end, $item->type, $item->isBreak, $item->description);
                }

                continue;
            }

            if (is_array($item)) {
                $rawStart = $item['start'] ?? $item['entry'] ?? null;
                $rawEnd = $item['end'] ?? $item['exit'] ?? null;

                if ($rawStart && $rawEnd) {
                    $start = Carbon::parse($rawStart, $timezone)->setTimezone($timezone);
                    $end = Carbon::parse($rawEnd, $timezone)->setTimezone($timezone);
                    if ($end->gt($start)) {
                        $normalized[] = new WorkInterval(
                            start: $start,
                            end: $end,
                            type: $item['type'] ?? 'worked',
                            isBreak: ! empty($item['is_break']),
                            description: $item['description'] ?? null
                        );
                    }
                }
            }
        }

        usort($normalized, fn (WorkInterval $a, WorkInterval $b) => $a->start->getTimestamp() <=> $b->start->getTimestamp());

        return $normalized;
    }

    /**
     * Deduz os intervalos de descanso usufruídos dos intervalos de trabalho.
     *
     * @param  array<int, WorkInterval>  $worked
     * @param  array<int, WorkInterval>  $breaks
     * @return array<int, WorkInterval>
     */
    private function deductBreaksFromWorkedIntervals(array $worked, array $breaks): array
    {
        if (empty($breaks)) {
            return $worked;
        }

        $result = [];

        foreach ($worked as $interval) {
            $currentPieces = [$interval];

            foreach ($breaks as $brk) {
                $nextPieces = [];
                foreach ($currentPieces as $piece) {
                    if (! $piece->overlaps($brk->start, $brk->end)) {
                        $nextPieces[] = $piece;

                        continue;
                    }

                    // Se o intervalo de trabalho inicia antes do descanso:
                    if ($piece->start->lt($brk->start)) {
                        $nextPieces[] = new WorkInterval(
                            start: $piece->start->copy(),
                            end: $brk->start->copy(),
                            type: $piece->type,
                            isBreak: false
                        );
                    }

                    // Se o intervalo de trabalho continua após o descanso:
                    if ($piece->end->gt($brk->end)) {
                        $nextPieces[] = new WorkInterval(
                            start: $brk->end->copy(),
                            end: $piece->end->copy(),
                            type: $piece->type,
                            isBreak: false
                        );
                    }
                }
                $currentPieces = $nextPieces;
            }

            foreach ($currentPieces as $finalPiece) {
                if ($finalPiece->durationInSeconds() > 0) {
                    $result[] = $finalPiece;
                }
            }
        }

        usort($result, fn (WorkInterval $a, WorkInterval $b) => $a->start->getTimestamp() <=> $b->start->getTimestamp());

        return $result;
    }

    /**
     * Calcula o tempo físico de sobreposição entre um intervalo trabalhado e as janelas noturnas.
     * Suporta qualquer passagem de meia-noite, virada de mês ou virada de ano.
     */
    private function calculateNightWindowOverlap(
        CarbonInterface $start,
        CarbonInterface $end,
        string $nightStartStr,
        string $nightEndStr,
        string $timezone
    ): int {
        $overlapSeconds = 0;

        // Varrer desde o dia anterior ao início até o dia seguinte ao término
        $cursorDay = $start->copy()->subDay()->startOfDay();
        $limitDay = $end->copy()->addDay()->endOfDay();

        [$startHour, $startMin] = explode(':', $nightStartStr);
        [$endHour, $endMin] = explode(':', $nightEndStr);

        while ($cursorDay->lte($limitDay)) {
            // A janela noturna começa às nightStartStr do dia cursor e vai até nightEndStr do dia cursor + 1
            $windowStart = $cursorDay->copy()->setTime((int) $startHour, (int) $startMin, 0);
            $windowEnd = $cursorDay->copy()->addDay()->setTime((int) $endHour, (int) $endMin, 0);

            if ($start->lt($windowEnd) && $end->gt($windowStart)) {
                $interStart = $start->gt($windowStart) ? $start->copy() : $windowStart->copy();
                $interEnd = $end->lt($windowEnd) ? $end->copy() : $windowEnd->copy();

                if ($interEnd->gt($interStart)) {
                    $overlapSeconds += ($interEnd->getTimestamp() - $interStart->getTimestamp());
                }
            }

            $cursorDay->addDay();
        }

        return $overlapSeconds;
    }

    /**
     * Calcula a prorrogação noturna (Súmula 60 TST / Art. 73 § 5º CLT / Lei 8.112).
     * Aplica-se quando o empregado cumpre a jornada no período noturno e prossegue trabalhando após as 05:00.
     *
     * @param  array<int, WorkInterval>  $intervals
     */
    private function calculateNightExtension(
        array $intervals,
        string $nightStartStr,
        string $nightEndStr,
        string $timezone
    ): int {
        $extensionSeconds = 0;

        [$nightStartHour] = explode(':', $nightStartStr);
        [$nightEndHour, $nightEndMin] = explode(':', $nightEndStr);

        foreach ($intervals as $interval) {
            // Verificar se o intervalo atravessou o término da noite (05:00)
            $cursorDay = $interval->start->copy()->subDay()->startOfDay();
            $limitDay = $interval->end->copy()->endOfDay();

            while ($cursorDay->lte($limitDay)) {
                $nightWindowEnd = $cursorDay->copy()->addDay()->setTime((int) $nightEndHour, (int) $nightEndMin, 0);
                $nightWindowStart = $cursorDay->copy()->setTime((int) $nightStartHour, 0, 0);

                // O empregado estava trabalhando no momento 05:00 e continuou trabalhando após as 05:00?
                if ($interval->start->lt($nightWindowEnd) && $interval->end->gt($nightWindowEnd)) {
                    // Para ser prorrogação da jornada noturna, deve ter trabalhado tempo significativo
                    // dentro da noite (ao menos 4 horas noturnas ou iniciou antes da meia-noite):
                    $nightOverlap = $this->calculateNightWindowOverlap(
                        $interval->start,
                        $nightWindowEnd,
                        $nightStartStr,
                        $nightEndStr,
                        $timezone
                    );

                    // Súmula 60, II TST: cumprida integral ou substancialmente a jornada no período noturno
                    // (ex: começou às 19h, 21h, 22h, 23h). Se começou às 04h, não é prorrogação de jornada noturna.
                    if ($nightOverlap >= 14400 || $interval->start->lte($cursorDay->copy()->setTime(23, 59, 59))) {
                        // Tempo trabalhado após as 05:00 até o término deste período contínuo
                        $extStart = $nightWindowEnd->copy();
                        $extEnd = $interval->end->copy();

                        if ($extEnd->gt($extStart)) {
                            $extensionSeconds += ($extEnd->getTimestamp() - $extStart->getTimestamp());
                        }
                    }
                }

                $cursorDay->addDay();
            }
        }

        return $extensionSeconds;
    }

    private function is12x36Schedule(?WorkSchedule $schedule, array $context): bool
    {
        if (! empty($context['is_12x36'])) {
            return true;
        }

        if (isset($context['shift']) && $context['shift'] instanceof ShiftAssignment) {
            if ($context['shift']->shift_type === ShiftType::Shift12x36) {
                return true;
            }
        }

        if ($schedule && $schedule->modality === WorkScheduleModality::TwelveByThirtySix) {
            return true;
        }

        return false;
    }

    private function is24x72Schedule(?WorkSchedule $schedule, array $context): bool
    {
        if (! empty($context['is_24x72'])) {
            return true;
        }

        if (isset($context['shift']) && $context['shift'] instanceof ShiftAssignment) {
            if ($context['shift']->shift_type === ShiftType::Shift24x72) {
                return true;
            }
        }

        if ($schedule && $schedule->modality === WorkScheduleModality::TwentyFourBySeventyTwo) {
            return true;
        }

        return false;
    }
}
