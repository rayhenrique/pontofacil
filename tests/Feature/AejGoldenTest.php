<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AEJ\AejValidator;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes Oficiais de Golden Fixture do Arquivo Eletrônico de Jornada (AEJ)
 * do Programa de Tratamento de Registro de Ponto (PTRP) - Portaria 671/2021 MTP.
 *
 * REGRA INVIOLÁVEL:
 * NENHUM teste nesta suíte deve criar, atualizar ou sobrescrever automaticamente os arquivos de fixture.
 * As fixtures em tests/Fixtures/AEJ são arquivos de referência estritamente somente-leitura.
 * Se uma fixture não existir ou for corrompida, o teste DEVE falhar.
 * Qualquer atualização de fixture deve ocorrer manualmente e de forma consciente pelo desenvolvedor/auditor.
 */
class AejGoldenTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $admin;

    protected User $worker;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'EMPRESA FISCAL MODELO LTDA',
            'trade_name' => 'Fiscal Modelo',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
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
            'establishment_id' => $this->establishment->id,
        ]);

        $this->admin = User::create([
            'name' => 'Auditor Admin',
            'email' => 'admin@modelo.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->worker = User::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria@modelo.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->worker->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-202',
            'cpf' => '98765432100',
            'job_title' => 'Assistente Administrativo',
        ]);
    }

    public function test_aej_official_requires_closed_period_unless_preview(): void
    {
        $generator = app(AejGenerator_2026_07_31::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ainda não foi fechada. Para emitir o AEJ fiscal definitivo, feche a competência formalmente.');

        $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: false
        );
    }

    public function test_aej_preview_mode_generates_valid_content_marked_as_preview(): void
    {
        $generator = app(AejGenerator_2026_07_31::class);
        $validator = app(AejValidator::class);

        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 10:00:00'),
            forcePreview: true
        );

        $this->assertTrue($result->isPreview);
        $this->assertStringContainsString('PREVIA-NAO-FECHAD', $result->content);
        $this->assertStringStartsWith('AEJ_PREVIA_', $result->filename);

        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros de validação do AEJ Prévia: '.implode('; ', $validation['errors']));
    }

    /**
     * Valida que a geração oficial do AEJ é comparada byte a byte contra a golden fixture congelada.
     */
    public function test_aej_official_generation_matches_golden_fixture_byte_by_byte(): void
    {
        $fixturePath = base_path('tests/Fixtures/AEJ/golden_aej_mte_2026.txt');

        // O teste deve falhar se a fixture de referência não existir
        $this->assertFileExists(
            $fixturePath,
            "A fixture oficial {$fixturePath} não foi encontrada. O teste jamais deve criar ou sobrescrever fixtures automaticamente."
        );

        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $fixedGenTime = Carbon::parse('2026-02-01 09:30:00');

        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: $fixedGenTime,
            forcePreview: false
        );

        $this->assertFalse($result->isPreview);
        $this->assertStringStartsWith('AEJ_12345678000199_202601.txt', $result->filename);

        // Validação formal com AejValidator
        $validator = app(AejValidator::class);
        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros de validação estrutural do AEJ: '.implode('; ', $validation['errors']));

        // Comparação estrita byte a byte contra a fixture versionada
        $this->assertStringEqualsFile(
            $fixturePath,
            $result->content,
            'O AEJ gerado difere da fixture oficial do MTE. As fixtures são somente leitura e não devem ser alteradas automaticamente.'
        );
    }

    /**
     * Validação de tamanho e estrutura dos blocos do AEJ segundo a Portaria 671 MTE:
     * - Tipo 1 (Cabeçalho): 236 caracteres
     * - Tipo 2 (Relação de Empregados): 249 caracteres
     * - Tipo 3 (Horários e Escalas): 70 caracteres
     * - Tipo 4 (Marcações): 53 caracteres
     * - Tipo 5 (Apuração Mensal): 45 caracteres
     * - Tipo 6 (Banco de Horas): 52 caracteres
     * - Tipo 9 (Trailer): 72 caracteres
     */
    public function test_aej_record_structures_and_field_sizes(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00'),
            forcePreview: false
        );

        $lines = explode("\r\n", trim($result->content));
        $header = $lines[0];
        $trailer = end($lines);

        $this->assertEquals(236, strlen($header), 'Cabeçalho Tipo 1 deve conter exatamente 236 caracteres.');
        $this->assertEquals('1', $header[9]);
        $this->assertEquals('12345678000199', substr($header, 11, 14));
        $this->assertStringContainsString('PENDENTE REGISTRO', $header, 'INPI pendente deve constar explicitamente como PENDENTE REGISTRO.');

        $this->assertEquals(72, strlen($trailer), 'Trailer Tipo 9 deve conter exatamente 72 caracteres.');
        $this->assertEquals('9', $trailer[9]);
    }

    /**
     * Validação do cálculo de integridade CRC-32 no trailer do AEJ.
     */
    public function test_aej_trailer_crc32_checksum_calculation(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00'),
            forcePreview: false
        );

        $this->assertNotEmpty($result->crcChecksum);
        $this->assertEquals(8, strlen($result->crcChecksum));

        $lines = explode("\r\n", trim($result->content));
        $trailer = end($lines);
        $crcInTrailer = substr($trailer, 64, 8);

        $this->assertEquals($result->crcChecksum, $crcInTrailer);
    }

    /**
     * Cria e fecha formalmente a competência Janeiro/2026 com marcações determinísticas.
     */
    protected function createClosedPeriodWithPunches(): void
    {
        $punchAction = app(RecordPunchEventAction::class);

        Carbon::setTestNow(Carbon::parse('2026-01-05 08:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'in', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-01-05 13:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'in', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-01-05 17:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow();

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );
    }
}
