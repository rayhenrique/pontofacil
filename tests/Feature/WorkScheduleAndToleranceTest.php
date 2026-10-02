<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkScheduleAndToleranceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $user;

    protected Employee $employee;

    protected WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->user = User::create([
            'name' => 'Mariana Silva',
            'email' => 'mariana@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-200',
            'cpf' => '98765432100',
        ]);
    }

    public function test_work_schedule_identifies_workdays_and_expected_minutes(): void
    {
        // Segunda-feira (dia útil, 480 min = 8h)
        $monday = Carbon::parse('2026-10-05'); // Segunda
        $this->assertTrue($this->schedule->isWorkDay($monday->dayOfWeek));
        $this->assertFalse($this->schedule->isDayOff($monday->dayOfWeek));
        $this->assertEquals(480, $this->schedule->expectedMinutesForDate($monday));

        // Domingo (folga semanal)
        $sunday = Carbon::parse('2026-10-04'); // Domingo
        $this->assertFalse($this->schedule->isWorkDay($sunday->dayOfWeek));
        $this->assertTrue($this->schedule->isDayOff($sunday->dayOfWeek));
        $this->assertEquals(0, $this->schedule->expectedMinutesForDate($sunday));
    }

    public function test_tolerance_within_5_minutes_is_not_counted_as_late_or_overtime(): void
    {
        // Simular batidas na Segunda-feira:
        // Entrada às 08:03 (3 minutos após o esperado - dentro da tolerância de 5 min)
        // Saída almoço às 12:00
        // Volta almoço às 14:00
        // Saída final às 18:02 (2 minutos a mais - dentro da tolerância)
        $monday = Carbon::parse('2026-10-05');
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:03:00'));

        $punchAction = app(RecordPunchEventAction::class);

        // 1ª Batida: 08:03
        $punch1 = $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);

        // 2ª Batida: 12:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $punch2 = $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);

        // 3ª Batida: 14:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 14:00:00'));
        $punch3 = $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);

        // 4ª Batida: 18:02
        Carbon::setTestNow(Carbon::parse('2026-10-05 18:02:00'));
        $punch4 = $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow();

        // 1. O fato bruto em punch_events permanece inalterado: 08:03
        $this->assertEquals('08:03', $punch1->occurred_at_local->format('H:i'));

        // 2. Apuração da jornada pelo motor PTRP
        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertEquals(480, $journey->scheduledMinutes);
        // Trabalhado: (12:00 - 08:03 = 237 min) + (18:02 - 14:00 = 242 min) = 479 min
        // Diferença em relação a 480 min: -1 min (dentro da tolerância de 5 min)
        $this->assertEquals(479, $journey->workedMinutes);
        $this->assertEquals(0, $journey->lateMinutes);
        $this->assertEquals(0, $journey->overtimeMinutes);
        $this->assertEquals(480, $journey->ordinaryMinutes);
        $this->assertFalse($journey->isIncomplete);
    }

    public function test_variation_exceeding_tolerance_is_counted_in_full(): void
    {
        $monday = Carbon::parse('2026-10-05');
        $punchAction = app(RecordPunchEventAction::class);

        // Entrada às 08:15 (15 minutos de atraso - excede limite de 5 minutos)
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:15:00'));
        $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);

        // Saída às 12:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);

        // Volta às 14:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 14:00:00'));
        $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);

        // Saída às 18:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 18:00:00'));
        $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow();

        $journey = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        // Trabalhado: 225 min + 240 min = 465 min (déficit de 15 min)
        $this->assertEquals(465, $journey->workedMinutes);
        $this->assertEquals(15, $journey->lateMinutes);
        $this->assertEquals(15, $journey->bankDebitMinutes);
    }

    public function test_punch_events_are_strictly_immutable_and_not_altered_by_journey_calculation(): void
    {
        $monday = Carbon::parse('2026-10-05');
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:57:00'));

        $punch = app(RecordPunchEventAction::class)->execute(user: $this->user, direction: 'in', establishment: $this->establishment);
        Carbon::setTestNow();

        $originalHash = $punch->payload_hash;
        $originalTime = $punch->occurred_at_local->format('H:i');

        // Executar apuração
        app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $punch->refresh();
        $this->assertEquals('07:57', $punch->occurred_at_local->format('H:i'));
        $this->assertEquals($originalHash, $punch->payload_hash);
    }
}
