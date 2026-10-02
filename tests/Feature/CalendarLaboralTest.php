<?php

namespace Tests\Feature;

use App\Domain\Calendar\Services\BrazilianHolidaysService;
use App\Domain\Calendar\Services\WorkCalendarService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\UserRole;
use App\Enums\WorkBehavior;
use App\Models\CalendarEvent;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarLaboralTest extends TestCase
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

        // Garantir que o estabelecimento tem UF e cidade para testes territoriais
        $this->establishment->update([
            'state' => 'AL',
            'city' => 'Maceió',
        ]);

        $this->user = User::create([
            'name' => 'Maria Calendário',
            'email' => 'maria_cal@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'work_schedule_id' => $this->schedule->id,
            'registration_number' => 'MAT-CAL-001',
            'cpf' => '11122233344',
        ]);
    }

    // ─── 20.18.20: Feriado sem batidas → absence = 0 ──────────────

    public function test_holiday_no_work_expected_without_punches_produces_zero_absence(): void
    {
        // Segunda-feira normal = 8h, mas é feriado nacional
        $monday = Carbon::parse('2026-10-05'); // Segunda

        CalendarEvent::create([
            'name' => 'Feriado Teste Nacional',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
            'legal_reference' => 'Lei Federal nº 000/Teste',
        ]);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertEquals(0, $calculated->scheduledMinutes, 'Feriado deve zerar scheduled');
        $this->assertEquals(0, $calculated->absenceMinutes, 'Feriado sem batida NÃO deve gerar falta');
        $this->assertEquals(0, $calculated->workedMinutes);
        $this->assertEquals(0, $calculated->holidayMinutes);
        $this->assertNotEmpty($calculated->treatmentNotes, 'Deve anotar o feriado nas notas');
        $this->assertNotEmpty($calculated->calendarSnapshot, 'Deve incluir calendar snapshot');
    }

    // ─── 20.18.20: Trabalho em feriado → holiday_minutes ──────────

    public function test_work_on_holiday_produces_holiday_minutes_not_overtime(): void
    {
        $monday = Carbon::parse('2026-10-05');

        CalendarEvent::create([
            'name' => 'Feriado com Trabalho',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
        ]);

        // Registrar batidas: 08:00-12:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00'));
        $punchAction = app(RecordPunchEventAction::class);
        $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow(null);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertEquals(0, $calculated->scheduledMinutes);
        $this->assertEquals(240, $calculated->workedMinutes);
        $this->assertEquals(240, $calculated->holidayMinutes, 'Trabalho em feriado deve ser holiday_minutes');
        $this->assertEquals(0, $calculated->overtimeMinutes, 'NÃO deve classificar como hora extra automaticamente');
        $this->assertEquals(0, $calculated->bankCreditMinutes, 'NÃO deve creditar banco automaticamente');
    }

    // ─── 20.18.20: Ponto facultativo com NORMAL_WORKDAY ───────────

    public function test_optional_day_normal_workday_without_punches_generates_absence(): void
    {
        $monday = Carbon::parse('2026-10-05');

        CalendarEvent::create([
            'name' => 'Ponto Facultativo Teste',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::OptionalDay,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NormalWorkday,
            'all_day' => true,
        ]);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        // Ponto facultativo com expediente normal: ausência deve seguir a jornada
        $this->assertEquals(480, $calculated->scheduledMinutes);
        $this->assertEquals(480, $calculated->absenceMinutes, 'Ponto fac. com expediente normal gera falta se não trabalhar');
    }

    // ─── 20.18.20: Ponto facultativo OPTIONAL_NO_WORK ─────────────

    public function test_optional_day_no_work_without_punches_produces_zero_absence(): void
    {
        $monday = Carbon::parse('2026-10-05');

        CalendarEvent::create([
            'name' => 'Carnaval (Admin decidiu sem expediente)',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::OptionalDay,
            'scope' => CalendarEventScope::Establishment,
            'establishment_id' => $this->establishment->id,
            'work_behavior' => WorkBehavior::OptionalNoWork,
            'all_day' => true,
        ]);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertEquals(0, $calculated->scheduledMinutes, 'Ponto fac. sem expediente deve zerar scheduled');
        $this->assertEquals(0, $calculated->absenceMinutes, 'Ponto fac. sem expediente NÃO deve gerar falta');
        $this->assertFalse($calculated->requiresCompensation);
    }

    // ─── 20.18.20: Ponto facultativo com compensação ──────────────

    public function test_optional_day_with_compensation_marks_requires_compensation(): void
    {
        $monday = Carbon::parse('2026-10-05');

        CalendarEvent::create([
            'name' => 'Recesso com compensação',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::OptionalDay,
            'scope' => CalendarEventScope::Establishment,
            'establishment_id' => $this->establishment->id,
            'work_behavior' => WorkBehavior::OptionalWithCompensation,
            'all_day' => true,
            'requires_compensation' => true,
        ]);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertEquals(0, $calculated->scheduledMinutes);
        $this->assertEquals(0, $calculated->absenceMinutes);
        $this->assertTrue($calculated->requiresCompensation, 'Deve sinalizar obrigação de compensação');
    }

    // ─── 20.18.20: Ponto facultativo parcial (meio período) ───────

    public function test_partial_optional_day_reduces_scheduled_minutes(): void
    {
        $monday = Carbon::parse('2026-10-05');

        // Escala normal 08:00–12:00 e 13:00–17:00 (total 480 minutos)
        $schedule = WorkSchedule::create([
            'name' => 'Escala 08-12 / 13-17',
            'tolerance_minutes' => 5,
            'daily_tolerance_minutes' => 10,
            'schedule_data' => [
                1 => [
                    'day_name' => 'Segunda-feira',
                    'is_work_day' => true,
                    'is_day_off' => false,
                    'periods' => [
                        ['start' => '08:00', 'end' => '12:00'],
                        ['start' => '13:00', 'end' => '17:00'],
                    ],
                    'break_minutes' => 60,
                    'expected_minutes' => 480,
                ],
            ],
            'active' => true,
        ]);

        // 20.18.6: Quarta de Cinzas / Meio Período: facultativo de 00:00 até 14:00
        // Expediente normal restante: 14:00–17:00 -> 180 minutos
        CalendarEvent::create([
            'name' => 'Quarta-feira de Cinzas (até 14h)',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::OptionalDay,
            'scope' => CalendarEventScope::Establishment,
            'establishment_id' => $this->establishment->id,
            'work_behavior' => WorkBehavior::OptionalNoWork,
            'all_day' => false,
            'starts_at' => '00:00',
            'ends_at' => '14:00',
        ]);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $schedule);

        $this->assertEquals(180, $calculated->scheduledMinutes, 'Ponto facultativo parcial até 14:00 deve deixar expediente normal de 14:00 às 17:00 (180 min)');
    }

    // ─── 20.18.20: Evento municipal afeta apenas o estabelecimento da cidade ────

    public function test_municipal_event_applies_only_to_matching_city_establishment(): void
    {
        $monday = Carbon::parse('2026-10-05');

        // Feriado municipal de Maceió
        CalendarEvent::create([
            'name' => 'Emancipação de Maceió',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::Municipal,
            'state' => 'AL',
            'city' => 'Maceió',
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
            'legal_reference' => 'Lei Municipal nº ...',
        ]);

        // Estabelecimento de Maceió/AL → deve ser afetado
        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);
        $this->assertEquals(0, $calculated->scheduledMinutes, 'Feriado municipal deve afetar Maceió');
        $this->assertEquals(0, $calculated->absenceMinutes);

        // Criar outro estabelecimento em Recife/PE → NÃO deve ser afetado
        $recife = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Filial Recife',
            'code' => 'REC',
            'state' => 'PE',
            'city' => 'Recife',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '99999999000199',
        ]);

        $calendarService = app(WorkCalendarService::class);
        $resolvedForRecife = $calendarService->resolveDay($monday, $recife, 480);

        $this->assertFalse($resolvedForRecife->isHoliday, 'Feriado de Maceió NÃO deve afetar Recife');
        $this->assertEquals(480, $resolvedForRecife->expectedMinutes);
    }

    // ─── 20.18.20: Evento estadual afeta apenas UF correspondente ─

    public function test_state_event_applies_only_to_matching_state_establishment(): void
    {
        $monday = Carbon::parse('2026-10-05');

        CalendarEvent::create([
            'name' => 'Emancipação Política de Alagoas',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::State,
            'state' => 'AL',
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
        ]);

        // Estabelecimento de AL → deve ser afetado
        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);
        $this->assertEquals(0, $calculated->scheduledMinutes, 'Feriado estadual de AL deve afetar estabelecimento de AL');

        // Estabelecimento de SP → NÃO deve ser afetado
        $sp = Establishment::create([
            'company_id' => $this->company->id,
            'name' => 'Filial São Paulo',
            'code' => 'SP',
            'state' => 'SP',
            'city' => 'São Paulo',
            'identifier_type' => 'CNPJ',
            'identifier_number' => '88888888000188',
        ]);

        $calendarService = app(WorkCalendarService::class);
        $resolvedForSP = $calendarService->resolveDay($monday, $sp, 480);

        $this->assertFalse($resolvedForSP->isHoliday, 'Feriado de AL NÃO deve afetar SP');
        $this->assertEquals(480, $resolvedForSP->expectedMinutes);
    }

    // ─── 20.18.15: Seed de feriados nacionais brasileiros ─────────

    public function test_brazilian_holidays_service_imports_national_holidays_for_year(): void
    {
        $service = new BrazilianHolidaysService;
        $count = $service->importNationalHolidays(2026);

        // 9 fixos + 1 Sexta-feira Santa = 10 feriados nacionais
        $this->assertEquals(10, $count);
        $this->assertEquals(10, CalendarEvent::where('type', CalendarEventType::Holiday)->count());

        // Verificar Sexta-feira Santa 2026 (Páscoa = 05/04/2026, Sexta = 03/04/2026)
        $goodFriday = CalendarEvent::where('name', 'Sexta-feira Santa (Paixão de Cristo)')->first();
        $this->assertNotNull($goodFriday);
        $this->assertEquals('2026-04-03', $goodFriday->event_date->format('Y-m-d'));
        $this->assertEquals(CalendarEventScope::National, $goodFriday->scope);
        $this->assertEquals(WorkBehavior::NoWorkExpected, $goodFriday->work_behavior);

        // Verificar que tem referência legal
        $this->assertNotEmpty($goodFriday->legal_reference);
    }

    public function test_brazilian_holidays_service_does_not_import_optional_days(): void
    {
        $service = new BrazilianHolidaysService;
        $service->importNationalHolidays(2026);

        // Nenhum ponto facultativo deve ser importado automaticamente
        $optionalCount = CalendarEvent::where('type', CalendarEventType::OptionalDay)->count();
        $this->assertEquals(0, $optionalCount, 'Pontos facultativos NÃO devem ser importados automaticamente');
    }

    public function test_brazilian_holidays_service_provides_suggested_optional_days(): void
    {
        $service = new BrazilianHolidaysService;
        $suggestions = $service->getSuggestedOptionalDaysForYear(2026);

        $this->assertNotEmpty($suggestions, 'Deve fornecer sugestões de pontos facultativos');
        $this->assertGreaterThanOrEqual(5, count($suggestions));

        // Verificar que Carnaval está nas sugestões
        $carnaval = collect($suggestions)->firstWhere('name', 'Carnaval (Terça-feira)');
        $this->assertNotNull($carnaval);
        $this->assertArrayHasKey('note', $carnaval);
    }

    // ─── 20.18.2: Recesso administrativo ──────────────────────────

    public function test_institutional_closure_suspends_work(): void
    {
        $monday = Carbon::parse('2026-10-05');

        CalendarEvent::create([
            'name' => 'Recesso Administrativo de Fim de Ano',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::InstitutionalClosure,
            'scope' => CalendarEventScope::Establishment,
            'establishment_id' => $this->establishment->id,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
        ]);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertEquals(0, $calculated->scheduledMinutes);
        $this->assertEquals(0, $calculated->absenceMinutes);
    }

    // ─── calendar_snapshot preservado no CalculatedJourney ────────

    public function test_calculated_journey_includes_calendar_snapshot(): void
    {
        $monday = Carbon::parse('2026-10-05');

        CalendarEvent::create([
            'name' => 'Feriado Snapshot Test',
            'event_date' => $monday->format('Y-m-d'),
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
            'legal_reference' => 'Lei Teste',
        ]);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);
        $array = $calculated->toArray();

        $this->assertArrayHasKey('calendar_snapshot', $array);
        $this->assertNotEmpty($array['calendar_snapshot']);
        $this->assertEquals('holiday', $array['calendar_snapshot'][0]['type']);
        $this->assertEquals('national', $array['calendar_snapshot'][0]['scope']);
        $this->assertEquals('no_work_expected', $array['calendar_snapshot'][0]['work_behavior']);
    }

    // ─── Dia sem evento de calendário funciona normalmente ────────

    public function test_normal_workday_without_calendar_events_works_as_before(): void
    {
        $monday = Carbon::parse('2026-10-05');

        // Registrar jornada normal sem eventos de calendário
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00'));
        $punchAction = app(RecordPunchEventAction::class);
        $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-10-05 14:00:00'));
        $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-10-05 18:00:00'));
        $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow(null);

        $calculated = app(CalculateDailyJourneyAction::class)->execute($this->employee, $monday, $this->schedule);

        $this->assertEquals(480, $calculated->scheduledMinutes);
        $this->assertEquals(480, $calculated->workedMinutes);
        $this->assertEquals(0, $calculated->absenceMinutes);
        $this->assertEquals(0, $calculated->holidayMinutes);
        $this->assertEmpty($calculated->calendarSnapshot);
    }

    // ─── 20.18.20: Snapshot imutabilidade: após fechar competência, alterar CalendarEvent não altera ClosedPeriod nem AEJ ───

    public function test_altering_calendar_event_after_monthly_closing_does_not_affect_closed_period_or_aej(): void
    {
        $admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_cal@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        // 1. Cadastrar feriado em Janeiro de 2026 (01/01 Confraternização Universal)
        $holiday = CalendarEvent::create([
            'name' => 'Confraternização Universal',
            'event_date' => '2026-01-01',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
        ]);

        // Registrar batidas normais em 05/01/2026
        $punchAction = app(RecordPunchEventAction::class);
        Carbon::setTestNow(Carbon::parse('2026-01-05 08:00:00'));
        $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);
        Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00'));
        $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);
        Carbon::setTestNow(Carbon::parse('2026-01-05 13:00:00'));
        $punchAction->execute(user: $this->user, direction: 'in', establishment: $this->establishment);
        Carbon::setTestNow(Carbon::parse('2026-01-05 17:00:00'));
        $punchAction->execute(user: $this->user, direction: 'out', establishment: $this->establishment);
        Carbon::setTestNow(null);

        // 2. Fechar a competência 01/2026
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $admin
        );

        $initialHash = $closedPeriod->snapshot_hash;
        $this->assertNotNull($initialHash);

        // 3. Gerar AEJ oficial a partir do fechamento
        $generator = app(AejGenerator_2026_07_31::class);
        $genTime = Carbon::parse('2026-02-01 10:00:00');
        $initialAej = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: $genTime,
            forcePreview: false
        );

        // 4. Alterar o CalendarEvent original e adicionar novo evento retroativo no calendário
        $holiday->update([
            'work_behavior' => WorkBehavior::NormalWorkday,
            'name' => 'Feriado Modificado Retroativamente',
        ]);

        CalendarEvent::create([
            'name' => 'Falso Feriado Criado Após o Fechamento',
            'event_date' => '2026-01-05',
            'type' => CalendarEventType::Holiday,
            'scope' => CalendarEventScope::National,
            'work_behavior' => WorkBehavior::NoWorkExpected,
            'all_day' => true,
        ]);

        // 5. Verificar que o ClosedPeriod mantém o snapshot e hash intactos
        $reloadedPeriod = ClosedPeriod::findForPeriod(2026, 1);
        $this->assertEquals($initialHash, $reloadedPeriod->snapshot_hash);

        // Verificar que o snapshot do funcionário gravou o calendário original
        $snapshot = $reloadedPeriod->currentSnapshots()->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals('Confraternização Universal', $snapshot->calendar_snapshot[0]['name']);
        $this->assertEquals('no_work_expected', $snapshot->calendar_snapshot[0]['work_behavior']);

        // 6. Gerar novamente o AEJ: o conteúdo deve ser 100% idêntico e determinístico
        $subsequentAej = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: $genTime,
            forcePreview: false
        );

        $this->assertEquals($initialAej->content, $subsequentAej->content, 'AEJ pós-fechamento não pode ser alterado por modificações futuras no calendário');
        $this->assertEquals($initialAej->snapshotHash, $subsequentAej->snapshotHash);
    }

    public function test_calendar_component_can_open_suggestions_and_import_selected(): void
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin_sug@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        Livewire::actingAs($admin)
            ->test('admin.calendar')
            ->call('openSuggestionsModal')
            ->assertSet('showSuggestionsModal', true)
            ->assertCount('suggestions', 7)
            ->call('toggleSelectAllSuggestions')
            ->assertCount('selectedSuggestions', 7)
            ->call('toggleSelectAllSuggestions')
            ->assertCount('selectedSuggestions', 0)
            ->set('selectedSuggestions', ['0', '1', '3'])
            ->call('importSelectedSuggestions')
            ->assertSet('showSuggestionsModal', false)
            ->assertSet('selectedSuggestions', [])
            ->assertDispatched('app-modal-alert');

        $this->assertEquals(3, CalendarEvent::where('type', CalendarEventType::OptionalDay)->count());
    }
}
