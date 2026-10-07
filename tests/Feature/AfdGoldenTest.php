<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdValidator;
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
 * conforme o leiaute do Ministério do Trabalho e Emprego (Portaria 671/2021 MTP).
 *
 * REGRA INVIOLÁVEL:
 * NENHUM teste nesta suíte deve criar, atualizar ou sobrescrever automaticamente os arquivos de fixture.
 * As fixtures em tests/Fixtures/AFD são arquivos de referência estritamente somente-leitura.
 * Se uma fixture não existir ou for corrompida, o teste DEVE falhar.
 * Qualquer atualização de fixture deve ocorrer manualmente e de forma consciente pelo desenvolvedor/auditor.
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
     * Valida que o AFD gerado bate byte a byte com a fixture oficial congelada.
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

        // Comparação estrita byte a byte contra a fixture versionada
        $this->assertStringEqualsFile(
            $fixturePath,
            $result->content,
            'O AFD gerado difere da fixture oficial do MTE. As fixtures são somente leitura e não devem ser alteradas automaticamente.'
        );

        Carbon::setTestNow();
    }

    /**
     * Validação estrutural de tamanho de campos segundo a Portaria 671 MTE:
     * - Tipo 1 (Cabeçalho): exatamente 236 caracteres
     * - Tipo 3 (Marcação REP-P): exatamente 101 caracteres
     * - Tipo 9 (Trailer): exatamente 63 caracteres
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
        $this->assertCount(6, $lines); // Header + 4 marcações + Trailer

        // Cabeçalho (Tipo 1)
        $this->assertEquals(236, strlen($lines[0]), 'Cabeçalho AFD Tipo 1 deve conter exatamente 236 caracteres.');
        $this->assertStringStartsWith('0000000001', $lines[0]);
        $this->assertStringContainsString('12345678000199', $lines[0]);
        $this->assertStringContainsString('PENDENTE REGISTRO', $lines[0], 'INPI pendente deve constar explicitamente como PENDENTE REGISTRO.');

        // Linhas de batida (Tipo 3)
        for ($i = 1; $i <= 4; $i++) {
            $this->assertEquals(101, strlen($lines[$i]), "Linha de marcação {$i} (Tipo 3) deve conter exatamente 101 caracteres.");
            $this->assertEquals('3', $lines[$i][9]);
        }

        // Trailer (Tipo 9)
        $this->assertEquals(63, strlen($lines[5]), 'Trailer Tipo 9 deve conter exatamente 63 caracteres.');
        $this->assertStringStartsWith('9999999999', $lines[5]);

        Carbon::setTestNow();
    }

    /**
     * Validação da sequência estritamente monotônica do NSR e contadores do trailer.
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

        // Checa sequência ascendente estrita dos NSRs (1, 2, 3, 4)
        for ($i = 1; $i <= 4; $i++) {
            $expectedNsr = str_pad((string) $i, 9, '0', STR_PAD_LEFT);
            $this->assertStringStartsWith($expectedNsr, $lines[$i]);
        }

        // Checa totalizadores no trailer
        $trailer = $lines[5];
        $qtdTipo3 = (int) substr($trailer, 10, 9);
        $totalLinhas = (int) substr($trailer, 46, 9);

        $this->assertEquals(4, $qtdTipo3);
        $this->assertEquals(6, $totalLinhas);

        Carbon::setTestNow();
    }

    /**
     * Validação do cálculo de integridade CRC-32 no trailer do AFD.
     */
    public function test_afd_trailer_crc32_checksum_calculation(): void
    {
        $establishment = $this->createDeterministicTestData();

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate(
            $establishment,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-01 18:00:00', 'America/Maceio')
        );

        $this->assertNotEmpty($result->crcChecksum);
        $this->assertEquals(8, strlen($result->crcChecksum));

        $lines = explode("\r\n", trim($result->content));
        $trailer = $lines[5];
        $crcInTrailer = substr($trailer, 55, 8);

        $this->assertEquals($result->crcChecksum, $crcInTrailer);

        Carbon::setTestNow();
    }

    /**
     * Validador formal posicional (AfdValidator) detecta quebras estruturais.
     */
    public function test_afd_validator_catches_invalid_record_sizes_and_broken_nsr_sequences(): void
    {
        $validator = new AfdValidator;

        // 1. Arquivo com cabeçalho truncado
        $invalidHeader = "0000000001112345678000199\r\n99999999990000000000000000000000000000000000000000000212345678\r\n";
        $val1 = $validator->validate($invalidHeader);
        $this->assertFalse($val1['is_valid']);
        $this->assertStringContainsString('exige exatamente 236', $val1['errors'][0]);

        // 2. Arquivo com batida fora de ordem monotônica
        $header = str_pad('0000000001112345678000199000000000000EMPRESA TESTE', 204, ' ').'01102026311020260110202612000002';
        $punch1 = '0000000053011020261200-03011122233344'.str_repeat('a', 64);
        $punch2 = '0000000033011020261300-03011122233344'.str_repeat('b', 64); // NSR 3 menor que 5!
        $trailer = '999999999900000000200000000000000000000000000000000000412345678';

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
        $company = CurrentCompany::get();
        $matriz = CurrentCompany::defaultEstablishment();

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

        // AFD da Filial tem 1 registro
        $afdFilial = $generator->generate($filial, $startDate, $endDate);
        $this->assertEquals(1, $afdFilial->totalRecords);
        $this->assertStringContainsString('00000000000272', $afdFilial->content);

        // AFD da Matriz tem 0 registros para este período
        $afdMatriz = $generator->generate($matriz, $startDate, $endDate);
        $this->assertEquals(0, $afdMatriz->totalRecords);
    }

    /**
     * Cria os dados de teste determinísticos para a suíte.
     */
    protected function createDeterministicTestData(): Establishment
    {
        $company = Company::create([
            'legal_name' => 'EMPRESA TESTE MATRIZ LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

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

        $user1 = User::create([
            'name' => 'Joao da Silva',
            'email' => 'joao.silva@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user1->id,
            'sector_id' => $sector->id,
            'cpf' => '111.222.333-44',
            'job_title' => 'Operador',
        ]);

        $user2 = User::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria.oliveira@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        Employee::create([
            'user_id' => $user2->id,
            'sector_id' => $sector->id,
            'cpf' => '555.666.777-88',
            'job_title' => 'Supervisora',
        ]);

        $action = app(RecordPunchEventAction::class);

        // Joao entra as 08:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00', 'America/Maceio'));
        $action->execute(user: $user1, direction: 'in', establishment: $establishment);

        // Joao sai para almoco as 12:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', 'America/Maceio'));
        $action->execute(user: $user1, direction: 'out', establishment: $establishment);

        // Maria entra as 13:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 13:00:00', 'America/Maceio'));
        $action->execute(user: $user2, direction: 'in', establishment: $establishment);

        // Maria sai as 17:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 17:00:00', 'America/Maceio'));
        $action->execute(user: $user2, direction: 'out', establishment: $establishment);

        return $establishment;
    }
}
