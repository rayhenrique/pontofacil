<?php

namespace App\Domain\Compliance\Signing;

/**
 * Contrato para verificação criptográfica de assinaturas destacadas CAdES (.p7s)
 * em arquivos fiscais (AFD do REP-P e AEJ do PTRP - Portaria 671/2021 MTP).
 */
interface CadesSignatureVerifierInterface
{
    /**
     * Verifica criptograficamente a assinatura destacada CAdES (.p7s).
     *
     * @param  string  $content  Conteúdo bruto do arquivo fiscal assinado
     * @param  string|null  $detachedSignature  Conteúdo binário ou DER da assinatura destacada (.p7s)
     * @return bool True se e somente se a assinatura for matematicamente válida, corresponder ao arquivo e emitida por ICP-Brasil confiável
     */
    public function verify(string $content, ?string $detachedSignature): bool;
}
