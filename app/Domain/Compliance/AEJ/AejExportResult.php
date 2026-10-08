<?php

namespace App\Domain\Compliance\AEJ;

use App\Models\ClosedPeriod;
use App\Models\Establishment;
use Carbon\Carbon;

class AejExportResult
{
    public function __construct(
        public string $content,
        public string $filename,
        public Establishment $establishment,
        public ?ClosedPeriod $closedPeriod,
        public Carbon $startDate,
        public Carbon $endDate,
        public int $totalRecords,
        public string $crcChecksum,
        public bool $isPreview = false,
        public ?string $snapshotHash = null,
        public string $signatureStatus = 'pending_certificate',
        public bool $isHomologated = false,
        public ?string $homologationReason = null,
        public bool $structureValid = true,
        public bool $signatureValid = false,
    ) {}
}
