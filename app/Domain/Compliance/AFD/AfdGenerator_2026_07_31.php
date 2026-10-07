<?php

namespace App\Domain\Compliance\AFD;

use App\Domain\Compliance\ARP\Enums\ArpEventType;
use App\Models\ArpEvent;
use App\Models\Establishment;
use App\Models\PunchEvent;
use Carbon\Carbon;

class AfdGenerator_2026_07_31 implements AfdGeneratorInterface
{
    /**
     * Versão do leiaute publicada pelo MTE em 31/07/2026.
     */
    public const LAYOUT_VERSION = '0002';

    public function generate(
        Establishment $establishment,
        Carbon $startDate,
        Carbon $endDate,
        ?Carbon $generationTime = null
    ): AfdExportResult {
        $company = $establishment->company;
        $genTime = $generationTime ?: Carbon::now($establishment->timezone ?: 'America/Maceio');

        // Extrai as marcações brutas preferencialmente do ledger fiscal central da ARP (arp_events),
        // com fallback para punch_events para preservar histórico legado.
        $arpPunches = ArpEvent::with('employee')
            ->where('establishment_id', $establishment->id)
            ->where('event_type', ArpEventType::Punch->value)
            ->whereBetween('occurred_at_local', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->orderBy('nsr', 'asc')
            ->get();

        $punches = $arpPunches->isNotEmpty()
            ? $arpPunches
            : PunchEvent::with('employee')
                ->where('establishment_id', $establishment->id)
                ->whereBetween('occurred_at_local', [
                    $startDate->copy()->startOfDay(),
                    $endDate->copy()->endOfDay(),
                ])
                ->orderBy('nsr', 'asc')
                ->get();

        $lines = [];

        // 1. Cabeçalho (Registro Tipo 1) - 236 caracteres
        $headerNsr = '000000000';
        $headerTipo = '1';
        $idType = ($establishment->identifier_type === 'cpf') ? '2' : '1';
        $idNumber = str_pad(preg_replace('/\D/', '', $establishment->identifier_number), 14, '0', STR_PAD_LEFT);
        $caepfCno = str_pad('', 12, '0', STR_PAD_LEFT);
        $legalName = mb_str_pad(mb_substr($this->sanitize($company->legal_name), 0, 150), 150, ' ', STR_PAD_RIGHT);

        // Registro INPI (17 posições). Quando pendente de homologação oficial, indica 'PENDENTE REGISTRO'.
        // O preenchimento definitivo depende do registro real no INPI. Nunca substitui por nome/versão.
        $inpiRegistration = $company->getInpiFiscalCode();

        $dtInicio = $startDate->format('dmY');
        $dtFim = $endDate->format('dmY');
        $dtGeracao = $genTime->format('dmY');
        $hrGeracao = $genTime->format('Hi');
        $versao = self::LAYOUT_VERSION;

        $lines[] = $headerNsr.$headerTipo.$idType.$idNumber.$caepfCno.$legalName.$inpiRegistration.$dtInicio.$dtFim.$dtGeracao.$hrGeracao.$versao;

        // 2. Registros de Marcação (Registro Tipo 3 - REP-P) - 101 caracteres
        $type3Count = 0;
        foreach ($punches as $punch) {
            $type3Count++;
            $nsr = str_pad((string) $punch->nsr, 9, '0', STR_PAD_LEFT);
            $tipoRegistro = '3';
            $dtMarcacao = $punch->occurred_at_local->format('dmY');
            $hrMarcacao = $punch->occurred_at_local->format('Hi');

            // Fuso horário formato -0300
            $offsetClean = preg_replace('/[^0-9+-]/', '', $punch->utc_offset ?: '-0300');
            if (strlen($offsetClean) === 3) { // ex: -03
                $offsetClean .= '00';
            }
            $offset = str_pad(substr($offsetClean, 0, 5), 4, '0', STR_PAD_RIGHT);
            if (strlen($offset) > 4) {
                $offset = substr($offset, 0, 4);
            }

            // CPF do trabalhador (11 dígitos numéricos)
            $cpfRaw = preg_replace('/\D/', '', $punch->employee?->cpf ?? '');
            $cpf = str_pad(substr($cpfRaw, 0, 11), 11, '0', STR_PAD_LEFT);

            // Hash Fiscal Oficial SHA-256 (64 hexadecimais)
            $fiscalHash = $punch->fiscal_hash ?? $punch->payload_hash;
            $hash = str_pad(strtolower(substr($fiscalHash, 0, 64)), 64, '0', STR_PAD_RIGHT);

            $lines[] = $nsr.$tipoRegistro.$dtMarcacao.$hrMarcacao.$offset.$cpf.$hash;
        }

        // 3. Trailer (Registro Tipo 9) - 63 caracteres
        $trailerNsr = '999999999';
        $trailerTipo = '9';
        $qtdTipo3 = str_pad((string) $type3Count, 9, '0', STR_PAD_LEFT);
        $qtdTipo4 = '000000000';
        $qtdTipo5 = '000000000';
        $qtdTipo7 = '000000000';
        // Total de linhas incluindo header, registros e trailer
        $totalLinhas = str_pad((string) (count($lines) + 1), 9, '0', STR_PAD_LEFT);

        // Prévia do trailer sem CRC para cálculo do checksum
        $trailerBase = $trailerNsr.$trailerTipo.$qtdTipo3.$qtdTipo4.$qtdTipo5.$qtdTipo7.$totalLinhas;

        // Calcula CRC-32 sobre todas as linhas anteriores
        $contentBeforeCrc = implode("\r\n", $lines)."\r\n".$trailerBase;
        $crcChecksum = sprintf('%08X', crc32($contentBeforeCrc));

        $lines[] = $trailerBase.$crcChecksum;

        $finalContent = implode("\r\n", $lines)."\r\n";

        $cnpjClean = preg_replace('/\D/', '', $establishment->identifier_number);
        $filename = sprintf('AFD_%s_%s_%s.txt', $cnpjClean, $startDate->format('Ymd'), $endDate->format('Ymd'));

        return new AfdExportResult(
            content: $finalContent,
            filename: $filename,
            establishment: $establishment,
            startDate: $startDate,
            endDate: $endDate,
            totalRecords: $type3Count,
            crcChecksum: $crcChecksum
        );
    }

    protected function sanitize(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/[^\x20-\x7E]/', '', $unaccented ?: $text);
    }
}
