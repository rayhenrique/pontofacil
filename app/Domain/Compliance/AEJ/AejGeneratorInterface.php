<?php

namespace App\Domain\Compliance\AEJ;

use App\Models\Establishment;
use Carbon\Carbon;

interface AejGeneratorInterface
{
    /**
     * Gera o Arquivo Eletrônico de Jornada (AEJ) para o estabelecimento e competência especificados.
     * Deve extrair dados exclusivamente de ClosedPeriod + Snapshots imutáveis quando a competência
     * estiver fechada, ou gerar prévia identificada caso a competência esteja aberta.
     */
    public function generate(
        Establishment $establishment,
        int $year,
        int $month,
        ?Carbon $generationTime = null,
        bool $forcePreview = false,
    ): AejExportResult;
}
