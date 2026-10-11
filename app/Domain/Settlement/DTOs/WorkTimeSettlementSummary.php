<?php

namespace App\Domain\Settlement\DTOs;

use App\Models\WorkTimeSettlementPolicy;

/**
 * Consolida os quatro conceitos fundamentais de apuração e destinação de jornada:
 * 1. Apuração (fatos brutos analíticos)
 * 2. Classificação (tolerado, extra, déficit, pendente)
 * 3. Destinação (banco, folha, compensação, folga)
 * 4. Quitação (comprovações registradas)
 */
class WorkTimeSettlementSummary
{
    /**
     * @param  array<int, mixed>  $dischargesSnapshot
     */
    public function __construct(
        public WorkTimeApuracaoResult $apuracao,
        public WorkTimeClassificacaoResult $classificacao,
        public WorkTimeDestinacaoResult $destinacao,
        public ?WorkTimeSettlementPolicy $policy = null,
        public array $dischargesSnapshot = [],
    ) {}

    public function toArray(): array
    {
        return [
            'apuracao' => $this->apuracao->toArray(),
            'classificacao' => $this->classificacao->toArray(),
            'destinacao' => $this->destinacao->toArray(),
            'policy' => $this->policy?->snapshot(),
            'discharges' => $this->dischargesSnapshot,
        ];
    }
}
