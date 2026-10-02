<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\AdjustTimeBankAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RecordTimeBankTransactionAction;
use App\Domain\PTRP\Actions\ReopenMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RequestTreatmentEventAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TimeBankTransaction;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedPeriodSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $workerUser;

    protected Employee $employee;

    protected Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $company = Company::create([
            'legal_name' => 'EMPRESA TESTE COMPLIANCE LTDA',
            'trade_name' => 'Empresa Compliance',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $this->establishment = Establishment::create([
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
            'name' => 'Tecnologia',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->admin = User::create([
            'name' => 'RH Admin',
            'email' => 'admin.rh@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->workerUser = User::create([
            'name' => 'Carlos Silva',
            'email' => 'carlos@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->workerUser->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-101',
            'cpf' => '11122233344',
            'job_title' => 'Analista Desenvolvedor',
        ]);
    }

    /**
     * Teste 21.0: Fechamento retroativo mensal calcula saldo com balanceUntil($periodEnd).
     * Exemplo: Janeiro +480 min (8h), Fevereiro +180 min (3h).
     * Fechar Janeiro em Fevereiro deve aplicar reset de estritamente -480 min,
     * mantendo os +180 min de Fevereiro 100% intactos!
     */
    public function test_retroactive_monthly_closing_resets_strictly_target_month_balance(): void
    {
        TimeBankPolicy::create([
            'name' => 'Banco Mensal',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => Carbon::parse('2026-01-01'),
            'created_by' => $this->admin->id,
        ]);

        $recordTxAction = app(RecordTimeBankTransactionAction::class);

        // Movimentação em Janeiro (+480 min)
        $recordTxAction->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 480,
            referenceDate: Carbon::parse('2026-01-20'),
        );

        // Movimentação em Fevereiro (+180 min)
        $recordTxAction->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 180,
            referenceDate: Carbon::parse('2026-02-10'),
        );

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        $this->assertEquals(660, $account->currentBalance());
        $this->assertEquals(480, $account->balanceUntil('2026-01-31'));

        // Fechar retroativamente a competência de Janeiro
        $closedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
            notes: 'Fechamento retroativo de Janeiro realizado em Fevereiro'
        );

        $this->assertEquals('closed', $closedPeriod->status);
        $this->assertEquals(1, $closedPeriod->snapshot_version);

        // Verifica que o reset compensatório gerado foi de rigorosamente -480 (e não -660)
        $resetTx = TimeBankTransaction::where('type', TimeBankTransactionType::MonthlyReset)->first();
        $this->assertNotNull($resetTx);
        $this->assertEquals(-480, $resetTx->minutes);
        $this->assertEquals('2026-01-31', $resetTx->reference_date->format('Y-m-d'));

        // O saldo final atual da conta deve ser exatamente 180 min (saldo de Fevereiro preservado!)
        $account->refresh();
        $this->assertEquals(180, $account->currentBalance());
    }

    /**
     * Teste 21.4: Fechamento deve ser impedido caso existam solicitações de tratamento pendentes.
     */
    public function test_closing_period_is_blocked_when_pending_treatment_requests_exist(): void
    {
        app(RequestTreatmentEventAction::class)->execute(
            employee: $this->employee,
            type: TreatmentEventType::ManualPunchAdded,
            effectiveAt: Carbon::parse('2026-01-15 14:00:00'),
            reasonText: 'Esqueci de registrar batida na volta do almoço',
            requestedBy: $this->workerUser,
            newValueJson: ['time' => '14:00', 'type' => 'in']
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('existem 1 solicitações de tratamento pendentes de aprovação pelo RH');

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );
    }

    /**
     * Teste 21.5: Alterações retroativas (ajuste manual e tratamentos) são estritamente bloqueadas após fechamento.
     */
    public function test_modifications_are_strictly_blocked_for_closed_periods(): void
    {
        // Fecha Janeiro normalmente
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );

        // 1. Tentar Ajuste Manual no banco de horas com data em Janeiro
        try {
            app(AdjustTimeBankAction::class)->execute(
                employee: $this->employee,
                type: TimeBankTransactionType::ManualCredit,
                minutes: 60,
                date: Carbon::parse('2026-01-20'),
                reason: 'Ajuste extraordinário retroativo',
                adminUser: $this->admin
            );
            $this->fail('Deveria ter lançado DomainException ao tentar ajuste manual em competência fechada');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Não é permitido realizar ajustes manuais de banco de horas em competência fechada (01/2026)', $e->getMessage());
        }

        // 2. Tentar Solicitar Tratamento de ponto em Janeiro
        try {
            app(RequestTreatmentEventAction::class)->execute(
                employee: $this->employee,
                type: TreatmentEventType::AbsenceJustified,
                effectiveAt: Carbon::parse('2026-01-22 08:00:00'),
                reasonText: 'Atestado médico entregue com atraso',
                requestedBy: $this->workerUser
            );
            $this->fail('Deveria ter lançado DomainException ao solicitar tratamento em competência fechada');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('A competência 01/2026 encontra-se fechada e congelada', $e->getMessage());
        }
    }

    /**
     * Teste 21.6: Reabertura formal exige justificativa, preserva snapshots anteriores e incrementa versão.
     */
    public function test_reopening_competence_requires_reason_preserves_history_and_increments_version(): void
    {
        // 1. Fechar Janeiro pela primeira vez
        $period = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
            notes: 'Primeiro fechamento'
        );

        $this->assertEquals(1, $period->snapshot_version);
        $firstHash = $period->snapshot_hash;
        $this->assertNotNull($firstHash);

        // Snapshots versão 1 gerados
        $this->assertEquals(1, $period->currentSnapshots()->count());

        // 2. Tentar reabrir sem justificativa suficiente (< 10 caracteres)
        try {
            app(ReopenMonthlyPeriodAction::class)->execute(
                year: 2026,
                month: 1,
                reopenedBy: $this->admin,
                reason: 'Curto'
            );
            $this->fail('Deveria ter lançado InvalidArgumentException por justificativa curta');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('no mínimo 10 caracteres', $e->getMessage());
        }

        // 3. Reabrir formalmente
        $reopenedPeriod = app(ReopenMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            reopenedBy: $this->admin,
            reason: 'Reabertura para inclusão de atestado médico com deferimento judicial'
        );

        $this->assertEquals('reopened', $reopenedPeriod->status);
        $this->assertEquals($this->admin->id, $reopenedPeriod->reopened_by);
        $this->assertEquals('Reabertura para inclusão de atestado médico com deferimento judicial', $reopenedPeriod->reopen_reason);

        // Snapshots anteriores (v1) continuam no banco!
        $this->assertDatabaseHas('closed_period_employee_snapshots', [
            'closed_period_id' => $period->id,
            'version' => 1,
        ]);

        // 4. Fechar novamente: deve gerar versão 2
        $reclosedPeriod = app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
            notes: 'Segundo fechamento após correções'
        );

        $this->assertEquals('closed', $reclosedPeriod->status);
        $this->assertEquals(2, $reclosedPeriod->snapshot_version);
        $this->assertNull($reclosedPeriod->reopened_by);

        // Histórico preservado: existem snapshots da versão 1 e da versão 2
        $this->assertDatabaseHas('closed_period_employee_snapshots', [
            'closed_period_id' => $period->id,
            'version' => 1,
        ]);
        $this->assertDatabaseHas('closed_period_employee_snapshots', [
            'closed_period_id' => $period->id,
            'version' => 2,
        ]);
    }
}
