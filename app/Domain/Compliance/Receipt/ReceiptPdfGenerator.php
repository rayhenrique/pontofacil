<?php

namespace App\Domain\Compliance\Receipt;

use App\Domain\Compliance\Signing\SigningServiceInterface;
use App\Models\PunchReceipt;

class ReceiptPdfGenerator
{
    public function __construct(
        protected SigningServiceInterface $signingService
    ) {}

    /**
     * Gera o arquivo binário PDF 1.4 do Comprovante do Trabalhador.
     */
    public function generate(PunchReceipt $receipt): string
    {
        $event = $receipt->punchEvent;
        $establishment = $event->establishment;
        $company = $establishment->company;
        $user = $event->user;
        $employee = $event->employee;

        $nsrFormatted = str_pad((string) $event->nsr, 9, '0', STR_PAD_LEFT);
        $tipoStr = $event->direction === 'in' ? 'ENTRADA' : 'SAIDA';
        $dataHoraStr = $event->occurred_at_local->format('d/m/Y H:i:s');
        $cpfFormatado = $employee?->cpf ?? 'NAO CADASTRADO';
        $softwareName = $company->rep_p_software_name ?? 'PontoFacil';
        $softwareVersion = $company->rep_p_software_version ?? '2.0.0';
        $verificationUrl = $receipt->verificationUrl();

        // Linhas de texto do comprovante
        $lines = [
            ['font' => 'F2', 'size' => 14, 'x' => 50, 'y' => 780, 'text' => 'COMPROVANTE DE REGISTRO DE PONTO DO TRABALHADOR'],
            ['font' => 'F2', 'size' => 9,  'x' => 50, 'y' => 762, 'text' => '[ AMBIENTE DE TESTES / DESENVOLVIMENTO - SEM CERTIFICADO ICP-BRASIL ]'],
            ['font' => 'F1', 'size' => 8,  'x' => 50, 'y' => 750, 'text' => 'Aviso: Documento gerado em conformidade estrutural com a Portaria 671/2021 MTP.'],
            ['font' => 'F1', 'size' => 8,  'x' => 50, 'y' => 740, 'text' => 'Para validade plena de REP-P, requer assinatura PAdES ICP-Brasil e registro INPI.'],

            ['font' => 'F2', 'size' => 10, 'x' => 50, 'y' => 715, 'text' => '1. IDENTIFICACAO DO EMPREGADOR E ESTABELECIMENTO'],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 698, 'text' => 'Razao Social: '.$this->sanitize($company->legal_name)],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 684, 'text' => 'CNPJ: '.$this->formatCnpj($company->cnpj)],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 670, 'text' => 'Estabelecimento: '.$this->sanitize($establishment->name).' ('.$establishment->code.')'],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 656, 'text' => 'Localizacao: '.$this->sanitize($establishment->city.' - '.$establishment->state)],

            ['font' => 'F2', 'size' => 10, 'x' => 50, 'y' => 630, 'text' => '2. IDENTIFICACAO DO TRABALHADOR'],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 613, 'text' => 'Nome do Servidor: '.$this->sanitize($user->name)],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 599, 'text' => 'CPF: '.$this->sanitize($cpfFormatado)],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 585, 'text' => 'Cargo / Funcao: '.$this->sanitize($employee?->job_title ?? 'Nao informado')],

            ['font' => 'F2', 'size' => 10, 'x' => 50, 'y' => 560, 'text' => '3. DADOS DO REGISTRO DE PONTO (REP-P)'],
            ['font' => 'F2', 'size' => 11, 'x' => 60, 'y' => 542, 'text' => 'NUMERO SEQUENCIAL DE REGISTRO (NSR): '.$nsrFormatted],
            ['font' => 'F2', 'size' => 11, 'x' => 60, 'y' => 526, 'text' => 'OPERACAO: '.$tipoStr.' as '.$dataHoraStr],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 510, 'text' => 'Fuso Horario Oficial: '.$event->timezone.' ('.$event->utc_offset.')'],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 496, 'text' => 'Carimbo UTC Gravado: '.$event->occurred_at_utc->format('Y-m-d\TH:i:s\Z')],
            ['font' => 'F1', 'size' => 9,  'x' => 60, 'y' => 482, 'text' => 'Canal Coletor: '.$event->source.' / '.$event->collector_type],

            ['font' => 'F2', 'size' => 10, 'x' => 50, 'y' => 455, 'text' => '4. INTEGRIDADE CRIPTOGRAFICA E AUTENTICIDADE'],
            ['font' => 'F1', 'size' => 8,  'x' => 60, 'y' => 438, 'text' => 'Codigo de Verificacao: '.$receipt->verification_code],
            ['font' => 'F1', 'size' => 8,  'x' => 60, 'y' => 424, 'text' => 'Hash SHA-256 Fiscal (Portaria 671): '.($event->fiscal_hash ?? $event->payload_hash)],
            ['font' => 'F1', 'size' => 8,  'x' => 60, 'y' => 410, 'text' => 'Hash SHA-256 do Comprovante: '.$receipt->receipt_hash],
            ['font' => 'F1', 'size' => 8,  'x' => 60, 'y' => 396, 'text' => 'Software REP-P: '.$softwareName.' v'.$softwareVersion.' (Instancia Dedicada)'],
            ['font' => 'F1', 'size' => 8,  'x' => 60, 'y' => 382, 'text' => $company->isRegisteredInpi() ? ('Registro INPI: '.$company->inpi_registration_number) : 'Registro INPI: Pendente de Registro'],

            ['font' => 'F2', 'size' => 9,  'x' => 50, 'y' => 360, 'text' => 'CONSULTA DE AUTENTICIDADE PUBLICA:'],
            ['font' => 'F1', 'size' => 8,  'x' => 50, 'y' => 345, 'text' => $verificationUrl],
            ['font' => 'F1', 'size' => 7,  'x' => 50, 'y' => 332, 'text' => 'Acesse o endereco acima para checar o registro no ledger e a cadeia imutavel.'],
        ];

        // Monta o stream de comandos PostScript / PDF
        $stream = "q\n";
        // Borda do cartão superior
        $stream .= "0.85 0.85 0.85 rg\n50 735 495 1 re f\n";
        $stream .= "50 475 495 1 re f\n";
        $stream .= "50 380 495 1 re f\n";
        $stream .= "0 0 0 rg\n";

        foreach ($lines as $item) {
            $stream .= "BT\n";
            $stream .= "/{$item['font']} {$item['size']} Tf\n";
            $stream .= "{$item['x']} {$item['y']} Td\n";
            $safeText = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $item['text']);
            $stream .= "({$safeText}) Tj\n";
            $stream .= "ET\n";
        }
        $stream .= "Q\n";

        $pdfBinary = $this->buildPdfBinary($stream);

        // Desacoplamento de assinatura digital via SigningServiceInterface
        return $this->signingService->signPdf($pdfBinary, [
            'nsr' => $nsrFormatted,
            'verification_code' => $receipt->verification_code,
            'receipt_hash' => $receipt->receipt_hash,
        ]);
    }

    /**
     * Monta uma estrutura PDF 1.4 válida com tabela de referências cruzadas e trailer.
     */
    protected function buildPdfBinary(string $contentStream): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[6] = '<< /Length '.strlen($contentStream)." >>\nstream\n".$contentStream.'endstream';

        $output = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($output);
            $output .= "{$num} 0 obj\n{$obj}\nendobj\n";
        }

        $xrefOffset = strlen($output);
        $output .= "xref\n";
        $output .= '0 '.(count($objects) + 1)."\n";
        $output .= "0000000000 65535 f \n";

        foreach ($objects as $num => $obj) {
            $output .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }

        $output .= "trailer\n";
        $output .= '<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\n";
        $output .= "startxref\n";
        $output .= "{$xrefOffset}\n";
        $output .= "%%EOF\n";

        return $output;
    }

    protected function sanitize(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        // Remove acentos para compatibilidade com Type1 Helvetica padrão sem quebras
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/[^\x20-\x7E]/', '', $unaccented ?: $text);
    }

    protected function formatCnpj(?string $cnpj): string
    {
        $clean = preg_replace('/\D/', '', $cnpj ?? '');
        if (strlen($clean) === 14) {
            return substr($clean, 0, 2).'.'.substr($clean, 2, 3).'.'.substr($clean, 5, 3).'/'.substr($clean, 8, 4).'-'.substr($clean, 12, 2);
        }

        return $clean ?: '00.000.000/0001-00';
    }
}
