<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\ApproveTreatmentEventAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Actions\ReviewMonthlyAttendanceAction;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\MonthlyAttendanceReviewService;
use App\Domain\WorkSchedule\Actions\AssignWorkScheduleAction;
use App\Domain\WorkSchedule\Actions\ConfigureWorkScheduleAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\LaborRuleApprovalStatus;
use App\Enums\LegalRegime;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\MonthlyAttendanceReview;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewFingerprintTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected Sector $sector;

    protected User $admin;

    protected User $manager;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'Fingerprint Co', 'trade_name' => 'Fingerprint', 'cnpj' => '12345678000195',
            'rep_p_software_name' => 'PontoFacil', 'rep_p_software_version' => '2.0.0',
        ]);
        $this->establishment = Establishment::create([
            'company_id' => $this->company->id, 'name' => 'Sede', 'identifier_type' => 'CNPJ',
            'identifier_number' => '12345678000195', 'state' => 'AL', 'city' => 'Maceió',
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@fingerprint.local', 'password' => 'x', 'role' => UserRole::Admin,
        ]);
        $this->manager = User::create([
            'name' => 'Gestor', 'email' => 'gestor@fingerprint.local', 'password' => 'x', 'role' => UserRole::Manager,
        ]);
        $this->sector = Sector::create([
            'establishment_id' => $this->establishment->id, 'name' => 'Geral', 'manager_id' => $this->manager->id,
        ]);

        $schedule = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM 40h', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '13:00', 'afternoon_end' => '17:00',
        ], $this->admin);

        $user = User::create([
            'name' => 'Servidor', 'email' => 'servidor@fingerprint.local',
            'password' => 'x', 'role' => UserRole::Employee,
        ]);
        $this->employee = Employee::create([
            'user_id' => $user->id, 'sector_id' => $this->sector->id,
            'registration_number' => 'MAT-1', 'cpf' => '12345678901',
            'job_title' => 'Servidor', 'legal_regime' => LegalRegime::CLT,
        ]);
        app(AssignWorkScheduleAction::class)->execute(
            employee: $this->employee, schedule: $schedule,
            effectiveFrom: '2026-09-01', reason: 'Base', assignedBy: $this->admin,
        );
    }

    protected function addPunch(string $dateTime, string $direction): void
    {
        $local = Carbon::parse($dateTime);
        PunchEvent::create([
            'id' => (string) Str::ulid(), 'establishment_id' => $this->establishment->id,
            'employee_id' => $this->employee->id, 'user_id' => $this->employee->user_id,
            'nsr' => rand(1000, 999999), 'direction' => $direction,
            'occurred_at_utc' => $local->copy()->utc(), 'occurred_at_local' => $local,
            'fiscal_hash' => hash('sha256', Str::random()), 'audit_chain_hash' => hash('sha256', Str::random()),
            'payload_hash' => hash('sha256', Str::random()), 'hash_type_7' => hash('sha256', Str::random()),
            'integrity_hash' => hash('sha256', Str::random()),
        ]);
    }

    protected function review(): MonthlyAttendanceReview
    {
        return app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
            $this->employee, 2026, 10, $this->manager
        );
    }

    protected function reviewWithRessalva(string $ressalva): MonthlyAttendanceReview
    {
        return app(ReviewMonthlyAttendanceAction::class)->reviewEmployee(
            $this->employee, 2026, 10, $this->manager, $ressalva
        );
    }

    protected function overviewItem(): array
    {
        $overview = app(MonthlyAttendanceReviewService::class)->getCompetenceOverview(2026, 10, $this->admin);

        return collect($overview['items'])->firstWhere('employee_id', $this->employee->id);
    }

    public function test_new_punch_after_review_invalidates(): void
    {
        $review = $this->review();
        $this->assertNotNull($review->fingerprint);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $review->fingerprint);
        $this->assertFalse($this->overviewItem()['stale']);

        $this->addPunch('2026-10-20 08:00:00', 'in');
        $this->addPunch('2026-10-20 12:00:00', 'out');

        $item = $this->overviewItem();
        $this->assertTrue($item['stale']);
        $this->assertContains(
            'Conferência desatualizada — houve alterações após a última análise.',
            $item['occurrences']
        );
    }

    public function test_approved_manual_punch_invalidates(): void
    {
        $this->review();

        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee, type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-10-06 17:00:00'),
            reasonText: 'Saída esquecida por atendimento urgente',
            requestedBy: $this->employee->user, newValueJson: ['direction' => 'out'],
        );
        app(ApproveTreatmentEventAction::class)->execute(event: $event, approvedBy: $this->admin);

        $this->assertTrue($this->overviewItem()['stale']);
    }

    public function test_disregarded_punch_invalidates(): void
    {
        $this->addPunch('2026-10-06 08:00:00', 'in');
        $this->addPunch('2026-10-06 12:00:00', 'out');
        $this->review();

        $punch = PunchEvent::where('employee_id', $this->employee->id)->orderBy('occurred_at_local')->first();
        $event = app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee, type: TreatmentEventType::PunchDisregarded,
            effectiveAt: Carbon::parse('2026-10-06 08:00:00'),
            reasonText: 'Marcação duplicada pelo coletor',
            requestedBy: $this->employee->user, referencePunchId: $punch->id,
        );
        app(ApproveTreatmentEventAction::class)->execute(event: $event, approvedBy: $this->admin);

        $this->assertTrue($this->overviewItem()['stale']);
    }

    public function test_schedule_change_invalidates(): void
    {
        $this->review();

        $other = app(ConfigureWorkScheduleAction::class)->createAdministrative([
            'name' => 'ADM Nova', 'days' => [1, 2, 3, 4, 5],
            'morning_start' => '08:00', 'morning_end' => '12:00',
            'afternoon_start' => '14:00', 'afternoon_end' => '18:00',
        ], $this->admin);
        app(AssignWorkScheduleAction::class)->execute(
            employee: $this->employee, schedule: $other,
            effectiveFrom: '2026-10-20', reason: 'Troca', assignedBy: $this->admin,
        );

        $this->assertTrue($this->overviewItem()['stale']);
    }

    public function test_shift_change_invalidates(): void
    {
        $shift = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'start_at_utc' => Carbon::parse('2026-10-06 11:00:00', 'UTC'),
            'end_at_utc' => Carbon::parse('2026-10-06 23:00:00', 'UTC'),
            'start_at_local' => Carbon::parse('2026-10-06 08:00:00'),
            'end_at_local' => Carbon::parse('2026-10-06 20:00:00'),
            'timezone' => 'America/Sao_Paulo',
            'break_minutes' => 60, 'expected_work_minutes' => 660,
        ]);

        $this->review();
        $this->assertFalse($this->overviewItem()['stale']);

        $shift->update(['expected_work_minutes' => 600]);

        $this->assertTrue($this->overviewItem()['stale']);
    }

    public function test_applicable_calendar_change_invalidates(): void
    {
        $this->review();
        $this->assertFalse($this->overviewItem()['stale']);

        CalendarEvent::create([
            'name' => 'Feriado Municipal', 'event_date' => '2026-10-12',
            'type' => CalendarEventType::Holiday, 'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected, 'all_day' => true, 'active' => true,
        ]);

        $this->assertTrue($this->overviewItem()['stale']);
    }

    public function test_effective_legal_rule_change_invalidates(): void
    {
        $this->review();
        $this->assertFalse($this->overviewItem()['stale']);

        $profile = LaborRuleProfile::create([
            'name' => 'CLT Especial', 'code' => 'clt_especial_teste',
            'legal_regime' => LegalRegime::CLT, 'jurisdiction' => 'federal',
            'legal_reference' => 'CLT teste', 'effective_from' => '2026-10-01',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'version' => 1, 'configuration' => [],
        ]);
        $this->employee->update(['labor_rule_profile_id' => $profile->id]);

        $this->assertTrue($this->overviewItem()['stale']);
    }

    public function test_irrelevant_changes_do_not_invalidate(): void
    {
        $this->review();

        // Marcação fora da competência.
        $this->addPunch('2026-11-03 08:00:00', 'in');
        // Evento de calendário fora da competência.
        CalendarEvent::create([
            'name' => 'Feriado Outro Mês', 'event_date' => '2026-11-02',
            'type' => CalendarEventType::Holiday, 'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected, 'all_day' => true, 'active' => true,
        ]);
        // Renomeações sem impacto na apuração.
        $this->employee->workSchedule->update(['name' => 'Nome Novo Sem Impacto']);
        $this->sector->update(['name' => 'Setor Renomeado']);

        $item = $this->overviewItem();
        $this->assertFalse($item['stale']);
        $this->assertFalse($item['has_unresolved_occurrences']);
    }

    public function test_batch_review_stores_fingerprints(): void
    {
        $action = app(ReviewMonthlyAttendanceAction::class);
        $result = $action->reviewBatch([$this->employee->id], 2026, 10, $this->manager);

        $this->assertEquals(1, $result['reviewed_count']);
        $this->assertNotNull($this->employee->monthlyAttendanceReviews()->first()->fingerprint);

        // Segunda rodada em lote encontra tudo válido, sem novas pendências.
        $second = $action->reviewBatch([$this->employee->id], 2026, 10, $this->manager);
        $this->assertEquals(1, $second['reviewed_count']);
        $this->assertEquals(0, $second['skipped_count']);
    }

    public function test_legacy_review_without_fingerprint_requires_revalidation(): void
    {
        $review = $this->review();
        $review->update(['fingerprint' => null]);

        $item = $this->overviewItem();
        $this->assertTrue($item['stale']);

        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
            $this->fail('Fechamento com conferência legada deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('nova validação', $e->getMessage());
        }

        $this->reviewWithRessalva('Reconferência após atualização dos insumos.');
        $this->assertFalse($this->overviewItem()['stale']);
    }

    public function test_closed_competence_does_not_recompute_fingerprint(): void
    {
        $this->addPunch('2026-10-06 08:00:00', 'in');
        $this->addPunch('2026-10-06 12:00:00', 'out');
        $this->review();
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);

        $this->addPunch('2026-10-20 08:00:00', 'in');

        $data = app(MonthlyAttendanceReviewService::class)
            ->resolveEmployeeReviewData($this->employee, 2026, 10);

        $this->assertTrue($data['is_closed']);
        $this->assertTrue($data['from_snapshot']);
        $this->assertArrayNotHasKey('stale', $data);
    }

    public function test_concurrent_close_attempts_serialize(): void
    {
        $this->review();
        app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);

        try {
            app(CloseMonthlyPeriodAction::class)->execute(2026, 10, $this->admin);
            $this->fail('Segundo fechamento deveria ser bloqueado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já se encontra fechada e congelada', $e->getMessage());
        }
    }
}
