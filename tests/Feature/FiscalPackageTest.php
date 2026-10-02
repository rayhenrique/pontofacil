<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\FiscalPackage\GenerateFiscalPackageAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class FiscalPackageTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $admin;

    protected User $auditor;

    protected User $employeeUser;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'EMPRESA PACOTE FISCAL LTDA',
            'trade_name' => 'Fiscal Package Test',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
            'name' => 'Sede Fiscal',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $sector = Sector::create([
            'name' => 'Financeiro',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->admin = User::create([
            'name' => 'Administrador RH',
            'email' => 'admin.pkg@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->auditor = User::create([
            'name' => 'Auditor Externo',
            'email' => 'auditor@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Auditor,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Funcionario Comum',
            'email' => 'comum@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-500',
            'cpf' => '12312312344',
            'job_title' => 'Assistente Financeiro',
        ]);
    }

    public function test_generate_fiscal_package_creates_valid_zip_with_all_regulatory_files(): void
    {
        // 1. Fechar competência
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        // 2. Gerar Pacote Fiscal
        $action = app(GenerateFiscalPackageAction::class);
        $result = $action->execute(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            requestedBy: $this->admin
        );

        $this->assertFileExists($result['zip_path']);
        $this->assertTrue($result['is_closed']);
        $this->assertEquals($closedPeriod->snapshot_hash, $result['period_hash']);
        $this->assertEquals('PACOTE_FISCAL_MTE_12345678000199_202601.zip', $result['filename']);

        // 3. Inspecionar conteúdo do ZIP
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($result['zip_path']));

        $expectedFiles = [
            'AFD_12345678000199_20260101_20260131.txt',
            'AEJ_12345678000199_202601.txt',
            'ESPELHO_PONTO_RESUMO_12345678000199_202601.txt',
            'EXTRATO_BANCO_HORAS_12345678000199_202601.txt',
            'LEIA-ME_FISCALIZACAO.txt',
            'HASHES_SHA256.txt',
        ];

        foreach ($expectedFiles as $expected) {
            $this->assertNotFalse($zip->locateName($expected), "Arquivo esperado {$expected} não encontrado no ZIP.");
        }

        // 4. Validar o manifesto de hashes dentro do ZIP
        $manifest = $zip->getFromName('HASHES_SHA256.txt');
        $this->assertNotEmpty($manifest);
        $this->assertStringContainsString('MANIFESTO DE INTEGRIDADE FORENSE - PACOTE FISCAL MTE', $manifest);
        $this->assertStringContainsString($closedPeriod->snapshot_hash, $manifest);

        $zip->close();
        @unlink($result['zip_path']);
    }

    public function test_admin_and_auditor_can_download_fiscal_package_while_regular_employee_is_forbidden(): void
    {
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $packageUrl = route('admin.fiscalizacao.package', [
            'establishmentId' => $this->establishment->id,
            'year' => 2026,
            'month' => 1,
        ]);

        // 1. Admin pode baixar (HTTP 200)
        $responseAdmin = $this->actingAs($this->admin)->get($packageUrl);
        $responseAdmin->assertOk();
        $this->assertStringContainsString('application/zip', $responseAdmin->headers->get('content-type'));

        // 2. Auditor pode baixar (HTTP 200)
        $responseAuditor = $this->actingAs($this->auditor)->get($packageUrl);
        $responseAuditor->assertOk();

        // 3. Funcionário comum recebe 403 Forbidden
        $responseEmployee = $this->actingAs($this->employeeUser)->get($packageUrl);
        $responseEmployee->assertForbidden();
    }

    public function test_individual_afd_and_aej_downloads_work_for_auditor(): void
    {
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        $afdUrl = route('admin.fiscalizacao.afd', [
            'establishmentId' => $this->establishment->id,
            'year' => 2026,
            'month' => 1,
        ]);

        $aejUrl = route('admin.fiscalizacao.aej', [
            'establishmentId' => $this->establishment->id,
            'year' => 2026,
            'month' => 1,
        ]);

        // Auditor baixa AFD
        $responseAfd = $this->actingAs($this->auditor)->get($afdUrl);
        $responseAfd->assertOk();
        $this->assertStringContainsString('AFD_', $responseAfd->headers->get('content-disposition'));

        // Auditor baixa AEJ
        $responseAej = $this->actingAs($this->auditor)->get($aejUrl);
        $responseAej->assertOk();
        $this->assertStringContainsString('AEJ_', $responseAej->headers->get('content-disposition'));
    }
}
