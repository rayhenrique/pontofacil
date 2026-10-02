<?php

namespace App\Domain\TimeClock\Services;

use App\Models\Establishment;
use Illuminate\Support\Facades\DB;

class NsrGeneratorService
{
    /**
     * Reserva o próximo Número Sequencial de Registro (NSR) de forma atômica e estritamente monotônica.
     *
     * Conforme a Portaria 671/2021 MTP:
     * - O NSR é próprio de cada estabelecimento;
     * - Inicia em 1 e avança de 1 em 1 sem saltos nem repetições;
     * - Utiliza lock pessimista (lockForUpdate) para prevenir condições de corrida sob concorrência.
     */
    public function reserveNextNsr(int $establishmentId): int
    {
        return DB::transaction(function () use ($establishmentId) {
            $establishment = Establishment::where('id', $establishmentId)
                ->lockForUpdate()
                ->firstOrFail();

            $reservedNsr = (int) $establishment->nsr_next;

            $establishment->nsr_next = $reservedNsr + 1;
            $establishment->save();

            return $reservedNsr;
        });
    }
}
