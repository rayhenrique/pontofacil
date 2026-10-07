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
 * - Tipo 1: Cabeçalho (236 caracteres)
 * - Tipo 2: Identificação do Empregador (203 caracteres com CRC-16)
 * - Tipo 4: Ajuste do Relógio em REP (49 caracteres com CRC-16)
 * - Tipo 5: Identificação do Empregado (189 caracteres com CRC-16)
 * - Tipo 6: Eventos Sensíveis do REP-P (136 caracteres)
 * - Tipo 7: Marcação de Ponto REP-P (137 caracteres com SHA-256 encadeado)
 * - Tipo 9: Trailer (73 caracteres)
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

    public function test_tipo_1_header_exact_length_236(): void
    {
        $nsr = '000000000';
        $tipo = '1';
        $idType = '1'; // CNPJ
        $idNumber = '12345678000199';
        $caepfCno = '000000000000';
        $razao = str_pad('EMPRESA TESTE COMPLIANCE LTDA', 150, ' ', STR_PAD_RIGHT);
        $inpi = str_pad('PENDENTE REGISTRO', 17, ' ', STR_PAD_RIGHT);
        $dtInicio = '01102026';
        $dtFim = '31102026';
        $dtGeracao = '01102026';
        $hrGeracao = '1800';
        $versao = '0002';

        $header = $nsr.$tipo.$idType.$idNumber.$caepfCno.$razao.$inpi.$dtInicio.$dtFim.$dtGeracao.$hrGeracao.$versao;

        $this->assertSame(236, strlen($header));
        $this->assertSame('0000000001', substr($header, 0, 10));
    }

    public function test_tipo_2_employer_exact_length_203_with_crc16(): void
    {
        $nsr = '000000001';
        $tipo = '2';
        $dtGravacao = '01102026';
        $hrGravacao = '0800';
        $idType = '1'; // CNPJ
        $idNumber = '12345678000199';
        $cei = str_pad('', 12, '0', STR_PAD_LEFT);
        $razao = str_pad('EMPRESA TESTE COMPLIANCE LTDA', 150, ' ', STR_PAD_RIGHT);

        $prefix = $nsr.$tipo.$dtGravacao.$hrGravacao.$idType.$idNumber.$cei.$razao;
        $this->assertSame(199, strlen($prefix));

        $crc = $this->hashService->calculateCrc16($prefix);
        $this->assertSame(4, strlen($crc));

        $record = $prefix.$crc;
        $this->assertSame(203, strlen($record));
    }

    public function test_tipo_4_time_sync_exact_length_49_with_crc16(): void
    {
        $nsr = '000000002';
        $tipo = '4';
        $dtAntes = '01102026';
        $hrAntes = '0800';
        $dtDepois = '01102026';
        $hrDepois = '0801';
        $cpfResp = '11122233344';

        $prefix = $nsr.$tipo.$dtAntes.$hrAntes.$dtDepois.$hrDepois.$cpfResp;
        $this->assertSame(45, strlen($prefix));

        $crc = $this->hashService->calculateCrc16($prefix);
        $this->assertSame(4, strlen($crc));

        $record = $prefix.$crc;
        $this->assertSame(49, strlen($record));
    }

    public function test_tipo_5_worker_mutation_exact_length_189_with_crc16(): void
    {
        $nsr = '000000003';
        $tipo = '5';
        $dtGravacao = '01102026';
        $hrGravacao = '0805';
        $operacao = 'I'; // Inclusão
        $cpf = str_pad('11122233344', 12, '0', STR_PAD_LEFT);
        $nome = str_pad('JOAO DA SILVA', 150, ' ', STR_PAD_RIGHT);

        $prefix = $nsr.$tipo.$dtGravacao.$hrGravacao.$operacao.$cpf.$nome;
        $this->assertSame(185, strlen($prefix));

        $crc = $this->hashService->calculateCrc16($prefix);
        $this->assertSame(4, strlen($crc));

        $record = $prefix.$crc;
        $this->assertSame(189, strlen($record));
    }

    public function test_tipo_6_sensitive_event_exact_length_136(): void
    {
        $nsr = '000000004';
        $tipo = '6';
        $dtHrGravacao = '2026-10-01T08:10:00-0300'; // 24 caracteres ISO
        $codEvento = '01'; // 2 caracteres
        $descricao = str_pad('DISPONIBILIDADE DO SISTEMA REP-P EM OPERACAO', 100, ' ', STR_PAD_RIGHT);

        $record = $nsr.$tipo.$dtHrGravacao.$codEvento.$descricao;
        $this->assertSame(136, strlen($record));
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

        // Marcação Gênesis (anterior = null)
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
        $prefix2 = $this->hashService->canonicalTipo7Prefix(
            nsr: $nsr2,
            occurredAtLocal: $occurred2,
            recordedAtLocal: $occurred2,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0'
        );
        $this->assertSame(73, strlen($prefix2));

        $hash2 = $this->hashService->calculateTipo7FiscalHash(
            nsr: $nsr2,
            occurredAtLocal: $occurred2,
            recordedAtLocal: $occurred2,
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

    public function test_tipo_9_trailer_exact_length_73(): void
    {
        $nsr = '999999999';
        $tipo = '9';
        $qtd2 = '000000001';
        $qtd3 = '000000000'; // REP-P não usa tipo 3
        $qtd4 = '000000001';
        $qtd5 = '000000001';
        $qtd6 = '000000001';
        $qtd7 = '000000002';
        $totalLinhas = '000000008';

        $trailer = $nsr.$tipo.$qtd2.$qtd3.$qtd4.$qtd5.$qtd6.$qtd7.$totalLinhas;
        $this->assertSame(73, strlen($trailer));
    }
}
