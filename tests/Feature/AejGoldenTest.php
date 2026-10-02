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

    public function test_aej_official_generation_from_frozen_snapshot_is_valid_and_deterministic(): void
    {
        // 1. Criar batidas reais no REP-P em Janeiro de 2026
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

        // 2. Fechar formalmente a competência congelando o snapshot
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $this->assertEquals('closed', $closedPeriod->status);
        $this->assertNotNull($closedPeriod->snapshot_hash);

        // 3. Gerar o AEJ oficial a partir do snapshot
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
        $this->assertEquals($closedPeriod->snapshot_hash, $result->snapshotHash);
        $this->assertStringStartsWith('AEJ_12345678000199_202601.txt', $result->filename);

        // 4. Validação formal do AEJ com AejValidator
        $validator = app(AejValidator::class);
        $validation = $validator->validate($result->content);

        $this->assertTrue($validation['is_valid'], 'Erros de validação estrutural do AEJ: '.implode('; ', $validation['errors']));

        // 5. Verificar linhas específicas
        $lines = explode("\r\n", trim($result->content));
        $header = $lines[0];
        $trailer = end($lines);

        $this->assertEquals(236, strlen($header));
        $this->assertEquals('1', $header[9]);
        $this->assertEquals('12345678000199', substr($header, 11, 14));
        $this->assertEquals('01012026', substr($header, 204, 8));
        $this->assertEquals('31012026', substr($header, 212, 8));
        $this->assertEquals('0002', substr($header, 232, 4));

        $this->assertEquals(72, strlen($trailer));
        $this->assertEquals('9', $trailer[9]);

        // Gravar fixture de referência (Golden File)
        $fixtureDir = base_path('tests/Fixtures/AEJ');
        if (! is_dir($fixtureDir)) {
            mkdir($fixtureDir, 0755, true);
        }
        $fixturePath = $fixtureDir.'/golden_aej_mte_2026.txt';
        file_put_contents($fixturePath, $result->content);

        // Validar que o arquivo gravado é idêntico byte a byte
        $this->assertFileExists($fixturePath);
        $this->assertEquals(file_get_contents($fixturePath), $result->content);
    }
}
