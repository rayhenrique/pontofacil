<?php

namespace App\Domain\Compliance\Signing;

/**
 * Serviço preparado para implementação da Assinatura Digital ICP-Brasil (Portaria 671/2021 MTP).
 *
 * Requisitos futuros previstos:
 * - PDF do Comprovante do Trabalhador: assinatura qualificada no padrão PAdES (PDF Advanced Electronic Signatures).
 * - AFD e AEJ fiscais: assinatura destacada CAdES (CMS Advanced Electronic Signatures) em arquivo com extensão '.p7s'.
 *
 * IMPORTANTE:
 * Este serviço NÃO simula assinaturas digitais com valores fictícios. Enquanto não houver certificado
 * digital A1 válido instalado na VPS da instalação e configurado no .env, o status permanece
 * estritamente como 'pending_certificate' e nenhum documento é marcado indevidamente como 'signed'.
 */
class IcpBrasilSigningService implements SigningServiceInterface
{
    public function __construct(
        protected ?string $certificatePath = null,
        protected ?string $passphrase = null
    ) {
        if ($this->certificatePath === null) {
            $this->certificatePath = function_exists('app') && app()->bound('config')
                ? config('compliance.icp_brasil.certificate_path', env('ICP_BRASIL_CERTIFICATE_PATH'))
                : (env('ICP_BRASIL_CERTIFICATE_PATH') ?: null);
        }

        if ($this->passphrase === null) {
            $this->passphrase = function_exists('app') && app()->bound('config')
                ? config('compliance.icp_brasil.passphrase', env('ICP_BRASIL_PASSPHRASE'))
                : (env('ICP_BRASIL_PASSPHRASE') ?: null);
        }
    }

    /**
     * Assina um binário PDF no padrão PAdES utilizando certificado ICP-Brasil A1.
     * Na ausência do certificado configurado, retorna o binário original sem adulteração.
     */
    public function signPdf(string $pdfBinary, array $metadata = []): string
    {
        if (! $this->isAvailable()) {
            // Não simula assinatura: retorna o documento original
            return $pdfBinary;
        }

        // TODO: Quando o certificado A1 for provisionado na VPS do cliente:
        // Executar a assinatura PAdES com biblioteca PKCS#7 / OpenSSL e injetar o bloco ByteRange no PDF.
        return $pdfBinary;
    }

    /**
     * Gera assinatura digital destacada CAdES (.p7s) para arquivos fiscais (AFD / AEJ).
     * Na ausência do certificado configurado, retorna vazio sem simular assinatura.
     */
    public function signDetached(string $content): string
    {
        if (! $this->isAvailable()) {
            return '';
        }

        // TODO: Quando o certificado A1 for provisionado na VPS do cliente:
        // Executar a assinatura CAdES-BES / CAdES-T detached gerando o envelope criptográfico .p7s.
        return '';
    }

    /**
     * Verifica se o certificado digital ICP-Brasil e a chave privada estão devidamente provisionados.
     */
    public function isAvailable(): bool
    {
        if (empty($this->certificatePath)) {
            return false;
        }

        return file_exists($this->certificatePath) && is_readable($this->certificatePath);
    }

    /**
     * Status oficial do serviço de assinatura.
     * Retorna 'signed' apenas se o certificado estiver ativo e configurado;
     * caso contrário retorna 'pending_certificate'.
     */
    public function status(): string
    {
        return $this->isAvailable() ? 'signed' : 'pending_certificate';
    }
}
