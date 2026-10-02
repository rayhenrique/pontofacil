<?php

namespace Tests\Feature;

use App\Domain\PTRP\Actions\AdjustTimeBankAction;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Actions\RecordTimeBankTransactionAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TimeBankTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeBankLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $employeeUser;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin RH',
            'email' => 'admin_tb@test.com',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->employeeUser = User::create([
            'name' => 'Carlos Trabalhador',
            'email' => 'carlos@test.com',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'cpf' => '12345678901',
            'registration_number' => 'MAT-001',
        ]);
    }

    public function test_ledger_balance_is_always_sum_of_transactions(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 120,
            referenceDate: Carbon::parse('2026-01-10'),
            description: 'Hora extra aprovada',
        );

        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::CompensationDebit,
            minutes: -60,
            referenceDate: Carbon::parse('2026-01-15'),
            description: 'Compensação de saída antecipada',
        );

        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 30,
            referenceDate: Carbon::parse('2026-01-20'),
            description: 'Hora extra plantão',
        );

        $account->refresh();
        $this->assertEquals(90, $account->currentBalance());
    }

    public function test_carry_over_policy_preserves_balance_to_next_month_without_synthetic_resets(): void
    {
        // Política de Acúmulo
        TimeBankPolicy::create([
            'name' => 'Banco de Horas Acordo 2026',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::CarryOver,
            'valid_from' => Carbon::parse('2026-01-01'),
            'created_by' => $this->admin->id,
        ]);

        // Janeiro com +08:00 (+480 min)
        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 480,
            referenceDate: Carbon::parse('2026-01-20'),
        );

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        $this->assertEquals(480, $account->currentBalance());

        // Fechar competência de Janeiro
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
            notes: 'Fechamento regular de Janeiro'
        );

        // Nenhuma transação artificial de zeramento criada
        $this->assertDatabaseMissing('time_bank_transactions', [
            'type' => TimeBankTransactionType::MonthlyReset->value,
        ]);

        // Fevereiro começa com o mesmo saldo de 480 minutos (+08:00)
        $account->refresh();
        $this->assertEquals(480, $account->currentBalance());
    }

    public function test_monthly_reset_policy_creates_exact_compensatory_transaction_and_preserves_history(): void
    {
        // Política de Zeramento Mensal
        TimeBankPolicy::create([
            'name' => 'Banco com Zeramento Mensal',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => Carbon::parse('2026-01-01'),
            'created_by' => $this->admin->id,
        ]);

        // Janeiro com +480 min (+08:00)
        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 450,
            referenceDate: Carbon::parse('2026-01-10'),
        );

        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 30,
            referenceDate: Carbon::parse('2026-01-20'),
        );

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        $this->assertEquals(480, $account->currentBalance());
        $initialTxCount = TimeBankTransaction::count();

        // Fechar competência de Janeiro
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
        );

        // Transação compensatória gerada com minutos = -480
        $this->assertDatabaseHas('time_bank_transactions', [
            'type' => TimeBankTransactionType::MonthlyReset->value,
            'minutes' => -480,
        ]);

        // Auditoria: histórico anterior permanece 100% íntegro (mais 1 transação adicionada)
        $this->assertEquals($initialTxCount + 1, TimeBankTransaction::count());

        // Saldo resultante após fechamento é rigorosamente 0
        $account->refresh();
        $this->assertEquals(0, $account->currentBalance());
    }

    public function test_monthly_reset_handles_negative_balance_correctly(): void
    {
        TimeBankPolicy::create([
            'name' => 'Banco Mensal',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => Carbon::parse('2026-01-01'),
            'created_by' => $this->admin->id,
        ]);

        // Saldo devedor: -150 min (-02:30)
        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::CompensationDebit,
            minutes: -150,
            referenceDate: Carbon::parse('2026-01-15'),
        );

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        $this->assertEquals(-150, $account->currentBalance());

        // Fechar competência
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin,
        );

        // Gera transação compensatória positiva de +150
        $this->assertDatabaseHas('time_bank_transactions', [
            'type' => TimeBankTransactionType::MonthlyReset->value,
            'minutes' => 150,
        ]);

        $account->refresh();
        $this->assertEquals(0, $account->currentBalance());
    }

    public function test_system_does_not_reset_balance_automatically_on_calendar_month_change(): void
    {
        TimeBankPolicy::create([
            'name' => 'Banco Mensal',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => Carbon::parse('2026-01-01'),
        ]);

        app(RecordTimeBankTransactionAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::OvertimeCredit,
            minutes: 120,
            referenceDate: Carbon::parse('2026-01-31'),
        );

        // Simulando que o relógio passou para Fevereiro sem fechamento formal
        Carbon::setTestNow(Carbon::parse('2026-02-01 00:01:00'));

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Saldo NÃO foi zerado sozinho
        $this->assertEquals(120, $account->currentBalance());
        $this->assertDatabaseMissing('time_bank_transactions', [
            'type' => TimeBankTransactionType::MonthlyReset->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_can_perform_manual_adjustment_with_mandatory_reason(): void
    {
        $action = app(AdjustTimeBankAction::class);

        // Crédito manual
        $txCredit = $action->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::ManualCredit,
            minutes: 90,
            date: Carbon::parse('2026-02-10'),
            reason: 'Acordo individual de banco de horas por serviço extraordinário',
            adminUser: $this->admin,
        );

        $this->assertEquals(90, $txCredit->minutes);
        $this->assertEquals($this->admin->id, $txCredit->created_by);

        // Débito manual
        $txDebit = $action->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::ManualDebit,
            minutes: 45,
            date: Carbon::parse('2026-02-12'),
            reason: 'Compensação acordada para folga do colaborador',
            adminUser: $this->admin,
        );

        $this->assertEquals(-45, $txDebit->minutes);

        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        $this->assertEquals(45, $account->currentBalance());
    }

    public function test_manual_adjustment_fails_without_justification(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AdjustTimeBankAction::class)->execute(
            employee: $this->employee,
            type: TimeBankTransactionType::ManualCredit,
            minutes: 60,
            date: Carbon::parse('2026-02-10'),
            reason: '   ',
            adminUser: $this->admin,
        );
    }

    public function test_versioned_policy_preserves_historical_rules_for_past_competences(): void
    {
        // Jan - Jun: CARRY_OVER
        $policyJan = TimeBankPolicy::create([
            'name' => 'Política 1º Semestre',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::CarryOver,
            'valid_from' => Carbon::parse('2026-01-01'),
            'valid_until' => Carbon::parse('2026-06-30'),
            'created_by' => $this->admin->id,
        ]);

        // Jul em diante: MONTHLY_RESET
        $policyJul = TimeBankPolicy::create([
            'name' => 'Política 2º Semestre',
            'enabled' => true,
            'closing_mode' => TimeBankClosingMode::MonthlyReset,
            'valid_from' => Carbon::parse('2026-07-01'),
            'valid_until' => null,
            'created_by' => $this->admin->id,
        ]);

        // Consulta de Fevereiro deve retornar CARRY_OVER
        $febPolicy = TimeBankPolicy::forDate(Carbon::parse('2026-02-15'));
        $this->assertEquals(TimeBankClosingMode::CarryOver, $febPolicy->closing_mode);
        $this->assertEquals($policyJan->id, $febPolicy->id);

        // Consulta de Agosto deve retornar MONTHLY_RESET
        $augPolicy = TimeBankPolicy::forDate(Carbon::parse('2026-08-15'));
        $this->assertEquals(TimeBankClosingMode::MonthlyReset, $augPolicy->closing_mode);
        $this->assertEquals($policyJul->id, $augPolicy->id);
    }

    public function test_cannot_close_already_closed_period(): void
    {
        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 3,
            closedBy: $this->admin,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já se encontra fechada e congelada');

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 3,
            closedBy: $this->admin,
        );
    }
}
