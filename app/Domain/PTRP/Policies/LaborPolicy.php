<?php

namespace App\Domain\PTRP\Policies;

/**
 * Regras e Diretrizes Trabalhistas Brasileiras (CLT / Portaria 671 MTP).
 *
 * Implementa a tolerância legal do Art. 58, § 1º da CLT:
 * Variações de até 5 minutos por batida, observado o limite diário de 10 minutos.
 *
 * IMPORTANTE: Esta regra atua EXCLUSIVAMENTE na camada de apuração.
 * A marcação original registrada no PunchEvent JAMAIS é alterada.
 */
class LaborPolicy
{
    public function __construct(
        public int $punchToleranceMinutes = 5,
        public int $dailyToleranceMinutes = 10,
        public int $minimumIntrajourneyBreakMinutes = 60,
    ) {}

    /**
     * Aplica a regra de tolerância do Art. 58, § 1º da CLT.
     * Retorna a variação líquida apurada considerando o limite legal.
     *
     * @param  int  $variationMinutes  Diferença em minutos (positiva = adiantado/atrasado)
     * @param  int  $accumulatedDailyVariation  Variação já acumulada no dia
     * @return int Minutos computáveis após aplicação da tolerância
     */
    public function applyPunchTolerance(int $variationMinutes, int $accumulatedDailyVariation = 0): int
    {
        $abs = abs($variationMinutes);

        // Se a batida individual estiver dentro do limite de 5 min
        // e o total diário acumulado não ultrapassar 10 min:
        if ($abs <= $this->punchToleranceMinutes && ($accumulatedDailyVariation + $abs) <= $this->dailyToleranceMinutes) {
            return 0; // Desconsiderado por tolerância legal
        }

        // Se ultrapassou qualquer limite, a totalidade do tempo é computada (Súmula 366 TST)
        return $variationMinutes;
    }
}
