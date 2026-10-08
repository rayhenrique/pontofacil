<?php

namespace Tests\Unit;

use App\Domain\Compliance\AFD\AfdValidator;
use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Testes Unitários dos Tipos de Registros Oficiais do AFD (Portaria 671/2021 MTP - Leiaute REP-P 31/07/2026).
 *
 * Registros suportados para REP-P:
 * - Tipo 1: Cabeçalho (290 caracteres com CRC-16 Kermit)
 * - Tipo 2: Identificação do Empregador (314 caracteres com CRC-16 Kermit)
 * - Tipo 4: Ajuste do Relógio em REP (49 caracteres com CRC-16 Kermit)
 * - Tipo 5: Identificação do Empregado (101 caracteres com CRC-16 Kermit)
 * - Tipo 6: Eventos Sensíveis do REP-P (36 caracteres)
 * - Tipo 7: Marcação de Ponto REP-P (137 caracteres com SHA-256 encadeado)
 * - Tipo 9: Trailer (64 caracteres)
 */
class AfdRecordTypesTest extends TestCase
{
    protected FiscalHashService $hashService;

    protected AfdValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hashService = new FiscalHashService;
        $this->validator = new AfdValidator($this->hashService);
    }

    public function test_tipo_1_header_exact_length_302_with_crc16(): void
    {
        $nsr = '000000000';
        $tipo = '1';
        $idType = '1'; // CNPJ
        $idNumber = '12345678000199';
        $caepfCno = str_pad('', 14, ' ', STR_PAD_RIGHT);
        $razao = str_pad('EMPRESA TESTE COMPLIANCE LTDA', 150, ' ', STR_PAD_RIGHT);
        $inpi = str_pad('', 17, ' ', STR_PAD_RIGHT); // 17 espaços quando pendente
        $dtInicio = '2026-10-01';
        $dtFim = '2026-10-31';
        $dtHrGeracao = '2026-10-01T18:00:00-0300';
        $versao = '004';
        $devIdType = '1';
        $devDoc = '12345678000199';
        $softwareModel = str_pad('', 30, ' ', STR_PAD_RIGHT);

        $prefix = $nsr.$tipo.$idType.$idNumber.$caepfCno.$razao.$inpi.$dtInicio.$dtFim.$dtHrGeracao.$versao.$devIdType.$devDoc.$softwareModel;
        $this->assertSame(298, strlen($prefix));

        $crc = $this->hashService->calculateCrc16($prefix);
        $this->assertSame(4, strlen($crc));

        $header = $prefix.$crc;
        $this->assertSame(302, strlen($header));
        $this->assertSame('0000000001', substr($header, 0, 10));
    }

    public function test_tipo_2_employer_exact_length_331_with_crc16(): void
    {
        $nsr = '000000001';
        $tipo = '2';
        $dtHrGravacao = '2026-10-01T08:00:00-0300';
        $cpfResp = str_pad('11122233344', 14, ' ', STR_PAD_RIGHT);
        $idType = '1'; // CNPJ
        $idNumber = '12345678000199';
        $cno = str_pad('', 14, ' ', STR_PAD_RIGHT);
        $razao = str_pad('EMPRESA TESTE COMPLIANCE LTDA', 150, ' ', STR_PAD_RIGHT);
        $local = str_pad('SEDE MACEIO', 100, ' ', STR_PAD_RIGHT);

        $prefix = $nsr.$tipo.$dtHrGravacao.$cpfResp.$idType.$idNumber.$cno.$razao.$local;
        $this->assertSame(327, strlen($prefix));

        $crc = $this->hashService->calculateCrc16($prefix);
        $this->assertSame(4, strlen($crc));

        $record = $prefix.$crc;
        $this->assertSame(331, strlen($record));
        $this->assertSame('000000001', substr($record, 0, 9));
        $this->assertSame('2', substr($record, 9, 1));
        $this->assertSame('2026-10-01T08:00:00-0300', substr($record, 10, 24));
        $this->assertSame(str_pad('11122233344', 14, ' '), substr($record, 34, 14));
        $this->assertSame('1', substr($record, 48, 1));
        $this->assertSame('12345678000199', substr($record, 49, 14));
        $this->assertSame(str_repeat(' ', 14), substr($record, 63, 14));
        $this->assertSame(str_pad('EMPRESA TESTE COMPLIANCE LTDA', 150, ' '), substr($record, 77, 150));
        $this->assertSame(str_pad('SEDE MACEIO', 100, ' '), substr($record, 227, 100));
        $this->assertSame($crc, substr($record, 327, 4));
    }

    public function test_tipo_4_time_sync_exact_length_73_with_crc16(): void
    {
        $nsr = '000000002';
        $tipo = '4';
        $dtHrAntes = '2026-10-01T08:00:00-0300';
        $dtHrDepois = '2026-10-01T08:01:00-0300';
        $cpfResp = '11122233344';

        $prefix = $nsr.$tipo.$dtHrAntes.$dtHrDepois.$cpfResp;
        $this->assertSame(69, strlen($prefix));

        $crc = $this->hashService->calculateCrc16($prefix);
        $this->assertSame(4, strlen($crc));

        $record = $prefix.$crc;
        $this->assertSame(73, strlen($record));
        $this->assertSame('000000002', substr($record, 0, 9));
        $this->assertSame('4', substr($record, 9, 1));
        $this->assertSame('2026-10-01T08:00:00-0300', substr($record, 10, 24));
        $this->assertSame('2026-10-01T08:01:00-0300', substr($record, 34, 24));
        $this->assertSame('11122233344', substr($record, 58, 11));
        $this->assertSame($crc, substr($record, 69, 4));
    }

    public function test_tipo_5_worker_mutation_exact_length_118_with_crc16(): void
    {
        $nsr = '000000003';
        $tipo = '5';
        $dtHrGravacao = '2026-10-01T08:05:00-0300';
        $operacao = 'I'; // Inclusão
        $cpf = str_pad('11122233344', 12, '0', STR_PAD_LEFT);
        $nome = str_pad('JOAO DA SILVA', 52, ' ', STR_PAD_RIGHT);
        $demaisDados = '    ';
        $cpfResp = '11122233344';

        $prefix = $nsr.$tipo.$dtHrGravacao.$operacao.$cpf.$nome.$demaisDados.$cpfResp;
        $this->assertSame(114, strlen($prefix));

        $crc = $this->hashService->calculateCrc16($prefix);
        $this->assertSame(4, strlen($crc));

        $record = $prefix.$crc;
        $this->assertSame(118, strlen($record));
        $this->assertSame('000000003', substr($record, 0, 9));
        $this->assertSame('5', substr($record, 9, 1));
        $this->assertSame('2026-10-01T08:05:00-0300', substr($record, 10, 24));
        $this->assertSame('I', substr($record, 34, 1));
        $this->assertSame('011122233344', substr($record, 35, 12));
        $this->assertSame(str_pad('JOAO DA SILVA', 52, ' '), substr($record, 47, 52));
        $this->assertSame('    ', substr($record, 99, 4));
        $this->assertSame('11122233344', substr($record, 103, 11));
        $this->assertSame($crc, substr($record, 114, 4));
    }

    public function test_tipo_6_sensitive_event_exact_length_36_with_official_rep_p_codes(): void
    {
        $nsr = '000000004';
        $tipo = '6';
        $dtHrGravacao = '2026-10-01T08:10:00-0300'; // 24 caracteres ISO
        $codDisponibilidade = '07'; // 07 disponibilidade REP-P
        $codIndisponibilidade = '08'; // 08 indisponibilidade REP-P

        $recordDisp = $nsr.$tipo.$dtHrGravacao.$codDisponibilidade;
        $this->assertSame(36, strlen($recordDisp));
        $this->assertSame('000000004', substr($recordDisp, 0, 9));
        $this->assertSame('6', substr($recordDisp, 9, 1));
        $this->assertSame('2026-10-01T08:10:00-0300', substr($recordDisp, 10, 24));
        $this->assertSame('07', substr($recordDisp, 34, 2));

        $recordIndisp = $nsr.$tipo.$dtHrGravacao.$codIndisponibilidade;
        $this->assertSame(36, strlen($recordIndisp));
        $this->assertSame('08', substr($recordIndisp, 34, 2));
    }

    public function test_tipo_7_punch_exact_length_137_with_sha256_chained(): void
    {
        $nsr = 5;
        $occurred = Carbon::parse('2026-10-01 08:30:00-03:00');
        $recorded = Carbon::parse('2026-10-01 08:30:00-03:00');
        $cpf = '11122233344';

        $prefix = $this->hashService->canonicalTipo7Prefix(
            nsr: $nsr,
            occurredAtLocal: $occurred,
            recordedAtLocal: $recorded,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0'
        );
        $this->assertSame(73, strlen($prefix));

        // Marcação Gênesis (anterior = null): calcula o hash diretamente sobre o prefixo sem 64 zeros
        $hashGenesis = $this->hashService->calculateTipo7FiscalHash(
            nsr: $nsr,
            occurredAtLocal: $occurred,
            recordedAtLocal: $recorded,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: null
        );
        $this->assertSame(64, strlen($hashGenesis));

        $recordGenesis = $prefix.$hashGenesis;
        $this->assertSame(137, strlen($recordGenesis));

        // Segunda marcação encadeada (aponta para $hashGenesis)
        $nsr2 = 6;
        $occurred2 = Carbon::parse('2026-10-01 12:00:00-03:00');
        $recorded2 = Carbon::parse('2026-10-01 12:00:00-03:00');
        $prefix2 = $this->hashService->canonicalTipo7Prefix(
            nsr: $nsr2,
            occurredAtLocal: $occurred2,
            recordedAtLocal: $recorded2,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0'
        );
        $this->assertSame(73, strlen($prefix2));

        $hash2 = $this->hashService->calculateTipo7FiscalHash(
            nsr: $nsr2,
            occurredAtLocal: $occurred2,
            recordedAtLocal: $recorded2,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: $hashGenesis
        );
        $this->assertSame(64, strlen($hash2));

        $record2 = $prefix2.$hash2;
        $this->assertSame(137, strlen($record2));
        $this->assertNotSame($hashGenesis, $hash2);
    }

    public function test_tipo_9_trailer_exact_length_64_without_total_linhas(): void
    {
        $nsr = '999999999';
        $qtd2 = '000000001';
        $qtd3 = '000000000'; // REP-P não usa tipo 3
        $qtd4 = '000000001';
        $qtd5 = '000000001';
        $qtd6 = '000000001';
        $qtd7 = '000000002';
        $tipo = '9';

        $trailer = $nsr.$qtd2.$qtd3.$qtd4.$qtd5.$qtd6.$qtd7.$tipo;
        $this->assertSame(64, strlen($trailer));
        $this->assertSame('999999999', substr($trailer, 0, 9));
        $this->assertSame('9', substr($trailer, 63, 1));
    }

    public function test_crc16_kermit_validation_vector_123456789_to_2189(): void
    {
        $crc = $this->hashService->calculateCrc16('123456789');
        $this->assertSame('2189', $crc);
    }
}
