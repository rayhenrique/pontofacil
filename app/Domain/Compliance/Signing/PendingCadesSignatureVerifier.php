<?php

namespace App\Domain\Compliance\Signing;

/**
 * Implementação segura enquanto não houver certificado ICP-Brasil e biblioteca
 * criptográfica CAdES definitiva integrada.
 *
 * Nunca simula assinaturas com texto mágico, cabeçalhos DER 0x30 arbitrários ou envelopes PEM não verificados.
 * Retorna estritamente false.
 */
class PendingCadesSignatureVerifier implements CadesSignatureVerifierInterface
{
    public function verify(string $content, ?string $detachedSignature): bool
    {
        // Enquanto a integração criptográfica real não for concluída,
        // nenhuma assinatura destacada é declarada como válida.
        return false;
    }
}
