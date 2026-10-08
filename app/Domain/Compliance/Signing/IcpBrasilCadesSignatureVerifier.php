<?php

namespace App\Domain\Compliance\Signing;

/**
 * Estrutura preparada para a futura integração criptográfica de verificação CAdES / ICP-Brasil (Portaria 671/2021 MTP).
 *
 * Quando implementada futuramente em produção, deverá validar:
 * 1. Estrutura ASN.1 / CMS SignedData (PKCS#7 / CAdES-BES / CAdES-T);
 * 2. Correspondência estrita do Message Digest com o hash SHA-256 do arquivo original;
 * 3. Validade matemática da assinatura RSA / ECDSA;
 * 4. Validade temporal do certificado do assinante;
 * 5. Cadeia de confiança ICP-Brasil (Autoridade Certificadora Raiz e Intermediárias);
 * 6. Consulta de revogação (LCR / CRL) ou protocolo OCSP.
 *
 * NÃO implementa verificação falsa nem heurística.
 */
class IcpBrasilCadesSignatureVerifier implements CadesSignatureVerifierInterface
{
    public function __construct(
        protected ?string $trustedCaBundlePath = null
    ) {}

    public function verify(string $content, ?string $detachedSignature): bool
    {
        if ($detachedSignature === null || trim($detachedSignature) === '') {
            return false;
        }

        // Sem biblioteca criptográfica completa e cadeia ICP-Brasil configuradas na VPS,
        // não atesta validade falsa.
        return false;
    }
}
