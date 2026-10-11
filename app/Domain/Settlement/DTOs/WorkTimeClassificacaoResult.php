<?php

namespace App\Domain\Settlement\DTOs;

/**
 * Representa a qualificação jurídica e factual das diferenças apuradas:
 * tolerada, justificada, compensável, remunerável, descontável ou pendente de análise.
 */
class WorkTimeClassificacaoResult
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<int, string>  $notes
     */
    public function __construct(
        public int $toleratedMinutes = 0,
        public int $justifiedMinutes = 0,
        public int $compensableOvertimeMinutes = 0,
        public int $remunerableOvertimeMinutes = 0,
        public int $compensableDeficitMinutes = 0,
        public int $deductibleDeficitMinutes = 0,
        public int $pendingAnalysisMinutes = 0,
        public bool $isPendingLegalDefinition = false,
        public array $notes = [],
        public array $details = [],
    ) {}

    public function totalPositiveClassified(): int
    {
        return $this->compensableOvertimeMinutes + $this->remunerableOvertimeMinutes + ($this->isPendingLegalDefinition ? 0 : 0);
    }

    public function totalNegativeClassified(): int
    {
        return $this->compensableDeficitMinutes + $this->deductibleDeficitMinutes;
    }

    public function toArray(): array
    {
        return [
            'tolerated_minutes' => $this->toleratedMinutes,
            'justified_minutes' => $this->justifiedMinutes,
            'compensable_overtime_minutes' => $this->compensableOvertimeMinutes,
            'remunerable_overtime_minutes' => $this->remunerableOvertimeMinutes,
            'compensable_deficit_minutes' => $this->compensableDeficitMinutes,
            'deductible_deficit_minutes' => $this->deductibleDeficitMinutes,
            'pending_analysis_minutes' => $this->pendingAnalysisMinutes,
            'is_pending_legal_definition' => $this->isPendingLegalDefinition,
            'notes' => $this->notes,
            'details' => $this->details,
        ];
    }
}
