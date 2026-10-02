<?php

namespace App\Domain\Compliance\Signing;

interface SigningServiceInterface
{
    /**
     * Assina um binário PDF no padrão PAdES utilizando certificado digital.
     * Retorna o PDF assinado ou o original caso a assinatura esteja desabilitada.
     */
    public function signPdf(string $pdfBinary, array $metadata = []): string;

    /**
     * Gera uma assinatura destacada (.p7s CAdES) para um arquivo posicional (ex: AFD ou AEJ).
     */
    public function signDetached(string $content): string;

    /**
     * Indica se o certificado ICP-Brasil e as chaves de assinatura estão configurados e ativos.
     */
    public function isAvailable(): bool;

    /**
     * Retorna a descrição do status da assinatura ('signed', 'unsigned', 'dev_preview').
     */
    public function status(): string;
}
