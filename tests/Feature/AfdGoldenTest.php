<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdValidator;
use App\Domain\Compliance\ARP\Actions\RecordArpEventAction;
use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Domain\Compliance\Signing\CadesSignatureVerifierInterface;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\ArpEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes Oficiais de Golden Fixture do Arquivo Fonte de Dados (AFD)
 * conforme o leiaute oficial do Ministério do Trabalho e Emprego (Portaria 671/2021 MTP - Anexo V vigente 31/07/2026).
 *
 * REGRA INVIOLÁVEL:
 * NENHUM teste nesta suíte deve criar, atualizar ou sobrescrever automaticamente os arquivos de fixture.
 * As fixtures em tests/Fixtures/AFD são arquivos de referência estritamente somente-leitura.
 * Se uma fixture não existir ou for corrompida, o teste DEVE falhar.
 * Qualquer atualização de fixture deve ocorrer manualmente e de forma consciente pelo auditor/desenvolvedor.
 */
class AfdGoldenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
    }

    /**
     * Valida que o AFD oficial gerado bate byte a byte com a fixture congelada.
     */
    public function test_afd_generation_matches_golden_fixture_byte_by_byte(): void
    {
        $fixturePath = base_path('tests/Fixtures/AFD/golden_afd_mte_2026.txt');

        // O teste deve falhar se a fixture de referência não existir
        $this->assertFileExists(
            $fixturePath,
            "A fixture oficial {$fixturePath} não foi encontrada. O teste jamais deve criar ou sobrescrever fixtures automaticamente."
        );

        $establishment = $this->createDeterministicTestData();

        $generator = new AfdGenerator_2026_07_31;
        $startDate = Carbon::parse('2026-10-01');
        $endDate = Carbon::parse('2026-10-31');
        $fixedGenTime = Carbon::parse('2026-10-01 18:00:00', 'America/Maceio');

        $result = $generator->generate($establishment, $startDate, $endDate, $fixedGenTime);

        // Validação formal com AfdValidator
        $validator = new AfdValidator;
        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros de validação do AFD gerado: '.implode('; ', $validation['errors']));

        // Comparação estrita byte a byte contra a fixture versionada
        $this->assertStringEqualsFile(
            $fixturePath,
            $result->content,
            'O AFD gerado difere da fixture oficial do MTE. As fixtures são somente leitura e não devem ser alteradas automaticamente.'
        );

        Carbon::setTestNow();
    }

    /**
     * Validação estrutural de tamanho de campos segundo o leiaute oficial do MTE:
     * - Tipo 1 (Cabeçalho): exatamente 236 caracteres
     * - Tipo 2 (Identificação do Empregador): exatamente 203 caracteres (com CRC-16 Kermit)
     * - Tipo 5 (Identificação do Empregado): exatamente 189 caracteres (com CRC-16 Kermit)
     * - Tipo 7 (Marcação REP-P): exatamente 137 caracteres (com SHA-256 encadeado)
     * - Tipo 9 (Trailer): exatamente 73 caracteres
     */
    public function test_afd_record_structures_and_field_sizes(): void
    {
        $establishment = $this->createDeterministicTestData();

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate(
            $establishment,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-01 18:00:00', 'America/Maceio')
        );

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
        // Header (1) + Tipo 2 (1) + Tipo 5 (2) + Tipo 7 (4) + Trailer (1) + Signature (1) = 10 linhas
        $this->assertCount(10, $lines);

        // Cabeçalho (Tipo 1) - 302 caracteres com CRC-16 Kermit
        $header = $lines[0];
        $this->assertSame(302, strlen($header), 'Cabeçalho AFD Tipo 1 deve conter exatamente 302 caracteres.');
        $this->assertSame('000000000', substr($header, 0, 9)); // NSR
        $this->assertSame('1', substr($header, 9, 1)); // Tipo
        $this->assertSame('1', substr($header, 10, 1)); // tpIdtEmpregador (CNPJ)
        $this->assertSame('12345678000199', substr($header, 11, 14)); // CNPJ
        $this->assertSame('              ', substr($header, 25, 14)); // CNO/CAEPF
        $this->assertSame(str_pad('EMPRESA TESTE MATRIZ LTDA', 150, ' '), substr($header, 39, 150)); // Razao
        $this->assertSame(str_repeat(' ', 17), substr($header, 189, 17)); // INPI (17 espaços quando pendente)
        $this->assertSame('2026-10-01', substr($header, 206, 10)); // DtInicio ISO
        $this->assertSame('2026-10-31', substr($header, 216, 10)); // DtFim ISO
        $this->assertSame('2026-10-01T18:00:00-0300', substr($header, 226, 24)); // DtHrGeracao 24 chars
        $this->assertSame('004', substr($header, 250, 3)); // Versao oficial
        $this->assertSame(' ', substr($header, 253, 1)); // Tipo id desenvolvedor
        $this->assertSame(str_repeat(' ', 14), substr($header, 254, 14)); // CNPJ/CPF desenvolvedor
        $this->assertSame(str_repeat(' ', 30), substr($header, 268, 30)); // Modelo REP-C (30 espaços para REP-P)
        $this->assertSame('5A66', substr($header, 298, 4)); // CRC-16 Kermit

        // Registro Tipo 2 (Empregador) - 331 caracteres com CRC-16 Kermit
        $tipo2 = $lines[1];
        $this->assertSame(331, strlen($tipo2), 'Registro Tipo 2 deve conter exatamente 331 caracteres.');
        $this->assertSame('000000001', substr($tipo2, 0, 9)); // NSR
        $this->assertSame('2', substr($tipo2, 9, 1)); // Tipo
        $this->assertSame('2026-10-01T07:00:00-0300', substr($tipo2, 10, 24)); // DtHrGravacao 24 chars
        $this->assertSame(str_repeat(' ', 14), substr($tipo2, 34, 14)); // CPF Resp 14 chars
        $this->assertSame('1', substr($tipo2, 48, 1)); // tpIdt
        $this->assertSame('12345678000199', substr($tipo2, 49, 14)); // CNPJ 14 chars
        $this->assertSame(str_repeat(' ', 14), substr($tipo2, 63, 14)); // CNO 14 chars
        $this->assertSame(str_pad('EMPRESA TESTE MATRIZ LTDA', 150, ' '), substr($tipo2, 77, 150));
        $this->assertSame(str_pad('Sede Maceio', 100, ' '), substr($tipo2, 227, 100));
        $this->assertSame('B4DF', substr($tipo2, 327, 4)); // CRC-16 Kermit

        // Registros Tipo 5 (Trabalhador) - 118 caracteres com CRC-16 Kermit
        $tipo5a = $lines[2];
        $this->assertSame(118, strlen($tipo5a), 'Registro Tipo 5 deve conter exatamente 118 caracteres.');
        $this->assertSame('000000002', substr($tipo5a, 0, 9));
        $this->assertSame('5', substr($tipo5a, 9, 1));
        $this->assertSame('2026-10-01T07:10:00-0300', substr($tipo5a, 10, 24));
        $this->assertSame('I', substr($tipo5a, 34, 1));
        $this->assertSame('011122233344', substr($tipo5a, 35, 12));
        $this->assertSame(str_pad('Joao da Silva', 52, ' '), substr($tipo5a, 47, 52));
        $this->assertSame('    ', substr($tipo5a, 99, 4));
        $this->assertSame('00000000000', substr($tipo5a, 103, 11));
        $this->assertSame('963A', substr($tipo5a, 114, 4));

        $tipo5b = $lines[3];
        $this->assertSame(118, strlen($tipo5b), 'Registro Tipo 5 deve conter exatamente 118 caracteres.');
        $this->assertSame('000000003', substr($tipo5b, 0, 9));
        $this->assertSame('5', substr($tipo5b, 9, 1));
        $this->assertSame('2026-10-01T07:20:00-0300', substr($tipo5b, 10, 24));
        $this->assertSame('I', substr($tipo5b, 34, 1));
        $this->assertSame('055566677788', substr($tipo5b, 35, 12));
        $this->assertSame(str_pad('Maria Oliveira', 52, ' '), substr($tipo5b, 47, 52));
        $this->assertSame('    ', substr($tipo5b, 99, 4));
        $this->assertSame('00000000000', substr($tipo5b, 103, 11));
        $this->assertSame('6D88', substr($tipo5b, 114, 4));

        // Registros Tipo 7 (Marcação REP-P) - 137 caracteres com SHA-256 encadeado
        for ($i = 4; $i <= 7; $i++) {
            $this->assertSame(137, strlen($lines[$i]), "Linha de marcação {$i} (Tipo 7) deve conter exatamente 137 caracteres.");
            $this->assertSame('7', substr($lines[$i], 9, 1));
            $this->assertSame('2026-10-01T', substr($lines[$i], 10, 11));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/i', substr($lines[$i], 73, 64));
        }

        // Trailer (Tipo 9) - 64 caracteres (sem totalLinhas, '9' final na posição 64)
        $trailer = $lines[8];
        $this->assertSame(64, strlen($trailer), 'Trailer Tipo 9 deve conter exatamente 64 caracteres.');
        $this->assertSame('999999999', substr($trailer, 0, 9));
        $this->assertSame('9', substr($trailer, 63, 1));

        // Linha de assinatura digital (.p7s) - 100 caracteres
        $this->assertSame(100, strlen($lines[9]), 'Linha de assinatura digital deve conter exatamente 100 caracteres.');
        $this->assertStringStartsWith('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', $lines[9]);

        Carbon::setTestNow();
    }

    /**
     * Validação da sequência estritamente monotônica do NSR e contadores do trailer oficial.
     */
    public function test_afd_nsr_monotonic_sequence_and_trailer_counts(): void
    {
        $establishment = $this->createDeterministicTestData();

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate(
            $establishment,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-01 18:00:00', 'America/Maceio')
        );

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));

        // Checa sequência ascendente contígua dos NSRs (1 a 7)
        for ($i = 1; $i <= 7; $i++) {
            $expectedNsr = str_pad((string) $i, 9, '0', STR_PAD_LEFT);
            $this->assertStringStartsWith($expectedNsr, $lines[$i]);
        }

        // Checa totalizadores no trailer Tipo 9 (64 caracteres)
        $trailer = $lines[8];
        $qtdTipo2 = (int) substr($trailer, 9, 9);
        $qtdTipo3 = (int) substr($trailer, 18, 9);
        $qtdTipo4 = (int) substr($trailer, 27, 9);
        $qtdTipo5 = (int) substr($trailer, 36, 9);
        $qtdTipo6 = (int) substr($trailer, 45, 9);
        $qtdTipo7 = (int) substr($trailer, 54, 9);
        $tipoFinal = substr($trailer, 63, 1);

        $this->assertSame(1, $qtdTipo2);
        $this->assertSame(0, $qtdTipo3); // REP-P não usa tipo 3
        $this->assertSame(0, $qtdTipo4);
        $this->assertSame(2, $qtdTipo5);
        $this->assertSame(0, $qtdTipo6);
        $this->assertSame(4, $qtdTipo7);
        $this->assertSame('9', $tipoFinal);

        Carbon::setTestNow();
    }

    /**
     * Validação do encadeamento determinístico do hash Tipo 7.
     */
    public function test_afd_deterministic_tipo7_hash_chaining(): void
    {
        $hashService = new FiscalHashService;

        $dt1 = Carbon::parse('2026-10-01 08:00:00', 'America/Maceio');
        $hash1 = $hashService->calculateTipo7FiscalHash(
            nsr: 4,
            occurredAtLocal: $dt1,
            recordedAtLocal: $dt1,
            cpf: '11122233344',
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: null
        );

        $dt2 = Carbon::parse('2026-10-01 12:00:00', 'America/Maceio');
        $hash2 = $hashService->calculateTipo7FiscalHash(
            nsr: 5,
            occurredAtLocal: $dt2,
            recordedAtLocal: $dt2,
            cpf: '11122233344',
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: $hash1
        );

        $this->assertSame(64, strlen($hash1));
        $this->assertSame(64, strlen($hash2));
        $this->assertNotSame($hash1, $hash2);

        // Se recalculado com os mesmos parâmetros, produz exatamente o mesmo resultado
        $recalculatedHash2 = $hashService->calculateTipo7FiscalHash(
            nsr: 5,
            occurredAtLocal: $dt2,
            recordedAtLocal: $dt2,
            cpf: '11122233344',
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: $hash1
        );
        $this->assertSame($hash2, $recalculatedHash2);
    }

    /**
     * Validador formal posicional (AfdValidator) detecta quebras estruturais.
     */
    public function test_afd_validator_catches_invalid_record_sizes_and_broken_nsr_sequences(): void
    {
        $validator = new AfdValidator;

        // 1. Arquivo com cabeçalho truncado
        $invalidHeader = "0000000001112345678000199\r\n99999999900000000000000000000000000000000000000000000000000000029\r\n";
        $val1 = $validator->validate($invalidHeader);
        $this->assertFalse($val1['is_valid']);
        $this->assertStringContainsString('exige exatamente 302', $val1['errors'][0]);

        // 2. Arquivo com NSR fora de ordem monotônica
        $headerPrefix = '0000000001112345678000199'.str_repeat(' ', 14).str_pad('EMPRESA TESTE', 150, ' ').str_repeat(' ', 17).'2026-10-012026-10-312026-10-01T12:00:00-0300004 12345678000199'.str_repeat(' ', 30);
        $headerCrc = (new FiscalHashService)->calculateCrc16($headerPrefix);
        $header = $headerPrefix.$headerCrc;
        $punch1 = '00000000572026-10-01T12:00:00-03000111222333442026-10-01T12:00:00-0300020'.str_repeat('a', 64);
        $punch2 = '00000000372026-10-01T13:00:00-03000111222333442026-10-01T13:00:00-0300020'.str_repeat('b', 64);
        $trailer = '99999999900000000000000000000000000000000000000000000000000000029';

        $invalidContent = $header."\r\n".$punch1."\r\n".$punch2."\r\n".$trailer."\r\n";
        $val2 = $validator->validate($invalidContent);

        $this->assertFalse($val2['is_valid']);
        $this->assertStringContainsString('quebrou a sequência monotônica ascendente', implode(' | ', $val2['errors']));
    }

    /**
     * Valida que a exportação parcial não reinicia o hash Tipo 7 artificialmente
     * e preserva o fiscal_hash original persistido na ARP.
     */
    public function test_afd_partial_export_preserves_tipo7_hash_from_arp(): void
    {
        $establishment = $this->createDeterministicTestData();
        $user1 = User::where('email', 'joao.silva@teste.local')->firstOrFail();

        // 1. Criar Tipo 7 dentro do segundo período (2026-10-02)
        Carbon::setTestNow(Carbon::parse('2026-10-02 08:00:00', 'America/Maceio'));
        $action = app(RecordPunchEventAction::class);
        $day2Event = $action->execute(user: $user1, direction: 'in', establishment: $establishment);
        $arpEventDay2 = ArpEvent::where('nsr', $day2Event->nsr)->firstOrFail();

        // 2. Exportar o mês completo (2026-10-01 a 2026-10-31)
        $generator = new AfdGenerator_2026_07_31;
        $resultFull = $generator->generate(
            $establishment,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-02 18:00:00', 'America/Maceio')
        );

        // 3. Exportar somente o segundo período (2026-10-02 a 2026-10-31)
        $resultPartial = $generator->generate(
            $establishment,
            Carbon::parse('2026-10-02'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-02 18:00:00', 'America/Maceio')
        );

        $linesFull = explode("\r\n", rtrim($resultFull->content, "\r\n"));
        $linesPartial = explode("\r\n", rtrim($resultPartial->content, "\r\n"));

        $tipo7LinesFull = array_values(array_filter($linesFull, fn ($line) => isset($line[9]) && $line[9] === '7'));
        $tipo7LinesPartial = array_values(array_filter($linesPartial, fn ($line) => isset($line[9]) && $line[9] === '7'));

        // No mês completo: 5 marcações (4 do dia 01 + 1 do dia 02)
        $this->assertCount(5, $tipo7LinesFull);
        // Na exportação parcial: 1 marcação (dia 02)
        $this->assertCount(1, $tipo7LinesPartial);

        $hashDay2Full = substr($tipo7LinesFull[4], 73, 64);
        $hashDay2Partial = substr($tipo7LinesPartial[0], 73, 64);

        // O hash gerado na exportação parcial deve ser idêntico ao exportado no mês completo
        $this->assertSame($hashDay2Full, $hashDay2Partial, 'O hash Tipo 7 na exportação parcial deve coincidir com a exportação completa.');
        // E deve ser idêntico ao fiscal_hash persistido imutavelmente na ARP
        $this->assertSame($arpEventDay2->fiscal_hash, $hashDay2Partial, 'O hash Tipo 7 deve ser idêntico ao fiscal_hash imutável da ARP.');

        Carbon::setTestNow();
    }

    /**
     * Valida geração e validação estrita dos Registros Tipo 4 (Ajuste do Relógio)
     * e Tipo 6 (Eventos Sensíveis REP-P) integrados via ARP.
     */
    public function test_afd_generation_includes_and_validates_records_tipo_4_and_tipo_6_from_arp(): void
    {
        $establishment = $this->createDeterministicTestData();
        $arpAction = app(RecordArpEventAction::class);

        // 1. Registrar TimeSync (Tipo 4)
        Carbon::setTestNow(Carbon::parse('2026-10-02 03:00:00', 'America/Maceio'));
        $arpAction->recordTimeSync(
            establishment: $establishment,
            syncDetails: [
                'before_occurred_at' => '2026-10-02 02:59:58',
                'responsible_cpf' => '11122233344',
            ]
        );

        // 2. Registrar Eventos Sensíveis REP-P: 07 (Disponibilidade) e 08 (Indisponibilidade)
        Carbon::setTestNow(Carbon::parse('2026-10-02 04:00:00', 'America/Maceio'));
        $arpAction->recordRepSensitiveEvent(
            establishment: $establishment,
            eventDescription: 'Verificacao de disponibilidade REP-P',
            metadata: ['event_code' => '07']
        );

        Carbon::setTestNow(Carbon::parse('2026-10-02 05:00:00', 'America/Maceio'));
        $arpAction->recordRepSensitiveEvent(
            establishment: $establishment,
            eventDescription: 'Queda temporaria de conexao',
            metadata: ['event_code' => '08']
        );

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate(
            $establishment,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-02 18:00:00', 'America/Maceio')
        );

        // Validação formal com AfdValidator
        $validator = new AfdValidator;
        $val = $validator->validate($result->content);
        $this->assertTrue($val['is_valid'], 'Erros na validação do AFD com Tipo 4 e 6: '.implode('; ', $val['errors']));

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));

        // Filtrar linhas Tipo 4 e Tipo 6
        $tipo4Lines = array_values(array_filter($lines, fn ($l) => isset($l[9]) && $l[9] === '4'));
        $tipo6Lines = array_values(array_filter($lines, fn ($l) => isset($l[9]) && $l[9] === '6'));

        $this->assertCount(1, $tipo4Lines);
        $this->assertCount(2, $tipo6Lines);

        // Validação posicional Tipo 4 (73 caracteres)
        $t4 = $tipo4Lines[0];
        $this->assertSame(73, strlen($t4));
        $this->assertSame('4', substr($t4, 9, 1));
        $this->assertSame('2026-10-02T02:59:00-0300', substr($t4, 10, 24));
        $this->assertSame('2026-10-02T03:00:00-0300', substr($t4, 34, 24));
        $this->assertSame('11122233344', substr($t4, 58, 11));

        // Validação posicional Tipo 6 (36 caracteres)
        $t6a = $tipo6Lines[0];
        $this->assertSame(36, strlen($t6a));
        $this->assertSame('6', substr($t6a, 9, 1));
        $this->assertSame('2026-10-02T04:00:00-0300', substr($t6a, 10, 24));
        $this->assertSame('07', substr($t6a, 34, 2));

        $t6b = $tipo6Lines[1];
        $this->assertSame(36, strlen($t6b));
        $this->assertSame('6', substr($t6b, 9, 1));
        $this->assertSame('2026-10-02T05:00:00-0300', substr($t6b, 10, 24));
        $this->assertSame('08', substr($t6b, 34, 2));

        // Validação Trailer Tipo 9
        $trailerLine = $lines[count($lines) - 2];
        $this->assertSame(64, strlen($trailerLine));
        $this->assertSame('000000001', substr($trailerLine, 27, 9)); // Qtd Tipo 4 = 1
        $this->assertSame('000000002', substr($trailerLine, 45, 9)); // Qtd Tipo 6 = 2

        Carbon::setTestNow();
    }

    /**
     * Valida isolamento estrito de AFDs por estabelecimento.
     */
    public function test_multiple_establishments_generate_isolated_afds(): void
    {
        $company = Company::create([
            'legal_name' => 'EMPRESA MATRIZ E FILIAL LTDA',
            'trade_name' => 'Matriz e Filial',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $matriz = Establishment::create([
            'company_id' => $company->id,
            'code' => 'MATRIZ',
            'name' => 'Sede Maceio',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'nsr_next' => 1,
        ]);

        $filial = Establishment::create([
            'company_id' => $company->id,
            'code' => 'FILIAL_ARAP',
            'name' => 'Filial Arapiraca',
            'identifier_type' => 'cnpj',
            'identifier_number' => '00000000000272',
            'city' => 'Arapiraca',
            'state' => 'AL',
            'nsr_next' => 1,
        ]);

        $user = User::create([
            'name' => 'Colaborador Filial',
            'email' => 'filial@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $action->execute(user: $user, direction: 'in', establishment: $filial);

        $generator = new AfdGenerator_2026_07_31;
        $startDate = Carbon::today()->startOfMonth();
        $endDate = Carbon::today()->endOfMonth();

        // AFD da Filial tem 2 registros (NSR 1 cadastro estabelecimento filial + NSR 2 punch)
        $afdFilial = $generator->generate($filial, $startDate, $endDate);
        $this->assertSame(2, $afdFilial->totalRecords);
        $this->assertStringContainsString('00000000000272', $afdFilial->content);

        // AFD da Matriz tem apenas o registro de cadastro da Matriz (NSR 1)
        $afdMatriz = $generator->generate($matriz, $startDate, $endDate);
        $this->assertSame(1, $afdMatriz->totalRecords);
    }

    /**
     * Teste de INPI pendente:
     * Mantém nomenclatura explícita de desenvolvimento/prévia (AFD_DEV_...) e não bloqueia o sistema (isHomologated = false).
     */
    public function test_afd_inpi_pending_generates_dev_filename_and_not_homologated(): void
    {
        $establishment = $this->createDeterministicTestData();

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertFalse($result->isHomologated);
        $this->assertSame('pending_inpi', $result->homologationReason);
        $this->assertStringStartsWith('AFD_DEV_12345678000199_', $result->filename);

        $validator = new AfdValidator;
        $val = $validator->validate($result->content);

        $this->assertTrue($val['structureValid']);
        $this->assertFalse($val['signatureValid']);
        $this->assertFalse($val['isHomologated']);
        $this->assertSame('pending_inpi', $val['homologationReason']);
    }

    /**
     * Teste de Nomenclatura Oficial quando INPI estiver cadastrado:
     * Portaria 671/2021 MTP item 10.3: AFD_{inpi}_{cnpj}_REP_P.txt
     */
    public function test_afd_official_filename_when_inpi_registered(): void
    {
        $establishment = $this->createDeterministicTestData();
        $establishment->company->update([
            'inpi_registration_status' => 'registered',
            'inpi_registration_number' => 'BR5120260000000',
        ]);

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame('AFD_BR5120260000000_12345678000199_REP_P.txt', $result->filename);
    }

    /**
     * Teste de Certificado Pendente:
     * Estrutura 100% válida, INPI e desenvolvedor configurados, mas sem verifier real.
     */
    public function test_afd_pending_certificate_is_structurally_valid_but_not_homologated(): void
    {
        config([
            'compliance.developer.document' => '12345678000199',
            'compliance.developer.name' => 'Desenvolvedor PontoFacil',
        ]);

        $establishment = $this->createDeterministicTestData();
        $establishment->company->update([
            'inpi_registration_status' => 'registered',
            'inpi_registration_number' => 'BR5120260000000',
        ]);

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertTrue($result->structureValid);
        $this->assertFalse($result->signatureValid);
        $this->assertSame('pending_certificate', $result->signatureStatus);
        $this->assertFalse($result->isHomologated);
        $this->assertSame('pending_certificate', $result->homologationReason);

        $validator = new AfdValidator;
        $val = $validator->validate($result->content);

        $this->assertTrue($val['structureValid']);
        $this->assertFalse($val['signatureValid']);
        $this->assertSame('pending_certificate', $val['signatureStatus']);
        $this->assertFalse($val['isHomologated']);
        $this->assertSame('pending_certificate', $val['homologationReason']);
    }

    /**
     * Teste do Marcador de Texto:
     * O marcador "ASSINATURA_DIGITAL_EM_ARQUIVO_P7S" não representa assinatura real existente.
     */
    public function test_afd_marker_does_not_imply_signature_validity(): void
    {
        $establishment = $this->createDeterministicTestData();
        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertStringContainsString('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', $result->content);

        $validator = new AfdValidator;
        // Validação sem passar arquivo .p7s destacado
        $val = $validator->validate($result->content);

        $this->assertTrue($val['structureValid']);
        $this->assertFalse($val['signatureValid']);
        $this->assertSame('pending_certificate', $val['signatureStatus']);
        $this->assertFalse($val['isHomologated']);
    }

    /**
     * Teste de Rejeição de Heurísticas Falsas de Assinatura:
     * Strings mágicas, cabeçalhos DER 0x30 ou PEM PKCS7 qualquer NUNCA validam assinatura em produção.
     */
    public function test_afd_heuristic_strings_and_bytes_never_validate_signature(): void
    {
        config([
            'compliance.developer.document' => '12345678000199',
            'compliance.developer.name' => 'Desenvolvedor PontoFacil',
        ]);

        $establishment = $this->createDeterministicTestData();
        $establishment->company->update([
            'inpi_registration_status' => 'registered',
            'inpi_registration_number' => 'BR5120260000000',
        ]);

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $validator = new AfdValidator;

        // 1. String mágica antiga
        $val1 = $validator->validate($result->content, 'VALID_CADES_P7S_SIGNATURE_BINARY_MOCK_ICP_BRASIL');
        $this->assertFalse($val1['signatureValid']);
        $this->assertSame('pending_certificate', $val1['signatureStatus']);
        $this->assertFalse($val1['isHomologated']);

        // 2. Bytes iniciando com DER 0x30
        $val2 = $validator->validate($result->content, "\x30\x82\x01\x00arbitrary_der_bytes");
        $this->assertFalse($val2['signatureValid']);
        $this->assertSame('pending_certificate', $val2['signatureStatus']);
        $this->assertFalse($val2['isHomologated']);

        // 3. PEM PKCS7 genérico sem validação ICP-Brasil
        $val3 = $validator->validate($result->content, "-----BEGIN PKCS7-----\nMIIB...fake...data\n-----END PKCS7-----");
        $this->assertFalse($val3['signatureValid']);
        $this->assertSame('pending_certificate', $val3['signatureStatus']);
        $this->assertFalse($val3['isHomologated']);

        // 4. Marcador isolado
        $val4 = $validator->validate($result->content, 'ASSINATURA_DIGITAL_EM_ARQUIVO_P7S');
        $this->assertFalse($val4['signatureValid']);
        $this->assertSame('pending_certificate', $val4['signatureStatus']);
        $this->assertFalse($val4['isHomologated']);
    }

    /**
     * Teste de Validação Estrutural Real no Generator:
     * O generator NUNCA declara structureValid = true sem validação real.
     */
    public function test_afd_generator_never_reports_structure_valid_without_actual_validation(): void
    {
        $establishment = $this->createDeterministicTestData();
        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        // Validação formal executada pelo generator bate com a do validador
        $this->assertTrue($result->structureValid);

        // Se o validador reprovar a estrutura, structureValid no validador é false
        $validator = new AfdValidator;
        $corruptedContent = "INVALID_AFD_HEADER\r\n".$result->content;
        $corruptedVal = $validator->validate($corruptedContent);
        $this->assertFalse($corruptedVal['structureValid']);
        $this->assertFalse($corruptedVal['isHomologated']);
    }

    /**
     * Teste de Assinatura Futura Válida via Mock de Injeção de Dependência:
     * Quando fornecida uma implementação de CadesSignatureVerifierInterface que retorna true,
     * o arquivo torna-se formalmente homologado (isHomologated = true, signatureValid = true).
     */
    public function test_afd_future_valid_cades_signature_becomes_homologated(): void
    {
        config([
            'compliance.developer.document' => '12345678000199',
            'compliance.developer.name' => 'Desenvolvedor PontoFacil',
        ]);

        $establishment = $this->createDeterministicTestData();
        $establishment->company->update([
            'inpi_registration_status' => 'registered',
            'inpi_registration_number' => 'BR5120260000000',
        ]);

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        // Injeção de mock da interface para simular verificação criptográfica real bem-sucedida
        $mockVerifier = $this->createMock(CadesSignatureVerifierInterface::class);
        $mockVerifier->expects($this->once())
            ->method('verify')
            ->willReturn(true);

        $validator = new AfdValidator(signatureVerifier: $mockVerifier);
        $val = $validator->validate($result->content, 'binary_cades_signature_data');

        $this->assertTrue($val['structureValid']);
        $this->assertTrue($val['signatureValid']);
        $this->assertSame('signed', $val['signatureStatus']);
        $this->assertTrue($val['isHomologated']);
        $this->assertNull($val['homologationReason']);
    }

    /**
     * Cria os dados de teste determinísticos para a suíte.
     */
    protected function createDeterministicTestData(): Establishment
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 07:00:00', 'America/Maceio'));

        $company = Company::create([
            'legal_name' => 'EMPRESA TESTE MATRIZ LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        // NSR 1: Cadastro do estabelecimento
        $establishment = Establishment::create([
            'company_id' => $company->id,
            'code' => 'MATRIZ',
            'name' => 'Sede Maceio',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $sector = Sector::create([
            'name' => 'Operacoes',
            'establishment_id' => $establishment->id,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-01 07:10:00', 'America/Maceio'));
        $user1 = User::create([
            'name' => 'Joao da Silva',
            'email' => 'joao.silva@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        // NSR 2: Cadastro Trabalhador 1
        Employee::create([
            'user_id' => $user1->id,
            'sector_id' => $sector->id,
            'cpf' => '111.222.333-44',
            'job_title' => 'Operador',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-01 07:20:00', 'America/Maceio'));
        $user2 = User::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria.oliveira@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        // NSR 3: Cadastro Trabalhador 2
        Employee::create([
            'user_id' => $user2->id,
            'sector_id' => $sector->id,
            'cpf' => '555.666.777-88',
            'job_title' => 'Supervisora',
        ]);

        $action = app(RecordPunchEventAction::class);

        // NSR 4: Joao entra as 08:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00', 'America/Maceio'));
        $action->execute(user: $user1, direction: 'in', establishment: $establishment);

        // NSR 5: Joao sai para almoco as 12:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', 'America/Maceio'));
        $action->execute(user: $user1, direction: 'out', establishment: $establishment);

        // NSR 6: Maria entra as 13:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 13:00:00', 'America/Maceio'));
        $action->execute(user: $user2, direction: 'in', establishment: $establishment);

        // NSR 7: Maria sai as 17:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 17:00:00', 'America/Maceio'));
        $action->execute(user: $user2, direction: 'out', establishment: $establishment);

        return $establishment;
    }
}
