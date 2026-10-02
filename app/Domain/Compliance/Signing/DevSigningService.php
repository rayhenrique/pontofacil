<?php

namespace App\Domain\Compliance\Signing;

class DevSigningService implements SigningServiceInterface
{
    /**
     * Modo Desenvolvimento / MVP:
     * Sem certificado digital ICP-Brasil instalado.
     * Retorna o PDF original com carimbo explícito de 'não assinado'.
     */
    public function signPdf(string $pdfBinary, array $metadata = []): string
    {
        return $pdfBinary;
    }

    public function signDetached(string $content): string
    {
        return '';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function status(): string
    {
        return 'unsigned';
    }
}
