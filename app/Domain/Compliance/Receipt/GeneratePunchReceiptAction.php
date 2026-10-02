<?php

namespace App\Domain\Compliance\Receipt;

use App\Domain\Compliance\Signing\SigningServiceInterface;
use App\Models\PunchEvent;
use App\Models\PunchReceipt;
use Illuminate\Support\Str;

class GeneratePunchReceiptAction
{
    public function __construct(
        protected SigningServiceInterface $signingService
    ) {}

    /**
     * Emite ou recupera o comprovante eletronico da marcacao de ponto.
     */
    public function execute(PunchEvent $punchEvent): PunchReceipt
    {
        if ($punchEvent->receipt) {
            return $punchEvent->receipt;
        }

        $code = 'PF-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        $canonicalData = sprintf(
            '%s|%d|%d|%s|%s|%s',
            $punchEvent->id,
            $punchEvent->establishment_id,
            $punchEvent->nsr,
            $punchEvent->user_id,
            $punchEvent->occurred_at_utc->toIso8601String(),
            $punchEvent->payload_hash
        );

        $receiptHash = hash('sha256', $canonicalData);

        return PunchReceipt::create([
            'punch_event_id' => $punchEvent->id,
            'verification_code' => $code,
            'receipt_hash' => $receiptHash,
            'signature_status' => $this->signingService->status(),
            'signed_at' => $this->signingService->isAvailable() ? now() : null,
            'signature_metadata' => [
                'mode' => 'development_unsigned',
                'notice' => 'Documento de desenvolvimento / teste. Certificado ICP-Brasil pendente de configuração.',
                'software_rep_p' => 'PontoFácil 2.0',
                'inpi_status' => 'pending_registration',
            ],
        ]);
    }
}
