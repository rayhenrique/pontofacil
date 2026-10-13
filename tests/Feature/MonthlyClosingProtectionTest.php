<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\MonthlyAttendanceReview;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ReviewForClose;
use Tests\TestCase;

class MonthlyClosingProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $admin;

    protected User $manager;

    protected User $auditor;

    protected ?Employee $employee = null;

    protected ?WorkSchedule $schedule = null;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Fechamento Protegido Co', 'trade_name' => 'Fechamento', 'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil', 'rep_p_software_version' => '2.0.0',
        ]);
        $this->establishment = Establishment::create([
            'company_id' => $this->company->id, 'name' => 'Sede', 'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@fechamento.local', 'password' => 'x', 'role' => UserRole::Admin,
        ]);
        $this->manager = User::create([
            'name' => 'Gestor', 'email' => 'gestor@fechamento.local', 'password' => 'x', 'role' => UserRole::Manager,
        ]);
        $this->auditor = User::create([
            'name' => 'Auditor', 'email' => 'auditor@fechamento.local', 'password' => 'x', 'role' => UserRole::Auditor,
        ]);
        $this->sector = Sector::create([
            'establishment_id' => $this->establishment->id, 'name' => 'Geral', 'manager_id' => $this->manager->id,
        ]);

        $this->schedule = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM 40h', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '13:00', 'afternoon_end' => '17:00',
        ], $this->admin);
    }

    protected function hiredEmployee(): Employee
    {
        if ($this->employee === null) {
            $user = User::create([
                'name' => 'Servidor', 'email' => 'servidor@fechamento.local',
                'password' => 'x', 'role' => UserRole::Employee,
            ]);
            $this->employee = Employee::create([
                'user_id' => $user->id, 'sector_id' => $this->sector->id,
                'registration_number' => 'MAT-1', 'cpf' => '12345678901',
                'job_title' => 'Servidor', 'legal_regime' => LegalRegime::CLT,
            ]);
            app(AssignWorkScheduleAction::class)->execute(
                employee: $this->employee, schedule: $this->schedule,
                effectiveFrom: '2026-09-01', reason: 'Base', assignedBy: $this->admin,
            );
        }

        return $this->employee;
    }

    public function test_close_without_review_is_blocked_by_direct_call(): void
    {
        $this->hiredEmployee();

        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
            $this->fail('Fechamento sem conferência deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('sem conferência concluída', $e->getMessage());
        }
    }

    public function test_close_with_valid_ressalva_succeeds(): void
    {
        ReviewForClose::reviewAll([$this->hiredEmployee()], 2026, 10, $this->admin);

        $closed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);

        $this->assertEquals('closed', $closed->status);
        $this->assertNotNull($closed->snapshot_hash);
    }

    public function test_manager_employee_and_auditor_cannot_close(): void
    {
        ReviewForClose::reviewAll([$this->hiredEmployee()], 2026, 10, $this->admin);

        foreach ([$this->manager, $this->auditor] as $actor) {
            try {
                app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $actor);
                $this->fail('Fechamento por perfil sem permissão deveria ser bloqueado.');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('Administrador/RH', $e->getMessage());
            }
        }

        $employeeUser = $this->hiredEmployee()->user;
        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $employeeUser);
            $this->fail('Fechamento por servidor deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Administrador/RH', $e->getMessage());
        }
    }

    public function test_close_empty_competence_is_blocked(): void
    {
        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
            $this->fail('Fechamento sem servidores deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Nenhum servidor abrangido', $e->getMessage());
        }
    }

    public function test_second_close_attempt_fails_as_already_closed(): void
    {
        ReviewForClose::reviewAll([$this->hiredEmployee()], 2026, 10, $this->admin);
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);

        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
            $this->fail('Segundo fechamento deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já se encontra fechada e congelada', $e->getMessage());
        }
    }

    public function test_time_bank_screen_has_no_independent_close(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin.time-bank')
            ->assertSee('Conferir e Fechar na Conferência Mensal')
            ->assertDontSee('Confirmar Fechamento');
    }

    public function test_stale_review_blocks_close_until_revalidated(): void
    {
        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
            $this->hiredEmployee(), 2026, 10, $this->manager
        );
        MonthlyAttendanceReview::where('employee_id', $this->hiredEmployee()->id)->update([
            'reviewed_at' => Carbon::now()->subHour(),
        ]);

        $other = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM Nova', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '14:00', 'afternoon_end' => '18:00',
        ], $this->admin);
        app(AssignWorkScheduleAction::class)->execute(
            employee: $this->hiredEmployee(), schedule: $other,
            effectiveFrom: '2026-10-20', reason: 'Troca', assignedBy: $this->admin,
        );

        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
            $this->fail('Fechamento com conferência desatualizada deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('nova validação', $e->getMessage());
        }

        app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
            $this->hiredEmployee(), 2026, 10, $this->manager, 'Nova escala conferida e validada.'
        );

        $closed = app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
        $this->assertEquals('closed', $closed->status);
    }
}
