<?php

namespace App\Domain\Compliance\AFD;

use App\Models\Establishment;
use Carbon\Carbon;

class AfdExportResult
{
    public function __construct(
        public string $content,
        public string $filename,
        public Establishment $establishment,
        public Carbon $startDate,
        public Carbon $endDate,
        public int $totalRecords,
        public string $crcChecksum,
        public bool $isHomologated = false,
        public string $signatureStatus = 'pending_certificate',
        public ?string $homologationReason = null,
    ) {}
}
