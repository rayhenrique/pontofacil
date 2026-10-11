<?php

namespace App\Domain\Settlement\Services;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Policies\LaborPolicy;
use App\Domain\Settlement\DTOs\WorkTimeApuracaoResult;
use App\Domain\Settlement\DTOs\WorkTimeClassificacaoResult;
use App\Domain\Settlement\DTOs\WorkTimeDestinacaoResult;
use App\Domain\Settlement\DTOs\WorkTimeSettlementSummary;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\SettlementPolicyApprovalStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Enums\LegalRegime;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LaborRuleProfile;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class WorkTimeSettlementService
{
    /**
     * Resolve a política de compensação e destinação aplicável para o colaborador na data.
     * Segue a precedência determinística:
     * 1. Política específica do Vínculo / Colaborador (employee_id)
     * 2. Política do Estabelecimento (establishment_id)
     * 3. Política Geral da Empresa / Instalação (company_id)
     * 4. Comportamento seguro quando não houver configuração
     */
    public function resolvePolicy(Employee $employee, CarbonInterface $date): WorkTimeSettlementPolicy
    {
        $targetDate = $date->format('Y-m-d');
        $regime = $employee->legal_regime;

        if (! Schema::hasTable('work_time_settlement_policies')) {
            return self::buildSafeUnconfiguredPolicy($regime);
        }

        $establishmentId = $employee->sector?->establishment_id;
        $companyId = $employee->sector?->establishment?->company_id;

        if (! $companyId) {
            $companyId = $employee->company_id ?? null;
        }

        // Em instalação single-tenant com única empresa cadastrada, resolve a empresa da instalação
        if (! $companyId && Company::count() === 1) {
            $companyId = CurrentCompany::get()?->id;
        }

        // 1. Precedência Nível 1: Política específica do Vínculo / Colaborador
        $individualQuery = WorkTimeSettlementPolicy::where('employee_id', $employee->id)
            ->activeAt($targetDate)
            ->approved();

        if ($companyId) {
            $individualQuery->where(function (Builder $q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhere(function (Builder $sub) {
                        $sub->whereNull('company_id')
                            ->whereRaw('(SELECT COUNT(*) FROM companies) <= 1');
                    });
            });
        }

        if ($regime) {
            $individualRegimePolicy = (clone $individualQuery)
                ->where('legal_regime', $regime->value)
                ->latest('id')
                ->first();

            if ($individualRegimePolicy) {
                return $individualRegimePolicy;
            }
        }

        $individualGeneralPolicy = (clone $individualQuery)
            ->whereNull('legal_regime')
            ->latest('id')
            ->first();

        if ($individualGeneralPolicy) {
            return $individualGeneralPolicy;
        }

        // 2. Precedência Nível 2: Política do Estabelecimento
        if ($establishmentId) {
            $establishment = $employee->sector?->establishment;
            $estCompanyId = $establishment?->company_id ?? $companyId;

            $establishmentQuery = WorkTimeSettlementPolicy::where('establishment_id', $establishmentId)
                ->whereNull('employee_id')
                ->activeAt($targetDate)
                ->approved();

            if ($estCompanyId) {
                $establishmentQuery->where(function (Builder $q) use ($estCompanyId) {
                    $q->where('company_id', $estCompanyId)
                        ->orWhere(function (Builder $sub) {
                            $sub->whereNull('company_id')
                                ->whereRaw('(SELECT COUNT(*) FROM companies) <= 1');
                        });
                });
            }

            // 2a. Prioriza política do estabelecimento com regime coincidente
            if ($regime) {
                $establishmentRegimePolicy = (clone $establishmentQuery)
                    ->where('legal_regime', $regime->value)
                    ->latest('id')
                    ->first();

                if ($establishmentRegimePolicy) {
                    return $establishmentRegimePolicy;
                }
            }

            // 2b. Política geral do estabelecimento (sem restrição de regime)
            $establishmentGeneralPolicy = (clone $establishmentQuery)
                ->whereNull('legal_regime')
                ->latest('id')
                ->first();

            if ($establishmentGeneralPolicy) {
                return $establishmentGeneralPolicy;
            }
        }

        // 3. Precedência Nível 3: Política da Empresa
        if ($companyId) {
            $companyQuery = WorkTimeSettlementPolicy::whereNull('employee_id')
                ->whereNull('establishment_id')
                ->where(function (Builder $q) use ($companyId) {
                    $q->where('company_id', $companyId)
                        ->orWhere(function (Builder $sub) {
                            $sub->whereNull('company_id')
                                ->whereRaw('(SELECT COUNT(*) FROM companies) <= 1');
                        });
                })
                ->activeAt($targetDate)
                ->approved();

            if ($regime) {
                $companyRegimePolicy = (clone $companyQuery)
                    ->where('legal_regime', $regime->value)
                    ->latest('id')
                    ->first();

                if ($companyRegimePolicy) {
                    return $companyRegimePolicy;
                }
            }

            $companyGeneralPolicy = (clone $companyQuery)
                ->whereNull('legal_regime')
                ->latest('id')
                ->first();

            if ($companyGeneralPolicy) {
                return $companyGeneralPolicy;
            }
        } elseif (Company::count() <= 1) {
            $globalQuery = WorkTimeSettlementPolicy::whereNull('employee_id')
                ->whereNull('establishment_id')
                ->activeAt($targetDate)
                ->approved();

            if ($regime) {
                $globalRegimePolicy = (clone $globalQuery)
                    ->where('legal_regime', $regime->value)
                    ->latest('id')
                    ->first();

                if ($globalRegimePolicy) {
                    return $globalRegimePolicy;
                }
            }

            $globalGeneralPolicy = (clone $globalQuery)
                ->whereNull('legal_regime')
                ->latest('id')
                ->first();

            if ($globalGeneralPolicy) {
                return $globalGeneralPolicy;
            }
        }

        // 4. Precedência Nível 4: Fallback seguro quando não houver configuração
        return $this->resolveFallbackPolicy($date, $regime);
    }

    /**
     * Resolve o comportamento seguro retrocompatível quando não há WorkTimeSettlementPolicy ativa.
     */
    protected function resolveFallbackPolicy(CarbonInterface $date, ?LegalRegime $regime): WorkTimeSettlementPolicy
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

            // Política legada explicitamente desativada nas configurações
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

        // Sem nenhuma política configurada no sistema: não cria políticas automáticas no banco.
        return self::buildSafeUnconfiguredPolicy($regime);
    }

    /**
     * Retorna uma representação em memória de estado não configurado seguro.
     */
    public static function buildSafeUnconfiguredPolicy(?LegalRegime $regime = null): WorkTimeSettlementPolicy
    {
        return new WorkTimeSettlementPolicy([
            'name' => 'Sem Política de Compensação Configurada',
            'modality' => SettlementModality::NoBank,
            'legal_regime' => $regime,
            'effective_from' => Carbon::parse('2020-01-01'),
            'legal_framework' => 'Nenhuma política de compensação de jornada configurada para o vínculo ou organização',
            'approval_status' => SettlementPolicyApprovalStatus::Draft,
            'status' => 'inactive',
            'compensation_terms' => [],
            'carry_over_terms' => [],
            'hybrid_rules' => [],
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

        $grossDeficit = $apuracao->grossLateMinutes + $apuracao->grossEarlyLeaveMinutes + $apuracao->grossAbsenceMinutes;
        $hasDifferences = ($apuracao->grossOvertimeMinutes > 0) || ($grossDeficit > 0) || ($apuracao->grossDifferenceMinutes !== 0);

        // Caso 2: Servidor Estatutário sem Parametrização Normativa Específica
        // O PontoFácil NÃO aplica regras privadas da CLT (como banco semestral de acordo tácito) a estatutários.
        $isPolicyApproved = $policy->isApproved() && $policy->status === 'active';
        if ($hasDifferences && ! $isPolicyApproved) {
            $isPendingLegalDefinition = true;
            $notes[] = 'Nenhuma política de compensação de jornada aprovada para o vínculo. Destinação pendente de definição.';
        }

        if ($hasDifferences && $regime && $regime->isStatutory()) {
            $isStatutoryFramework = $this->hasStatutoryLegalBasis($policy);

            if (! $isStatutoryFramework || ! $isPolicyApproved) {
                // Faltando fundamentação estatutária expressa do ente federativo para banco de horas
                $isPendingLegalDefinition = true;
                $notes[] = sprintf(
                    'Servidor estatutário (%s): regime de compensação/banco de horas requer previsão em lei ou regulamento próprio do ente federativo (CF/88 Art. 39, § 3º). Destinação pendente de definição.',
                    $regime->label()
                );
            }
        }

        // Caso 3: Tratamento de Diferenças Positivas (Horas Extras / Excedente)
        if ($apuracao->grossOvertimeMinutes > 0) {
            $overtime = $apuracao->grossOvertimeMinutes;

            // Tolerância CLT (Art. 58, § 1º) quando aplicável exclusivamente a regime CLT
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
                        SettlementModality::DirectPayroll => $remunerableOvertime += $overtime,
                        SettlementModality::NoBank => match (true) {
                            $policy->authorizesPayrollPayment() => $remunerableOvertime += $overtime,
                            $policy->authorizesMonthlyCompensation() => $compensableOvertime += $overtime,
                            default => $pendingAnalysisMinutes += $overtime,
                        },
                        SettlementModality::CumulativeBank,
                        SettlementModality::MonthlyCompensation,
                        SettlementModality::Hybrid,
                        SettlementModality::TimeOff,
                        SettlementModality::LegacyMonthlyReset => $compensableOvertime += $overtime,
                    };

                    if ($policy->modality === SettlementModality::NoBank && ! $policy->authorizesPayrollPayment() && ! $policy->authorizesMonthlyCompensation()) {
                        $notes[] = 'Destinação pendente de definição.';
                    }
                }
            }
        }

        // Caso 4: Tratamento de Diferenças Negativas (Atrasos, Saídas Antecipadas e Faltas)
        $grossDeficit = $apuracao->grossLateMinutes + $apuracao->grossEarlyLeaveMinutes + $apuracao->grossAbsenceMinutes;
        if ($grossDeficit > 0) {
            // Tolerância CLT para atrasos exclusivamente a regime CLT
            if ($regime === LegalRegime::CLT && $laborPolicy && $apuracao->grossLateMinutes > 0) {
                $toleratedLate = $laborPolicy->applyPunchTolerance($apuracao->grossLateMinutes);
                if ($toleratedLate === 0) {
                    $toleratedMinutes += $apuracao->grossLateMinutes;
                    $notes[] = sprintf('Variação de atraso de %d min tolerada conforme Art. 58, § 1º da CLT.', $apuracao->grossLateMinutes);
                    $grossDeficit -= $apuracao->grossLateMinutes;
                }
            }

            if ($grossDeficit > 0) {
                if ($isPendingLegalDefinition) {
                    $pendingAnalysisMinutes += $grossDeficit;
                } elseif ($policy->modality->operatesTimeBankLedger() || $policy->modality === SettlementModality::MonthlyCompensation) {
                    $compensableDeficit += $grossDeficit;
                } elseif ($policy->modality === SettlementModality::DirectPayroll) {
                    $deductibleDeficit += $grossDeficit;
                } elseif ($policy->modality === SettlementModality::NoBank) {
                    if ($policy->authorizesPayrollDeduction()) {
                        $deductibleDeficit += $grossDeficit;
                    } elseif ($policy->authorizesMonthlyCompensation()) {
                        $compensableDeficit += $grossDeficit;
                    } else {
                        $pendingAnalysisMinutes += $grossDeficit;
                        $notes[] = 'Destinação pendente de definição.';
                    }
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
            notes: array_values(array_unique($notes)),
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
                notes: array_values(array_unique(array_merge($notes, $classificacao->notes))),
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
                // Sem banco de horas: não existe contabilização em banco.
                // Diferenças só vão para folha ou compensação se houver autorização prévia.
                $payrollCredit = $classificacao->remunerableOvertimeMinutes;
                $payrollDebit = $classificacao->deductibleDeficitMinutes;
                $monthlyCompensation = $classificacao->compensableOvertimeMinutes;

                if ($payrollCredit > 0) {
                    $notes[] = 'Horas extras destinadas para pagamento em folha (autorizado em política).';
                }
                if ($payrollDebit > 0) {
                    $notes[] = 'Déficit destinado para desconto em folha (autorizado em política).';
                }
                if ($monthlyCompensation > 0) {
                    $notes[] = 'Compensação autorizada dentro do período.';
                }
                if ($classificacao->pendingAnalysisMinutes > 0) {
                    $notes[] = 'Destinação pendente de definição.';
                }
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

        $isPendingDef = $classificacao->pendingAnalysisMinutes > 0 && ($payrollCredit === 0 && $payrollDebit === 0 && $bankCredit === 0 && $bankDebit === 0 && $monthlyCompensation === 0);

        return new WorkTimeDestinacaoResult(
            modality: $modality,
            destinedToBankCreditMinutes: $bankCredit,
            destinedToBankDebitMinutes: $bankDebit,
            destinedToMonthlyCompensationMinutes: $monthlyCompensation,
            destinedToPayrollCreditMinutes: $payrollCredit,
            destinedToPayrollDebitMinutes: $payrollDebit,
            destinedToDayOffMinutes: $dayOff,
            pendingMinutes: $classificacao->pendingAnalysisMinutes,
            isPendingDefinition: $isPendingDef,
            notes: array_values(array_unique(array_merge($notes, $classificacao->notes))),
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
        if (! $policy->isApproved()) {
            return false;
        }

        $framework = strtolower(trim((string) $policy->legal_framework));

        if (empty($framework) || strlen($framework) < 4) {
            return false;
        }

        if ($framework === 'clt' || $framework === 'lei' || $framework === 'decreto') {
            return false;
        }

        return (str_contains($framework, 'lei') && ! str_contains($framework, 'apenas lei'))
            || str_contains($framework, 'decreto')
            || str_contains($framework, 'portaria')
            || str_contains($framework, 'estatuto')
            || str_contains($framework, '8.112')
            || str_contains($framework, 'instrucao normativa')
            || str_contains($framework, 'resolucao');
    }

    /**
     * Resolve no servidor a disponibilidade autoritativa de minutos para a origem informada,
     * diferenciando com rigor créditos e débitos e deduzindo destinações ativas já realizadas.
     * Não confia em valores enviados externamente pelo chamador.
     *
     * @return array{available_credit: int, available_debit: int, source_description: string}
     */
    public function resolveAuthoritativeAvailableMinutes(
        Employee $employee,
        SettlementOriginType $originType,
        ?string $originIdentifier,
        string $referencePeriod,
        ?string $ignoreSettlementId = null,
    ): array {
        return match ($originType) {
            SettlementOriginType::DailyJourney => $this->resolveDailyJourneyMinutes($employee, $originIdentifier, $referencePeriod, $ignoreSettlementId),
            SettlementOriginType::MonthlyClosingBalance => $this->resolveMonthlyClosingMinutes($employee, $referencePeriod, $ignoreSettlementId),
            SettlementOriginType::TimeBankBalance => $this->resolveTimeBankBalanceMinutes($employee, $ignoreSettlementId),
            SettlementOriginType::AdministrativeDiscrepancy => [
                'available_credit' => PHP_INT_MAX,
                'available_debit' => PHP_INT_MAX,
                'source_description' => 'Ajuste ou discrepância administrativa autorizada',
            ],
        };
    }

    /**
     * Resolve os minutos disponíveis a partir de uma jornada diária específica (aberta ou fechada).
     */
    protected function resolveDailyJourneyMinutes(
        Employee $employee,
        ?string $originIdentifier,
        string $referencePeriod,
        ?string $ignoreSettlementId,
    ): array {
        $dateStr = $originIdentifier ?: "{$referencePeriod}-01";
        $journeyDate = Carbon::parse($dateStr);

        $closedPeriod = ClosedPeriod::findForPeriod($journeyDate->year, $journeyDate->month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        $grossCredit = 0;
        $grossDebit = 0;
        $sourceDesc = '';

        if ($isClosed) {
            $snapshot = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closedPeriod->id)
                ->where('employee_id', $employee->id)
                ->first();

            $day = collect($snapshot?->journey_snapshot ?? [])->firstWhere('date', $journeyDate->toDateString());
            if ($day) {
                $grossCredit = (int) ($day['overtime_minutes'] ?? max(0, $day['daily_difference_minutes'] ?? 0));
                $grossDebit = (int) ($day['deficit_minutes'] ?? abs(min(0, $day['daily_difference_minutes'] ?? 0)));
            }
            $sourceDesc = sprintf('Snapshot congelado da competência fechada %02d/%04d (dia %s)', $journeyDate->month, $journeyDate->year, $journeyDate->format('d/m/Y'));
        } else {
            $journey = app(CalculateDailyJourneyAction::class)->execute($employee, $journeyDate);
            $grossCredit = (int) $journey->overtimeMinutes;
            $grossDebit = (int) ($journey->lateMinutes + $journey->earlyLeaveMinutes + $journey->absenceMinutes);
            $sourceDesc = sprintf('Apuração analítica diária do PTRP (dia %s)', $journeyDate->format('d/m/Y'));
        }

        $existingQuery = WorkTimeSettlement::where('employee_id', $employee->id)
            ->where('origin_type', SettlementOriginType::DailyJourney->value)
            ->where(function ($q) use ($journeyDate) {
                $q->where('origin_identifier', $journeyDate->toDateString())
                    ->orWhere('operation_date', $journeyDate->toDateString());
            })
            ->whereNotIn('status', [WorkTimeSettlementStatus::Cancelled, WorkTimeSettlementStatus::Reversed])
            ->whereNull('reversal_of_id');

        if ($ignoreSettlementId) {
            $existingQuery->where('id', '!=', $ignoreSettlementId);
        }

        $existing = $existingQuery->get();
        $alreadyCredit = (int) $existing->where('minutes', '>', 0)->sum('minutes');
        $alreadyDebit = (int) abs($existing->where('minutes', '<', 0)->sum('minutes'));

        return [
            'available_credit' => max(0, $grossCredit - $alreadyCredit),
            'available_debit' => max(0, $grossDebit - $alreadyDebit),
            'source_description' => $sourceDesc,
        ];
    }

    /**
     * Resolve os minutos disponíveis a partir do fechamento de uma competência mensal.
     */
    protected function resolveMonthlyClosingMinutes(
        Employee $employee,
        string $referencePeriod,
        ?string $ignoreSettlementId,
    ): array {
        $parts = explode('-', $referencePeriod);
        $year = (int) ($parts[0] ?? now()->year);
        $month = (int) ($parts[1] ?? now()->month);

        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        $snapshot = null;
        if ($isClosed) {
            $snapshot = ClosedPeriodEmployeeSnapshot::where('closed_period_id', $closedPeriod->id)
                ->where('employee_id', $employee->id)
                ->first();
        }

        if ($snapshot) {
            $closingBalance = (int) ($snapshot->time_bank_snapshot['balance_at_closing'] ?? 0);
            $grossCredit = $closingBalance > 0 ? $closingBalance : 0;
            $grossDebit = $closingBalance < 0 ? abs($closingBalance) : 0;

            if ($grossCredit === 0 && ! empty($snapshot->journey_snapshot)) {
                $grossCredit = (int) collect($snapshot->journey_snapshot)->sum(fn ($j) => $j['destined_to_payroll_minutes'] ?? $j['overtime_minutes'] ?? 0);
            }
            $sourceDesc = sprintf('Snapshot congelado de fechamento da competência %s', $referencePeriod);
        } else {
            $timeBank = TimeBankAccount::where('employee_id', $employee->id)->first();
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            $closingBalance = $timeBank ? $timeBank->balanceUntil($endOfMonth) : 0;

            $grossCredit = $closingBalance > 0 ? $closingBalance : 0;
            $grossDebit = $closingBalance < 0 ? abs($closingBalance) : 0;

            if ($grossCredit === 0) {
                $calculateAction = app(CalculateDailyJourneyAction::class);
                $totalOvertime = 0;
                $daysInMonth = $endOfMonth->day;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayDate = Carbon::createFromDate($year, $month, $d);
                    $journey = $calculateAction->execute($employee, $dayDate);
                    $totalOvertime += (int) $journey->overtimeMinutes;
                }
                if ($totalOvertime > 0) {
                    $grossCredit = $totalOvertime;
                }
            }

            $sourceDesc = sprintf('Saldo apurado para a competência %s', $referencePeriod);
        }

        $existingQuery = WorkTimeSettlement::where('employee_id', $employee->id)
            ->where('reference_period', $referencePeriod)
            ->where('origin_type', SettlementOriginType::MonthlyClosingBalance->value)
            ->whereNotIn('status', [WorkTimeSettlementStatus::Cancelled, WorkTimeSettlementStatus::Reversed])
            ->whereNull('reversal_of_id');

        if ($ignoreSettlementId) {
            $existingQuery->where('id', '!=', $ignoreSettlementId);
        }

        $existing = $existingQuery->get();
        $alreadyCredit = (int) $existing->where('minutes', '>', 0)->sum('minutes');
        $alreadyDebit = (int) abs($existing->where('minutes', '<', 0)->sum('minutes'));

        return [
            'available_credit' => max(0, $grossCredit - $alreadyCredit),
            'available_debit' => max(0, $grossDebit - $alreadyDebit),
            'source_description' => $sourceDesc,
        ];
    }

    /**
     * Resolve os minutos disponíveis no saldo acumulado do banco de horas.
     */
    protected function resolveTimeBankBalanceMinutes(
        Employee $employee,
        ?string $ignoreSettlementId,
    ): array {
        $timeBank = TimeBankAccount::where('employee_id', $employee->id)->first();
        $currentBalance = $timeBank ? $timeBank->currentBalance() : 0;

        $grossCredit = $currentBalance > 0 ? $currentBalance : 0;
        $grossDebit = $currentBalance < 0 ? abs($currentBalance) : 0;

        $existingQuery = WorkTimeSettlement::where('employee_id', $employee->id)
            ->where('origin_type', SettlementOriginType::TimeBankBalance->value)
            ->whereIn('status', [WorkTimeSettlementStatus::Pending, WorkTimeSettlementStatus::Approved, WorkTimeSettlementStatus::AwaitingExecution])
            ->whereNull('reversal_of_id');

        if ($ignoreSettlementId) {
            $existingQuery->where('id', '!=', $ignoreSettlementId);
        }

        $existing = $existingQuery->get();
        $alreadyCredit = (int) $existing->where('minutes', '>', 0)->sum('minutes');
        $alreadyDebit = (int) abs($existing->where('minutes', '<', 0)->sum('minutes'));

        return [
            'available_credit' => max(0, $grossCredit - $alreadyCredit),
            'available_debit' => max(0, $grossDebit - $alreadyDebit),
            'source_description' => sprintf('Ledger de banco de horas (saldo atual: %s)', TimeBankAccount::formatMinutes($currentBalance)),
        ];
    }
}
