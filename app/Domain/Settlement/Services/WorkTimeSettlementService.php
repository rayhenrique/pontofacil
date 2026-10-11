<?php

namespace App\Domain\Settlement\Services;

use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Policies\LaborPolicy;
use App\Domain\Settlement\DTOs\WorkTimeApuracaoResult;
use App\Domain\Settlement\DTOs\WorkTimeClassificacaoResult;
use App\Domain\Settlement\DTOs\WorkTimeDestinacaoResult;
use App\Domain\Settlement\DTOs\WorkTimeSettlementSummary;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Enums\LegalRegime;
use App\Models\Employee;
use App\Models\LaborRuleProfile;
use App\Models\TimeBankPolicy;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class WorkTimeSettlementService
{
    /**
     * Resolve a política de compensação e destinação aplicável para o colaborador na data.
     * Segue a precedência determinística:
     * 1. Política individual do Vínculo / Colaborador (employee_id)
     * 2. Política do Estabelecimento (establishment_id)
     * 3. Política Geral da Empresa / Instalação (company_id ou global)
     * 4. Fallback retrocompatível com TimeBankPolicy legada
     */
    public function resolvePolicy(Employee $employee, CarbonInterface $date): WorkTimeSettlementPolicy
    {
        $targetDate = $date->format('Y-m-d');
        $regime = $employee->legal_regime;
        $establishmentId = $employee->sector?->establishment_id;
        $companyId = $employee->sector?->establishment?->company_id;

        // 1. Precedência Nível 1: Política específica do Vínculo / Colaborador
        $individualPolicy = WorkTimeSettlementPolicy::where('employee_id', $employee->id)
            ->activeAt($targetDate)
            ->approved()
            ->latest('id')
            ->first();

        if ($individualPolicy) {
            return $individualPolicy;
        }

        // 2. Precedência Nível 2: Política do Estabelecimento
        if ($establishmentId) {
            // Prioriza política do estabelecimento com regime coincidente
            if ($regime) {
                $establishmentRegimePolicy = WorkTimeSettlementPolicy::where('establishment_id', $establishmentId)
                    ->whereNull('employee_id')
                    ->where('legal_regime', $regime->value)
                    ->activeAt($targetDate)
                    ->approved()
                    ->latest('id')
                    ->first();

                if ($establishmentRegimePolicy) {
                    return $establishmentRegimePolicy;
                }
            }

            // Política geral do estabelecimento (sem restrição de regime)
            $establishmentGeneralPolicy = WorkTimeSettlementPolicy::where('establishment_id', $establishmentId)
                ->whereNull('employee_id')
                ->whereNull('legal_regime')
                ->activeAt($targetDate)
                ->approved()
                ->latest('id')
                ->first();

            if ($establishmentGeneralPolicy) {
                return $establishmentGeneralPolicy;
            }
        }

        // 3. Precedência Nível 3: Política da Empresa / Geral da Instalação
        if ($regime) {
            $companyRegimePolicy = WorkTimeSettlementPolicy::whereNull('employee_id')
                ->whereNull('establishment_id')
                ->where('legal_regime', $regime->value)
                ->activeAt($targetDate)
                ->approved()
                ->latest('id')
                ->first();

            if ($companyRegimePolicy) {
                return $companyRegimePolicy;
            }
        }

        $companyGeneralPolicy = WorkTimeSettlementPolicy::whereNull('employee_id')
            ->whereNull('establishment_id')
            ->whereNull('legal_regime')
            ->activeAt($targetDate)
            ->approved()
            ->latest('id')
            ->first();

        if ($companyGeneralPolicy) {
            return $companyGeneralPolicy;
        }

        // 4. Precedência Nível 4: Fallback transparente para TimeBankPolicy legada
        return $this->createFallbackFromLegacyTimeBankPolicy($date, $regime);
    }

    /**
     * Gera uma representação em memória transparente a partir da TimeBankPolicy legada
     * para preservar 100% da compatibilidade e comportamento prévio do sistema.
     */
    protected function createFallbackFromLegacyTimeBankPolicy(CarbonInterface $date, ?LegalRegime $regime): WorkTimeSettlementPolicy
    {
        $legacyPolicy = TimeBankPolicy::forDate($date);

        if ($legacyPolicy) {
            if ($legacyPolicy->enabled) {
                $modality = match ($legacyPolicy->closing_mode) {
                    TimeBankClosingMode::MonthlyReset => SettlementModality::LegacyMonthlyReset,
                    TimeBankClosingMode::CarryOver => SettlementModality::CumulativeBank,
                    default => SettlementModality::CumulativeBank,
                };

                $policy = new WorkTimeSettlementPolicy([
                    'name' => $legacyPolicy->name ?? 'Banco de Horas Geral (Legado)',
                    'modality' => $modality,
                    'legal_regime' => $regime,
                    'effective_from' => $legacyPolicy->valid_from ?? now()->startOfYear(),
                    'effective_until' => $legacyPolicy->valid_until,
                    'legal_framework' => 'Regra geral de banco de horas configurada nas definições do sistema',
                    'approval_status' => SettlementPolicyApprovalStatus::Approved,
                    'status' => 'active',
                    'compensation_terms' => ['max_months' => ($modality === SettlementModality::LegacyMonthlyReset ? 1 : 6)],
                ]);
                $policy->id = $legacyPolicy->id;

                return $policy;
            }

            // Política legada explicitamente desativada
            return new WorkTimeSettlementPolicy([
                'name' => 'Sem Banco de Horas (Desativado nas Configurações)',
                'modality' => SettlementModality::NoBank,
                'legal_regime' => $regime,
                'effective_from' => $legacyPolicy->valid_from ?? Carbon::parse('2020-01-01'),
                'legal_framework' => 'Banco de horas desativado nas configurações do sistema',
                'approval_status' => SettlementPolicyApprovalStatus::Approved,
                'status' => 'active',
                'compensation_terms' => [],
            ]);
        }

        // Sem nenhuma política configurada no sistema: preserva o comportamento padrão de banco acumulativo do PTRP
        return new WorkTimeSettlementPolicy([
            'name' => 'Banco de Horas Geral (Padrão do Sistema)',
            'modality' => SettlementModality::CumulativeBank,
            'legal_regime' => $regime,
            'effective_from' => Carbon::parse('2020-01-01'),
            'legal_framework' => 'Regra geral de compensação e banco de horas do PTRP',
            'approval_status' => SettlementPolicyApprovalStatus::Approved,
            'status' => 'active',
            'compensation_terms' => ['max_months' => 6],
        ]);
    }

    /**
     * Classifica as diferenças da apuração conforme as regras jurídicas aplicáveis ao regime.
     * Separação conceitual: Fato Apurado -> Interpretação e Classificação Jurídica.
     */
    public function classify(
        Employee $employee,
        CarbonInterface $date,
        WorkTimeApuracaoResult $apuracao,
        ?WorkTimeSettlementPolicy $policy = null,
        ?LaborRuleProfile $ruleProfile = null,
        ?LaborPolicy $laborPolicy = null,
    ): WorkTimeClassificacaoResult {
        $policy = $policy ?? $this->resolvePolicy($employee, $date);
        $laborPolicy = $laborPolicy ?? new LaborPolicy;
        $regime = $employee->legal_regime;

        $notes = [];
        $toleratedMinutes = 0;
        $justifiedMinutes = 0;
        $compensableOvertime = 0;
        $remunerableOvertime = 0;
        $compensableDeficit = 0;
        $deductibleDeficit = 0;
        $pendingAnalysisMinutes = 0;
        $isPendingLegalDefinition = false;

        // Caso 1: Ausência de Escala ou Autorização Contratual
        if (! $apuracao->hasSchedule) {
            $pendingAnalysisMinutes = $apuracao->workedMinutes;
            $isPendingLegalDefinition = true;
            $notes[] = 'Ausência de escala de trabalho autorizada para a data. Horas trabalhadas pendentes de parametrização.';

            return new WorkTimeClassificacaoResult(
                toleratedMinutes: 0,
                justifiedMinutes: 0,
                compensableOvertimeMinutes: 0,
                remunerableOvertimeMinutes: 0,
                compensableDeficitMinutes: 0,
                deductibleDeficitMinutes: 0,
                pendingAnalysisMinutes: $pendingAnalysisMinutes,
                isPendingLegalDefinition: true,
                notes: $notes,
            );
        }

        // Caso 2: Servidor Estatutário sem Parametrização Normativa Específica
        // O PontoFácil NÃO aplica regras privadas da CLT (como banco semestral de acordo tácito) a estatutários.
        if ($regime && $regime->isStatutory()) {
            $isStatutoryFramework = $this->hasStatutoryLegalBasis($policy);

            if (! $isStatutoryFramework && $policy->modality->operatesTimeBankLedger()) {
                // Faltando fundamentação estatutária expressa do ente federativo para banco de horas
                $isPendingLegalDefinition = true;
                $pendingAnalysisMinutes = max(0, $apuracao->grossDifferenceMinutes);
                $notes[] = sprintf(
                    'Servidor estatutário (%s): regime de compensação/banco de horas requer previsão em lei ou regulamento próprio do ente federativo (CF/88 Art. 39, § 3º). Apuração pendente de parametrização jurídica.',
                    $regime->label()
                );
            }
        }

        // Caso 3: Tratamento de Diferenças Positivas (Horas Extras / Excedente)
        if ($apuracao->grossOvertimeMinutes > 0) {
            $overtime = $apuracao->grossOvertimeMinutes;

            // Tolerância CLT (Art. 58, § 1º) quando aplicável
            if ($regime === LegalRegime::CLT && $laborPolicy) {
                $tolerated = $laborPolicy->applyPunchTolerance($overtime);
                if ($tolerated === 0) {
                    $toleratedMinutes += $overtime;
                    $notes[] = sprintf('Excedente de %d min considerado dentro da tolerância legal (Art. 58, § 1º CLT).', $overtime);
                    $overtime = 0;
                }
            }

            if ($overtime > 0) {
                if ($isPendingLegalDefinition) {
                    $pendingAnalysisMinutes += $overtime;
                } else {
                    match ($policy->modality) {
                        SettlementModality::DirectPayroll, SettlementModality::NoBank => $remunerableOvertime += $overtime,
                        SettlementModality::CumulativeBank, SettlementModality::MonthlyCompensation, SettlementModality::Hybrid, SettlementModality::TimeOff, SettlementModality::LegacyMonthlyReset => $compensableOvertime += $overtime,
                    };
                }
            }
        }

        // Caso 4: Tratamento de Diferenças Negativas (Atrasos, Saídas Antecipadas e Faltas)
        $grossDeficit = $apuracao->grossLateMinutes + $apuracao->grossEarlyLeaveMinutes + $apuracao->grossAbsenceMinutes;
        if ($grossDeficit > 0) {
            // Tolerância CLT para atrasos
            if ($regime === LegalRegime::CLT && $laborPolicy && $apuracao->grossLateMinutes > 0) {
                $toleratedLate = $laborPolicy->applyPunchTolerance($apuracao->grossLateMinutes);
                if ($toleratedLate === 0) {
                    $toleratedMinutes += $apuracao->grossLateMinutes;
                    $notes[] = sprintf('Variação de atraso de %d min tolerada conforme Art. 58, § 1º da CLT.', $apuracao->grossLateMinutes);
                    $grossDeficit -= $apuracao->grossLateMinutes;
                }
            }

            if ($grossDeficit > 0) {
                if ($policy->modality->operatesTimeBankLedger() || $policy->modality === SettlementModality::MonthlyCompensation) {
                    $compensableDeficit += $grossDeficit;
                } else {
                    $deductibleDeficit += $grossDeficit;
                }
            }
        }

        return new WorkTimeClassificacaoResult(
            toleratedMinutes: $toleratedMinutes,
            justifiedMinutes: $justifiedMinutes,
            compensableOvertimeMinutes: $compensableOvertime,
            remunerableOvertimeMinutes: $remunerableOvertime,
            compensableDeficitMinutes: $compensableDeficit,
            deductibleDeficitMinutes: $deductibleDeficit,
            pendingAnalysisMinutes: $pendingAnalysisMinutes,
            isPendingLegalDefinition: $isPendingLegalDefinition,
            notes: $notes,
            details: [
                'policy_id' => $policy->id,
                'policy_name' => $policy->name,
                'modality' => $policy->modality->value,
                'regime' => $regime?->value,
            ]
        );
    }

    /**
     * Aplica a destinação formal e legalmente autorizada às quantidades classificadas.
     * Separação conceitual: Classificação -> Destinação.
     */
    public function destine(
        WorkTimeClassificacaoResult $classificacao,
        WorkTimeSettlementPolicy $policy,
    ): WorkTimeDestinacaoResult {
        $notes = [];
        $modality = $policy->modality;

        // Se houver pendência de definição legal, não direciona automaticamente para banco nem folha
        if ($classificacao->isPendingLegalDefinition) {
            $notes[] = 'Destinação sobrestada aguardando parametrização normativa do vínculo.';

            return new WorkTimeDestinacaoResult(
                modality: $modality,
                destinedToBankCreditMinutes: 0,
                destinedToBankDebitMinutes: 0,
                destinedToMonthlyCompensationMinutes: 0,
                destinedToPayrollCreditMinutes: 0,
                destinedToPayrollDebitMinutes: 0,
                destinedToDayOffMinutes: 0,
                pendingMinutes: $classificacao->pendingAnalysisMinutes,
                isPendingDefinition: true,
                notes: array_merge($notes, $classificacao->notes),
            );
        }

        $bankCredit = 0;
        $bankDebit = 0;
        $monthlyCompensation = 0;
        $payrollCredit = 0;
        $payrollDebit = 0;
        $dayOff = 0;

        switch ($modality) {
            case SettlementModality::NoBank:
                // Organização sem banco de horas: horas extras e atrasos vão para folha ou apuração analítica
                $payrollCredit = $classificacao->compensableOvertimeMinutes + $classificacao->remunerableOvertimeMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes + $classificacao->compensableDeficitMinutes;
                $notes[] = 'Organização operando sem banco de horas: saldos apurados destinados para controle de folha.';
                break;

            case SettlementModality::MonthlyCompensation:
                // Compensação exclusiva dentro do próprio mês
                $monthlyCompensation = $classificacao->compensableOvertimeMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes;
                $notes[] = 'Compensação restrita à competência corrente. Saldo apurado não acumula para o mês seguinte.';
                break;

            case SettlementModality::CumulativeBank:
                // Banco acumulativo: crédito e débito no ledger
                $bankCredit = $classificacao->compensableOvertimeMinutes;
                $bankDebit = $classificacao->compensableDeficitMinutes;
                $payrollCredit = $classificacao->remunerableOvertimeMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes;
                $notes[] = sprintf('Crédito/débito direcionado ao ledger de banco de horas (prazo acordado: até %d meses).', $policy->getMaxCompensationMonths());
                break;

            case SettlementModality::DirectPayroll:
                // Horas extras destinadas diretamente para a folha
                $payrollCredit = $classificacao->compensableOvertimeMinutes + $classificacao->remunerableOvertimeMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes + $classificacao->compensableDeficitMinutes;
                $notes[] = 'Horas extras autorizadas destinadas para pagamento em folha com adicional.';
                break;

            case SettlementModality::TimeOff:
                // Compensação por folga programada
                $dayOff = $classificacao->compensableOvertimeMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes;
                $notes[] = 'Saldo positivo destinado para concessão futura de folga compensatória.';
                break;

            case SettlementModality::Hybrid:
                // Modelo misto: limite para banco de horas, excedente para folha
                $dailyBankLimit = $policy->hybrid_rules['daily_bank_limit_minutes'] ?? null;
                $overtime = $classificacao->compensableOvertimeMinutes;

                if ($dailyBankLimit !== null && $overtime > (int) $dailyBankLimit) {
                    $bankCredit = (int) $dailyBankLimit;
                    $payrollCredit = $overtime - (int) $dailyBankLimit;
                    $notes[] = sprintf('Modelo Misto: %d min creditados em banco e %d min excedentes destinados à folha.', $bankCredit, $payrollCredit);
                } else {
                    $bankCredit = $overtime;
                    $payrollCredit = 0;
                    $notes[] = sprintf('Modelo Misto: total de %d min destinado ao banco de horas.', $bankCredit);
                }

                $bankDebit = $classificacao->compensableDeficitMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes;
                break;

            case SettlementModality::LegacyMonthlyReset:
                // Compatibilidade com o modo legado MONTHLY_RESET
                $bankCredit = $classificacao->compensableOvertimeMinutes;
                $bankDebit = $classificacao->compensableDeficitMinutes;
                $payrollCredit = $classificacao->remunerableOvertimeMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes;
                $notes[] = 'Modo legado MONTHLY_RESET: crédito/débito no ledger com zeramento contábil no fechamento formal do período.';
                break;
        }

        return new WorkTimeDestinacaoResult(
            modality: $modality,
            destinedToBankCreditMinutes: $bankCredit,
            destinedToBankDebitMinutes: $bankDebit,
            destinedToMonthlyCompensationMinutes: $monthlyCompensation,
            destinedToPayrollCreditMinutes: $payrollCredit,
            destinedToPayrollDebitMinutes: $payrollDebit,
            destinedToDayOffMinutes: $dayOff,
            pendingMinutes: $classificacao->pendingAnalysisMinutes,
            isPendingDefinition: false,
            notes: array_merge($notes, $classificacao->notes),
            details: [
                'policy_id' => $policy->id,
                'policy_name' => $policy->name,
            ]
        );
    }

    /**
     * Consolida os quatro conceitos para o trabalhador na data.
     */
    public function summarize(
        Employee $employee,
        CarbonInterface $date,
        WorkTimeApuracaoResult $apuracao,
        WorkTimeClassificacaoResult $classificacao,
        WorkTimeDestinacaoResult $destinacao,
        ?WorkTimeSettlementPolicy $policy = null,
    ): WorkTimeSettlementSummary {
        $policy = $policy ?? $this->resolvePolicy($employee, $date);

        return new WorkTimeSettlementSummary(
            apuracao: $apuracao,
            classificacao: $classificacao,
            destinacao: $destinacao,
            policy: $policy,
            dischargesSnapshot: [],
        );
    }

    /**
     * Verifica se a política possui fundamentação jurídica compatível com servidor estatutário.
     */
    protected function hasStatutoryLegalBasis(WorkTimeSettlementPolicy $policy): bool
    {
        $framework = strtolower((string) $policy->legal_framework);

        // Se mencionar lei, decreto, portaria, estatuto ou edital, considera fundamentada
        return str_contains($framework, 'lei')
            || str_contains($framework, 'decreto')
            || str_contains($framework, 'portaria')
            || str_contains($framework, 'estatuto')
            || str_contains($framework, '8.112')
            || str_contains($framework, 'instrucao normativa')
            || str_contains($framework, 'resolucao');
    }
}
