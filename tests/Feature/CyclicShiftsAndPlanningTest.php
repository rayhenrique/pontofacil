<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\WorkSchedule\Actions\GenerateCyclicShiftsAction;
use App\Domain\WorkSchedule\Actions\SwapShiftsAction;
use App\Domain\WorkSchedule\Actions\ValidateShiftConflictsAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\LegalRegime;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Enums\WorkScheduleModality;
use App\Models\CalendarEvent;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CyclicShiftsAndPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $adminUser;

    protected Sector $sector;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->adminUser = User::create([
            'name' => 'Gestor Operacional',
            'email' => 'gestor.operacional@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->sector = Sector::create([
            'name' => 'Hospital Municipal de Urgência',
            'establishment_id' => $this->establishment->id,
        ]);

        $employeeUser = User::create([
            'name' => 'Dr. Paulo Plantonista',
            'email' => 'paulo.plantonista@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MED-1234',
            'cpf' => '12345678900',
            'job_title' => 'Médico Plantonista',
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);
    }

    public function test_fixed_weekly_schedule_shift_generation(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $action = app(GenerateCyclicShiftsAction::class);

        // Gera de segunda a domingo (2026-10-05 Segunda a 2026-10-11 Domingo)
        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-05',
            startDate: '2026-10-05',
            endDate: '2026-10-11',
            assignedBy: $this->adminUser,
            includeOffDays: false,
        );

        // Deve gerar 5 dias úteis (Segunda a Sexta)
        $this->assertCount(5, $shifts);

        $mondayShift = $shifts->first();
        $this->assertEquals('2026-10-05 08:00:00', $mondayShift->start_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-05 18:00:00', $mondayShift->end_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals(480, $mondayShift->expected_work_minutes);
        $this->assertEquals(ShiftType::Regular, $mondayShift->shift_type);
        $this->assertFalse($mondayShift->crosses_midnight);
    }

    public function test_six_by_one_cyclic_shift_generation(): void
    {
        $schedule = WorkSchedule::create([
            'name' => 'Escala 6×1 Operacional',
            'modality' => WorkScheduleModality::SixByOne,
            'cycle_days' => 7,
            'cycle_data' => [
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_minutes' => 60,
                'off_day_index' => 6, // 7º dia é folga
            ],
            'active' => true,
        ]);

        $action = app(GenerateCyclicShiftsAction::class);

        // Gera um ciclo completo de 7 dias
        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-01',
            startDate: '2026-10-01',
            endDate: '2026-10-07',
            includeOffDays: true,
        );

        $this->assertCount(7, $shifts);

        $workShifts = $shifts->where('is_day_off', false);
        $offShifts = $shifts->where('is_day_off', true);

        $this->assertCount(6, $workShifts);
        $this->assertCount(1, $offShifts);
        $this->assertEquals(ShiftType::OffDay, $offShifts->first()->shift_type);
    }

    public function test_twelve_by_thirty_six_day_shift(): void
    {
        $schedule = WorkSchedule::create([
            'name' => 'Escala 12×36 Diurna',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'cycle_days' => 2,
            'cycle_data' => [
                'start_time' => '07:00',
                'end_time' => '19:00',
                'break_minutes' => 60,
                'is_night_shift' => false,
            ],
            'active' => true,
        ]);

        $action = app(GenerateCyclicShiftsAction::class);

        // 4 dias = 2 plantões e 2 folgas
        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-10',
            startDate: '2026-10-10',
            endDate: '2026-10-13',
            includeOffDays: true,
        );

        $this->assertCount(4, $shifts);

        $firstShift = $shifts->where('is_day_off', false)->first();
        $this->assertEquals('2026-10-10 07:00:00', $firstShift->start_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-10 19:00:00', $firstShift->end_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals(12, $firstShift->durationInHours());
        $this->assertFalse($firstShift->crosses_midnight);
        $this->assertEquals(ShiftType::Shift12x36, $firstShift->shift_type);
    }

    public function test_twelve_by_thirty_six_night_shift_crossing_midnight(): void
    {
        $schedule = WorkSchedule::create([
            'name' => 'Escala 12×36 Noturna',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'cycle_days' => 2,
            'cycle_data' => [
                'start_time' => '19:00',
                'end_time' => '07:00',
                'break_minutes' => 60,
                'is_night_shift' => true,
            ],
            'active' => true,
        ]);

        $action = app(GenerateCyclicShiftsAction::class);

        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-10',
            startDate: '2026-10-10',
            endDate: '2026-10-10',
            includeOffDays: false,
        );

        $this->assertCount(1, $shifts);

        $nightShift = $shifts->first();
        // Início em 10/10 às 19h e término em 11/10 às 07h
        $this->assertEquals('2026-10-10 19:00:00', $nightShift->start_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-11 07:00:00', $nightShift->end_at_local->format('Y-m-d H:i:s'));
        $this->assertTrue($nightShift->crosses_midnight);
        $this->assertTrue($nightShift->is_night_shift);
        $this->assertEquals(12, $nightShift->durationInHours());
    }

    public function test_twenty_four_by_seventy_two_shift_with_authorized_regime(): void
    {
        $schedule = WorkSchedule::create([
            'name' => 'Plantão 24×72 Médico Socorrista',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'requires_legal_authorization' => true,
            'allowed_legal_regimes' => ['municipal_statutory'],
            'cycle_days' => 4,
            'cycle_data' => [
                'start_time' => '07:00',
                'end_time' => '07:00',
                'break_minutes' => 120,
            ],
            'active' => true,
        ]);

        $action = app(GenerateCyclicShiftsAction::class);

        // Gera 4 dias (1 plantão 24h e 3 dias de folga)
        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-10',
            startDate: '2026-10-10',
            endDate: '2026-10-13',
            includeOffDays: true,
        );

        $this->assertCount(4, $shifts);

        $plantao = $shifts->where('is_day_off', false)->first();
        $this->assertEquals('2026-10-10 07:00:00', $plantao->start_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-11 07:00:00', $plantao->end_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals(24, $plantao->durationInHours());
        $this->assertTrue($plantao->crosses_midnight);
        $this->assertTrue($plantao->is_night_shift);
        $this->assertEquals(ShiftType::Shift24x72, $plantao->shift_type);

        $folgas = $shifts->where('is_day_off', true);
        $this->assertCount(3, $folgas);
    }

    public function test_twenty_four_by_seventy_two_rejected_for_unauthorized_regime(): void
    {
        $cltEmployee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'sector_id' => $this->sector->id,
            'legal_regime' => LegalRegime::CLT,
        ]);

        $schedule = WorkSchedule::create([
            'name' => 'Plantão 24×72 Restrito',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'requires_legal_authorization' => true,
            'allowed_legal_regimes' => ['municipal_statutory'],
        ]);

        $this->expectException(ValidationException::class);
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $cltEmployee,
            schedule: $schedule,
            anchorDate: '2026-10-10',
            startDate: '2026-10-10',
            endDate: '2026-10-11',
        );
    }

    public function test_night_shift_crossing_month_boundary(): void
    {
        $schedule = WorkSchedule::create([
            'name' => '12×36 Noturna Fim de Mês',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'cycle_data' => ['start_time' => '19:00', 'end_time' => '07:00', 'is_night_shift' => true],
        ]);

        $action = app(GenerateCyclicShiftsAction::class);

        // Início em 31 de Outubro às 19:00 → término em 01 de Novembro às 07:00
        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-31',
            startDate: '2026-10-31',
            endDate: '2026-10-31',
            includeOffDays: false,
        );

        $shift = $shifts->first();
        $this->assertEquals('2026-10-31 19:00:00', $shift->start_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-11-01 07:00:00', $shift->end_at_local->format('Y-m-d H:i:s'));
        $this->assertTrue($shift->crosses_midnight);
    }

    public function test_night_shift_crossing_year_boundary(): void
    {
        $schedule = WorkSchedule::create([
            'name' => '12×36 Noturna Fim de Ano',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'cycle_data' => ['start_time' => '19:00', 'end_time' => '07:00', 'is_night_shift' => true],
        ]);

        $action = app(GenerateCyclicShiftsAction::class);

        // Início em 31 de Dezembro às 19:00 → término em 01 de Janeiro às 07:00
        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-12-31',
            startDate: '2026-12-31',
            endDate: '2026-12-31',
            includeOffDays: false,
        );

        $shift = $shifts->first();
        $this->assertEquals('2026-12-31 19:00:00', $shift->start_at_local->format('Y-m-d H:i:s'));
        $this->assertEquals('2027-01-01 07:00:00', $shift->end_at_local->format('Y-m-d H:i:s'));
        $this->assertTrue($shift->crosses_midnight);
    }

    public function test_intershift_minimum_rest_validation(): void
    {
        $schedule = WorkSchedule::createDefault40h();

        // Criar plantão 1: termina 2026-10-10 às 19:00
        $shift1 = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => '2026-10-10 07:00:00',
            'end_at_local' => '2026-10-10 19:00:00',
            'start_at_utc' => Carbon::parse('2026-10-10 07:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'end_at_utc' => Carbon::parse('2026-10-10 19:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
        ]);

        // Criar plantão 2 com intervalo insuficiente de apenas 12h (início 2026-10-11 07:00, enquanto 12x36 exige 36h)
        $shift2 = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => '2026-10-11 07:00:00',
            'end_at_local' => '2026-10-11 19:00:00',
            'start_at_utc' => Carbon::parse('2026-10-11 07:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'end_at_utc' => Carbon::parse('2026-10-11 19:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
        ]);

        $validator = app(ValidateShiftConflictsAction::class);
        $result = $validator->execute($this->employee);

        $this->assertNotEmpty($result['warnings']);
        $warning = collect($result['warnings'])->firstWhere('type', 'insufficient_rest');
        $this->assertNotNull($warning);
        $this->assertEquals(12, $warning['rest_hours']);
        $this->assertEquals(36, $warning['required_hours']);
    }

    public function test_swap_shift_between_employees_preserves_audit(): void
    {
        $schedule = WorkSchedule::createDefault40h();

        $originalShift = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => '2026-10-15 07:00:00',
            'end_at_local' => '2026-10-15 19:00:00',
            'start_at_utc' => Carbon::parse('2026-10-15 07:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'end_at_utc' => Carbon::parse('2026-10-15 19:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'shift_type' => ShiftType::Shift12x36,
            'status' => ShiftStatus::Scheduled,
        ]);

        $substituto = Employee::create([
            'user_id' => User::factory()->create(['name' => 'Dra. Roberta Substituta'])->id,
            'sector_id' => $this->sector->id,
            'registration_number' => 'MED-9999',
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);

        $action = app(SwapShiftsAction::class);
        $newShift = $action->execute(
            originalShift: $originalShift,
            replacementEmployee: $substituto,
            reason: 'Permuta por motivo de participação em congresso médico',
            assignedBy: $this->adminUser,
        );

        // Plantão original atualizado
        $originalShift->refresh();
        $this->assertEquals(ShiftStatus::Swapped, $originalShift->status);
        $this->assertEquals($substituto->id, $originalShift->swapped_with_employee_id);

        // Novo plantão criado para a substituta
        $this->assertEquals($substituto->id, $newShift->employee_id);
        $this->assertEquals(ShiftType::Swapped, $newShift->shift_type);
        $this->assertEquals(ShiftOrigin::Swap, $newShift->origin);
        $this->assertEquals($this->employee->id, $newShift->swapped_with_employee_id);
    }

    public function test_calendar_holiday_integration_with_shifts(): void
    {
        // Registrar feriado nacional no dia 2026-10-12 (Nossa Sra. Aparecida)
        CalendarEvent::create([
            'name' => 'Nossa Senhora Aparecida',
            'event_date' => '2026-10-12',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
        ]);

        $schedule = WorkSchedule::createDefault40h();
        $action = app(GenerateCyclicShiftsAction::class);

        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-12',
            startDate: '2026-10-12',
            endDate: '2026-10-12',
            includeOffDays: false,
        );

        $shift = $shifts->first();
        $this->assertNotNull($shift);
        // O feriado é integrado e documentado nas anotações
        $this->assertStringContainsString('Nossa Senhora Aparecida', $shift->notes);
    }

    public function test_idempotent_cyclic_generation_does_not_duplicate_shifts(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $action = app(GenerateCyclicShiftsAction::class);

        // Primeira execução
        $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-05',
            startDate: '2026-10-05',
            endDate: '2026-10-09',
        );

        $countFirst = ShiftAssignment::where('employee_id', $this->employee->id)->count();
        $this->assertEquals(5, $countFirst);

        // Segunda execução do mesmo período
        $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-05',
            startDate: '2026-10-05',
            endDate: '2026-10-09',
        );

        $countSecond = ShiftAssignment::where('employee_id', $this->employee->id)->count();
        $this->assertEquals(5, $countSecond); // Não duplicou!
    }

    public function test_overlapping_shifts_conflict_detection(): void
    {
        $schedule = WorkSchedule::createDefault40h();

        // Plantão 1: 08:00 às 16:00
        ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => '2026-10-10 08:00:00',
            'end_at_local' => '2026-10-10 16:00:00',
            'start_at_utc' => Carbon::parse('2026-10-10 08:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'end_at_utc' => Carbon::parse('2026-10-10 16:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'shift_type' => ShiftType::Regular,
            'status' => ShiftStatus::Scheduled,
        ]);

        // Plantão 2: 14:00 às 22:00 (sobrepõe das 14h às 16h)
        ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => '2026-10-10 14:00:00',
            'end_at_local' => '2026-10-10 22:00:00',
            'start_at_utc' => Carbon::parse('2026-10-10 14:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'end_at_utc' => Carbon::parse('2026-10-10 22:00:00', 'America/Sao_Paulo')->setTimezone('UTC'),
            'shift_type' => ShiftType::Regular,
            'status' => ShiftStatus::Scheduled,
        ]);

        $validator = app(ValidateShiftConflictsAction::class);
        $result = $validator->execute($this->employee);

        $this->assertTrue($result['has_critical_conflicts']);
        $overlap = collect($result['conflicts'])->firstWhere('type', 'overlap');
        $this->assertNotNull($overlap);
    }

    public function test_timezone_conversion_utc_and_local(): void
    {
        $schedule = WorkSchedule::createDefault40h();
        $action = app(GenerateCyclicShiftsAction::class);

        $shifts = $action->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-05',
            startDate: '2026-10-05',
            endDate: '2026-10-05',
            timezone: 'America/Sao_Paulo',
        );

        $shift = $shifts->first();
        $this->assertEquals('2026-10-05 08:00:00', $shift->start_at_local->format('Y-m-d H:i:s'));
        // Em America/Sao_Paulo (UTC-3), 08:00 equivale a 11:00 em UTC
        $this->assertEquals('2026-10-05 11:00:00', $shift->start_at_utc->format('Y-m-d H:i:s'));
    }

    public function test_cannot_modify_or_generate_shifts_in_closed_period(): void
    {
        // Registrar fechamento para Outubro/2026
        ClosedPeriod::create([
            'year' => 2026,
            'month' => 10,
            'status' => 'closed',
            'closed_by' => $this->adminUser->id,
            'closed_at' => now(),
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $this->expectException(ValidationException::class);
        app(GenerateCyclicShiftsAction::class)->execute(
            employee: $this->employee,
            schedule: $schedule,
            anchorDate: '2026-10-10',
            startDate: '2026-10-10',
            endDate: '2026-10-15',
        );
    }

    public function test_real_punch_events_remain_untouched_on_shift_changes(): void
    {
        // Registrar uma batida bruta física real
        $punch = PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'user_id' => $this->employee->user_id,
            'employee_id' => $this->employee->id,
            'nsr' => 1,
            'occurred_at_utc' => now()->subHours(4),
            'occurred_at_local' => now()->subHours(4),
            'direction' => 'E',
            'source' => 'web_portal',
            'fiscal_hash' => hash('sha256', '1'),
            'audit_chain_hash' => hash('sha256', 'chain1'),
            'payload_hash' => hash('sha256', 'payload1'),
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $shift = ShiftAssignment::create([
            'employee_id' => $this->employee->id,
            'work_schedule_id' => $schedule->id,
            'start_at_local' => now()->subHours(5),
            'end_at_local' => now()->addHours(7),
            'start_at_utc' => now()->subHours(5),
            'end_at_utc' => now()->addHours(7),
            'shift_type' => ShiftType::Regular,
            'status' => ShiftStatus::Scheduled,
        ]);

        // Cancelar o plantão planejado
        $shift->update([
            'status' => ShiftStatus::Cancelled,
            'reason' => 'Cancelamento de teste',
        ]);

        // A batida original permanece 100% inalterada
        $freshPunch = PunchEvent::findOrFail($punch->id);
        $this->assertEquals('E', $freshPunch->direction);
        $this->assertEquals(1, $freshPunch->nsr);
        $this->assertEquals($punch->sha256_hash, $freshPunch->sha256_hash);
    }
}
