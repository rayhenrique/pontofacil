<?php

namespace App\Domain\Compliance\AFD;

use App\Models\Establishment;
use Carbon\Carbon;

interface AfdGeneratorInterface
{
    /**
     * Gera o arquivo AFD (Arquivo Fonte de Dados) para o estabelecimento e período especificados.
     * Deve extrair dados exclusivamente de punch_events brutos (REP-P).
     */
    public function generate(
        Establishment $establishment,
        Carbon $startDate,
        Carbon $endDate,
        ?Carbon $generationTime = null
    ): AfdExportResult;
}
