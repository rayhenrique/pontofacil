<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdValidator;
use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
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

        $lines = explode("\r\n", trim($result->content));
        // Header (1) + Tipo 2 (1) + Tipo 5 (2) + Tipo 7 (4) + Trailer (1) = 9 linhas
        $this->assertCount(9, $lines);

        // Cabeçalho (Tipo 1) - 236 caracteres
        $this->assertSame(236, strlen($lines[0]), 'Cabeçalho AFD Tipo 1 deve conter exatamente 236 caracteres.');
        $this->assertStringStartsWith('0000000001', $lines[0]);
        $this->assertStringContainsString('12345678000199', $lines[0]);
        $this->assertStringContainsString('PENDENTE REGISTRO', $lines[0]);

        // Registro Tipo 2 (Empregador) - 203 caracteres com CRC-16
        $this->assertSame(203, strlen($lines[1]), 'Registro Tipo 2 deve conter exatamente 203 caracteres.');
        $this->assertSame('2', $lines[1][9]);

        // Registros Tipo 5 (Trabalhador) - 189 caracteres com CRC-16
        $this->assertSame(189, strlen($lines[2]), 'Registro Tipo 5 deve conter exatamente 189 caracteres.');
        $this->assertSame('5', $lines[2][9]);
        $this->assertSame(189, strlen($lines[3]), 'Registro Tipo 5 deve conter exatamente 189 caracteres.');
        $this->assertSame('5', $lines[3][9]);

        // Registros Tipo 7 (Marcação REP-P) - 137 caracteres com SHA-256 encadeado
        for ($i = 4; $i <= 7; $i++) {
            $this->assertSame(137, strlen($lines[$i]), "Linha de marcação {$i} (Tipo 7) deve conter exatamente 137 caracteres.");
            $this->assertSame('7', $lines[$i][9]);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/i', substr($lines[$i], 73, 64));
        }

        // Trailer (Tipo 9) - 73 caracteres
        $this->assertSame(73, strlen($lines[8]), 'Trailer Tipo 9 deve conter exatamente 73 caracteres.');
        $this->assertStringStartsWith('9999999999', $lines[8]);

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

        $lines = explode("\r\n", trim($result->content));

        // Checa sequência ascendente contígua dos NSRs (1 a 7)
        for ($i = 1; $i <= 7; $i++) {
            $expectedNsr = str_pad((string) $i, 9, '0', STR_PAD_LEFT);
            $this->assertStringStartsWith($expectedNsr, $lines[$i]);
        }

        // Checa totalizadores no trailer Tipo 9 (73 caracteres)
        $trailer = $lines[8];
        $qtdTipo2 = (int) substr($trailer, 10, 9);
        $qtdTipo3 = (int) substr($trailer, 19, 9);
        $qtdTipo4 = (int) substr($trailer, 28, 9);
        $qtdTipo5 = (int) substr($trailer, 37, 9);
        $qtdTipo6 = (int) substr($trailer, 46, 9);
        $qtdTipo7 = (int) substr($trailer, 55, 9);
        $totalLinhas = (int) substr($trailer, 64, 9);

        $this->assertSame(1, $qtdTipo2);
        $this->assertSame(0, $qtdTipo3); // REP-P não usa tipo 3
        $this->assertSame(0, $qtdTipo4);
        $this->assertSame(2, $qtdTipo5);
        $this->assertSame(0, $qtdTipo6);
        $this->assertSame(4, $qtdTipo7);
        $this->assertSame(9, $totalLinhas);

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
        $invalidHeader = "0000000001112345678000199\r\n9999999999000000000000000000000000000000000000000000000000000000000000002\r\n";
        $val1 = $validator->validate($invalidHeader);
        $this->assertFalse($val1['is_valid']);
        $this->assertStringContainsString('exige exatamente 236', $val1['errors'][0]);

        // 2. Arquivo com NSR fora de ordem monotônica
        $header = str_pad('0000000001112345678000199000000000000EMPRESA TESTE', 204, ' ').'01102026311020260110202612000002';
        $punch1 = '00000000572026-10-01T12:00:00-03000111222333442026-10-01T12:00:00-0300020'.str_repeat('a', 64);
        $punch2 = '00000000372026-10-01T13:00:00-03000111222333442026-10-01T13:00:00-0300020'.str_repeat('b', 64);
        $trailer = '9999999999000000000000000000000000000000000000000000000000000000000000004';

        $invalidContent = $header."\r\n".$punch1."\r\n".$punch2."\r\n".$trailer."\r\n";
        $val2 = $validator->validate($invalidContent);

        $this->assertFalse($val2['is_valid']);
        $this->assertStringContainsString('quebrou a sequência monotônica ascendente', implode(' | ', $val2['errors']));
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
