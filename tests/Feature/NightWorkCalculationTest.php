<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\LaborRules\Actions\CalculateNightWorkAction;
use App\Domain\LaborRules\DTOs\WorkInterval;
use App\Domain\LaborRules\Enums\NightCalculationStatus;
use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Enums\LaborRuleApprovalStatus;
use App\Enums\LegalRegime;
use App\Enums\WorkScheduleModality;
use App\Models\ClosedPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NightWorkCalculationTest extends TestCase
{
    use RefreshDatabase;

    private CalculateNightWorkAction $action;

    private Company $company;

    private Establishment $establishment;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->action = new CalculateNightWorkAction;
        $this->company = CurrentCompany::get();
        $this->establishment = CurrentCompany::defaultEstablishment();

        $this->sector = Sector::create([
            'establishment_id' => $this->establishment->id,
            'name' => 'Operações Noturnas',
        ]);
    }

    /**
     * Teste 1: CLT Urbana Geral (22h às 05h, sem intervalo).
     * 420 minutos físicos trabalhados -> 480 minutos equivalentes com hora de 52m30s.
     * O tempo físico de permanência JAMAIS é inflado.
     */
    public function test_clt_urban_night_work_calculation_and_physical_vs_legal_separation(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-10-10 22:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 05:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile
        );

        // Tempo físico trabalhado: 7 horas = 420 minutos = 25.200 segundos
        $this->assertEquals(25200, $result->physicalWorkedSeconds);
        $this->assertEquals(420, $result->physicalWorkedMinutes());
        $this->assertEquals('07:00', $result->formattedPhysicalWorked());

        // Tempo físico noturno: 7 horas = 420 minutos = 25.200 segundos
        $this->assertEquals(25200, $result->physicalNightSeconds);
        $this->assertEquals(420, $result->physicalNightMinutes());
        $this->assertEquals('07:00', $result->formattedPhysicalNight());

        // Equivalência legal noturna com hora ficta de 52m30s (Fator 8/7): 8 horas = 480 minutos = 28.800 segundos
        $this->assertEquals(28800, $result->legalNightEquivalentSeconds);
        $this->assertEquals(480, $result->legalNightEquivalentMinutes());
        $this->assertEquals('08:00', $result->formattedLegalNightEquivalent());

        // Bônus ficto: 60 minutos (3.600 segundos)
        $this->assertEquals(3600, $result->nightFictionalBonusSeconds());
        $this->assertEquals(60, $result->nightFictionalBonusMinutes());

        // Parâmetros legais aplicados
        $this->assertEquals(20.0, $result->nightAdditionalPercentage);
        $this->assertEquals(0, $result->nightExtensionSeconds);
        $this->assertEquals(NightCalculationStatus::Calculated, $result->calculationStatus);
        $this->assertEquals('clt_urban_standard', $profile->code);
    }

    /**
     * Teste 2: Servidor Público Federal (Lei 8.112/1990, art. 75).
     * Adicional de 25% e hora noturna de 52m30s.
     */
    public function test_federal_statutory_night_work_calculation(): void
    {
        $profile = LaborRuleProfile::createFederalStatutoryProfile();

        $start = Carbon::parse('2026-10-10 22:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 05:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile
        );

        $this->assertEquals(25200, $result->physicalNightSeconds);
        $this->assertEquals(28800, $result->legalNightEquivalentSeconds);
        $this->assertEquals(25.0, $result->nightAdditionalPercentage);
        $this->assertEquals(NightCalculationStatus::Calculated, $result->calculationStatus);
        $this->assertStringContainsString('Lei nº 8.112/1990', $result->legalReference);
    }

    /**
     * Teste 3: Estatutário Municipal com regra validada e aprovada pelo RH local.
     */
    public function test_municipal_statutory_with_configured_and_approved_rule(): void
    {
        $profile = LaborRuleProfile::createMunicipalStatutoryProfile(
            name: 'Estatuto dos Servidores de Maceió - Lei 1.234/2020',
            code: 'municipal_statutory_maceio',
            legalReference: 'Lei Municipal nº 1.234/2020, art. 88',
            config: [
                'night_start_time' => '22:00',
                'night_end_time' => '05:00',
                'additional_percentage' => 20.0,
                'reduced_hour_seconds' => 3150,
                'apply_reduced_hour' => true,
                'apply_extension' => false,
            ]
        );

        $user = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '111.222.333-44',
            'legal_regime' => LegalRegime::MunicipalStatutory,
            'labor_rule_profile_id' => $profile->id,
        ]);

        $resolved = $employee->getLaborRuleProfileForDate(Carbon::parse('2026-10-10'));
        $this->assertNotNull($resolved);
        $this->assertEquals('municipal_statutory_maceio', $resolved->code);

        $start = Carbon::parse('2026-10-10 22:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 05:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $resolved
        );

        $this->assertEquals(25200, $result->physicalNightSeconds);
        $this->assertEquals(28800, $result->legalNightEquivalentSeconds);
        $this->assertEquals(20.0, $result->nightAdditionalPercentage);
        $this->assertEquals(NightCalculationStatus::Calculated, $result->calculationStatus);
    }

    /**
     * Teste 4: Estatutário Municipal SEM regra validada.
     * NÃO herda CLT nem Lei 8.112. Apura tempo cronológico e marca pending_configuration.
     */
    public function test_municipal_statutory_without_rule_is_marked_pending_and_does_not_invent_rules(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '555.666.777-88',
            'legal_regime' => LegalRegime::MunicipalStatutory,
        ]);

        $resolved = $employee->getLaborRuleProfileForDate(Carbon::parse('2026-10-10'));
        $this->assertNull($resolved, 'Estatutário municipal não deve herdar perfil CLT ou federal automaticamente');

        $start = Carbon::parse('2026-10-10 22:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 05:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: null,
            context: ['legal_regime' => LegalRegime::MunicipalStatutory]
        );

        // Tempo físico apurado com exatidão cronológica
        $this->assertEquals(25200, $result->physicalWorkedSeconds);
        $this->assertEquals(25200, $result->physicalNightSeconds);

        // Equivalência legal NUNCA inventa hora reduzida: 1:1 com o físico
        $this->assertEquals(25200, $result->legalNightEquivalentSeconds);
        $this->assertEquals(0, $result->nightFictionalBonusSeconds());

        // Adicional não é presumido
        $this->assertNull($result->nightAdditionalPercentage);
        $this->assertEquals(0, $result->nightExtensionSeconds);

        // Status pendente de parametrização
        $this->assertEquals(NightCalculationStatus::PendingConfiguration, $result->calculationStatus);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('Regime estatutário sem perfil normativo validado', $result->warnings[0]);
    }

    /**
     * Teste 5: Intervalos noturnos comprovadamente usufruídos (exclusão de descanso).
     * 19:00 às 07:00 com intervalo de 00:00 às 01:00.
     */
    public function test_night_break_deduction_from_worked_and_night_seconds(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-10-10 19:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 07:00:00', 'America/Sao_Paulo');

        $breakStart = Carbon::parse('2026-10-11 00:00:00', 'America/Sao_Paulo');
        $breakEnd = Carbon::parse('2026-10-11 01:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile,
            breakIntervals: [new WorkInterval($breakStart, $breakEnd, 'break', true)],
            context: ['is_12x36' => true] // 12x36 compensa prorrogação
        );

        // Tempo físico trabalhado total: 12h - 1h descanso = 11h = 39.600 segundos (660 minutos)
        $this->assertEquals(39600, $result->physicalWorkedSeconds);
        $this->assertEquals(660, $result->physicalWorkedMinutes());

        // Tempo físico noturno: 7h da janela (22h às 05h) - 1h de descanso (00h às 01h) = 6 horas = 21.600 segundos
        $this->assertEquals(21600, $result->physicalNightSeconds);
        $this->assertEquals(360, $result->physicalNightMinutes());

        // Equivalência com hora ficta: (21.600 * 3600) / 3150 = 24.686 segundos (~411 minutos)
        $this->assertEquals(24686, $result->legalNightEquivalentSeconds);
        $this->assertEquals(411, $result->legalNightEquivalentMinutes());
    }

    /**
     * Teste 6: Prorrogação após as 05:00 em jornada ordinária (Art. 73, § 5º CLT / Súmula 60 TST).
     * Caso A: 21:00 → 06:00 (prorrogação das 05:00 às 06:00 = 1 hora).
     */
    public function test_night_extension_calculation_21_to_06(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-10-10 21:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 06:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile,
            context: ['is_12x36' => false]
        );

        // Físico noturno (22h às 05h): 7 horas = 25.200 segundos
        $this->assertEquals(25200, $result->physicalNightSeconds);

        // Prorrogação pós-05h (05h às 06h): 1 hora = 3.600 segundos
        $this->assertEquals(3600, $result->nightExtensionSeconds);
        $this->assertEquals(60, $result->nightExtensionMinutes());

        // Base total noturna elegível: 25.200 + 3.600 = 28.800 segundos
        // Equivalência legal com hora reduzida: (28.800 * 8) / 7 = 32.914 segundos (~549 minutos)
        $this->assertEquals(32914, $result->legalNightEquivalentSeconds);
    }

    /**
     * Teste 7: Prorrogação noturna com início às 23:00 e término às 08:00.
     * Noturno ordinário: 23:00 às 05:00 (6h = 21.600s).
     * Prorrogação: 05:00 às 08:00 (3h = 10.800s).
     */
    public function test_night_extension_calculation_23_to_08(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-10-10 23:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 08:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile,
            context: ['is_12x36' => false]
        );

        $this->assertEquals(21600, $result->physicalNightSeconds);
        $this->assertEquals(10800, $result->nightExtensionSeconds);
        $this->assertEquals(180, $result->nightExtensionMinutes());

        // Base = 21.600 + 10.800 = 32.400 segundos.
        // Equivalência legal = (32.400 * 8) / 7 = 37.029 segundos.
        $this->assertEquals(37029, $result->legalNightEquivalentSeconds);
    }

    /**
     * Teste 8: Escala 12×36 sob CLT Urbana (Art. 59-A, parágrafo único).
     * Plantão das 19:00 às 07:00: prorrogação após 05:00 é considerada compensada pela folga de 36h.
     * Não gera hora extra automática nem distorce a jornada.
     */
    public function test_scale_12x36_compensates_extension_under_clt_art_59_a(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-10-10 19:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 07:00:00', 'America/Sao_Paulo');

        $schedule = WorkSchedule::create([
            'name' => 'Escala 12x36 Noturna Teste',
            'modality' => WorkScheduleModality::TwelveByThirtySix,
            'tolerance_minutes' => 5,
        ]);

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile,
            schedule: $schedule
        );

        $this->assertEquals(43200, $result->physicalWorkedSeconds); // 12h = 43.200s
        $this->assertEquals(25200, $result->physicalNightSeconds);  // 22h às 05h = 7h = 25.200s
        $this->assertEquals(0, $result->nightExtensionSeconds, 'Em 12x36 sob o Art. 59-A CLT a prorrogação é compensada');
        $this->assertEquals(28800, $result->legalNightEquivalentSeconds); // 7h * 8/7 = 8h = 28.800s

        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('Art. 59-A', $result->warnings[0]);
    }

    /**
     * Teste 9: Escala 24×72 com perfil permitido vs perfil não permitido.
     */
    public function test_scale_24x72_modality_validation(): void
    {
        $federalProfile = LaborRuleProfile::createFederalStatutoryProfile(); // Permite 24x72
        $cltProfile = LaborRuleProfile::createStandardCltProfile();          // Não permite 24x72 sem autorização

        $start = Carbon::parse('2026-10-10 07:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 07:00:00', 'America/Sao_Paulo');

        $schedule24 = WorkSchedule::create([
            'name' => 'Escala 24x72 Especial',
            'modality' => WorkScheduleModality::TwentyFourBySeventyTwo,
            'requires_legal_authorization' => true,
        ]);

        // Perfil Federal: permitido sem warnings de não-conformidade
        $resultFed = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $federalProfile,
            schedule: $schedule24
        );
        $this->assertEquals(86400, $resultFed->physicalWorkedSeconds); // 24h
        $this->assertEquals(25200, $resultFed->physicalNightSeconds);  // 7h noturnas
        $this->assertEmpty($resultFed->warnings);

        // Perfil CLT padrão: emite warning de conformidade legal sobre jornada > 12h
        $resultClt = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $cltProfile,
            schedule: $schedule24
        );
        $this->assertNotEmpty($resultClt->warnings);
        $this->assertStringContainsString('modalidade 24x72 sem autorização expressa', $resultClt->warnings[0]);
    }

    /**
     * Teste 10: Plantão que atravessa mudança de mês (31 de Outubro às 19h até 01 de Novembro às 07h).
     */
    public function test_shift_crossing_month_boundary(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-10-31 19:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-11-01 07:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile,
            context: ['is_12x36' => true]
        );

        $this->assertEquals(43200, $result->physicalWorkedSeconds);
        $this->assertEquals(25200, $result->physicalNightSeconds);
        $this->assertEquals(28800, $result->legalNightEquivalentSeconds);
    }

    /**
     * Teste 11: Plantão que atravessa mudança de ano (31 de Dezembro às 19h até 01 de Janeiro às 07h).
     */
    public function test_shift_crossing_year_boundary(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-12-31 19:00:00', 'America/Sao_Paulo');
        $end = Carbon::parse('2027-01-01 07:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile,
            context: ['is_12x36' => true]
        );

        $this->assertEquals(43200, $result->physicalWorkedSeconds);
        $this->assertEquals(25200, $result->physicalNightSeconds);
        $this->assertEquals(28800, $result->legalNightEquivalentSeconds);
    }

    /**
     * Teste 12: Fuso horário do estabelecimento respeitado com exatidão (Manaus GMT-4 vs SP GMT-3).
     */
    public function test_establishment_timezone_preservation(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        // 22:00 às 05:00 no fuso de Manaus
        $startManaus = Carbon::parse('2026-10-10 22:00:00', 'America/Manaus');
        $endManaus = Carbon::parse('2026-10-11 05:00:00', 'America/Manaus');

        $resultManaus = $this->action->execute(
            workedIntervals: [new WorkInterval($startManaus, $endManaus)],
            timezone: 'America/Manaus',
            profile: $profile
        );

        $this->assertEquals(25200, $resultManaus->physicalNightSeconds);
        $this->assertEquals(28800, $resultManaus->legalNightEquivalentSeconds);
        $this->assertEquals('America/Manaus', $resultManaus->appliedParameters['timezone']);
    }

    /**
     * Teste 13: Precisão em segundos sem arredondamentos intermediários.
     * Exemplo: Entrada às 22:15:30 e Saída às 04:45:15.
     * Total = 6 horas, 29 minutos e 45 segundos = 23.385 segundos.
     */
    public function test_second_level_precision_without_loss(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        $start = Carbon::parse('2026-10-10 22:15:30', 'America/Sao_Paulo');
        $end = Carbon::parse('2026-10-11 04:45:15', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [new WorkInterval($start, $end)],
            timezone: 'America/Sao_Paulo',
            profile: $profile
        );

        $expectedSeconds = $end->getTimestamp() - $start->getTimestamp();
        $this->assertEquals(23385, $expectedSeconds);
        $this->assertEquals(23385, $result->physicalWorkedSeconds);
        $this->assertEquals(23385, $result->physicalNightSeconds);

        // Equivalência exata: round(23385 * 3600 / 3150) = round(26725.714) = 26726 segundos
        $this->assertEquals(26726, $result->legalNightEquivalentSeconds);
    }

    /**
     * Teste 14: Mudança de perfil normativo por vigência histórica.
     */
    public function test_normative_profile_transition_by_effective_date(): void
    {
        // Perfil antigo com 20%
        $oldProfile = LaborRuleProfile::create([
            'name' => 'Norma Histórica 2025',
            'code' => 'hist_profile_2025',
            'legal_regime' => LegalRegime::CLT,
            'jurisdiction' => 'federal',
            'legal_reference' => 'Acordo Coletivo 2025',
            'effective_from' => '2025-01-01',
            'effective_until' => '2025-12-31',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'configuration' => [
                'night_start_time' => '22:00',
                'night_end_time' => '05:00',
                'additional_percentage' => 20.0,
                'reduced_hour_seconds' => 3150,
                'apply_reduced_hour' => true,
            ],
        ]);

        // Perfil novo com 22% a partir de 2026
        $newProfile = LaborRuleProfile::create([
            'name' => 'Norma Atualizada 2026',
            'code' => 'new_profile_2026',
            'legal_regime' => LegalRegime::CLT,
            'jurisdiction' => 'federal',
            'legal_reference' => 'Acordo Coletivo 2026',
            'effective_from' => '2026-01-01',
            'approval_status' => LaborRuleApprovalStatus::Approved,
            'configuration' => [
                'night_start_time' => '22:00',
                'night_end_time' => '05:00',
                'additional_percentage' => 22.0,
                'reduced_hour_seconds' => 3150,
                'apply_reduced_hour' => true,
            ],
        ]);

        $this->assertTrue($oldProfile->isEffectiveAt('2025-06-15'));
        $this->assertFalse($oldProfile->isEffectiveAt('2026-06-15'));
        $this->assertTrue($newProfile->isEffectiveAt('2026-06-15'));
    }

    /**
     * Teste 15: Ausência de dupla contagem em batidas contíguas ou encadeadas.
     */
    public function test_no_double_counting_in_chained_intervals(): void
    {
        $profile = LaborRuleProfile::createStandardCltProfile();

        // Intervalo 1: 22h às 00h (2 horas)
        $start1 = Carbon::parse('2026-10-10 22:00:00', 'America/Sao_Paulo');
        $end1 = Carbon::parse('2026-10-11 00:00:00', 'America/Sao_Paulo');

        // Intervalo 2: 00h às 05h (5 horas)
        $start2 = Carbon::parse('2026-10-11 00:00:00', 'America/Sao_Paulo');
        $end2 = Carbon::parse('2026-10-11 05:00:00', 'America/Sao_Paulo');

        $result = $this->action->execute(
            workedIntervals: [
                new WorkInterval($start1, $end1),
                new WorkInterval($start2, $end2),
            ],
            timezone: 'America/Sao_Paulo',
            profile: $profile
        );

        $this->assertEquals(25200, $result->physicalWorkedSeconds);
        $this->assertEquals(25200, $result->physicalNightSeconds);
        $this->assertEquals(28800, $result->legalNightEquivalentSeconds);
    }

    /**
     * Teste 16: Validação de consistência legal no LaborRuleProfile.
     * Impede percentuais e parâmetros que contrariem a CLT e a Lei 8.112.
     */
    public function test_labor_rule_profile_prevents_illegal_configurations(): void
    {
        // 1. CLT não pode ter adicional inferior a 20%
        $this->expectException(ValidationException::class);

        LaborRuleProfile::create([
            'name' => 'Perfil Ilegal CLT',
            'code' => 'illegal_clt',
            'legal_regime' => LegalRegime::CLT,
            'jurisdiction' => 'federal',
            'legal_reference' => 'Referência de Teste',
            'effective_from' => '2026-01-01',
            'configuration' => [
                'additional_percentage' => 15.0, // Inválido: menor que 20%
            ],
        ]);
    }

    /**
     * Teste 17: Lei 8.112 não pode ter adicional inferior a 25%.
     */
    public function test_federal_profile_prevents_less_than_25_percent(): void
    {
        $this->expectException(ValidationException::class);

        LaborRuleProfile::create([
            'name' => 'Perfil Ilegal Federal',
            'code' => 'illegal_federal',
            'legal_regime' => LegalRegime::FederalStatutory,
            'jurisdiction' => 'federal',
            'legal_reference' => 'Referência de Teste',
            'effective_from' => '2026-01-01',
            'configuration' => [
                'additional_percentage' => 20.0, // Inválido para Lei 8.112: piso é 25%
            ],
        ]);
    }

    /**
     * Teste 18: Auditoria e imutabilidade de competências fechadas.
     * Perfil alterado hoje não pode reescrever período fechado.
     */
    public function test_closed_periods_preserve_audit_snapshot_immutability(): void
    {
        $closer = User::factory()->create();
        $closed = ClosedPeriod::create([
            'year' => 2026,
            'month' => 9,
            'status' => 'closed',
            'snapshot_version' => 1,
            'snapshot_hash' => hash('sha256', 'closed-september-2026'),
            'closed_by' => $closer->id,
            'closed_at' => now(),
        ]);

        $this->assertTrue(ClosedPeriod::isClosed(2026, 9));
        $this->assertFalse(ClosedPeriod::isClosed(2026, 10));
    }

    /**
     * Teste 19: Integração de CalculateDailyJourneyAction com CalculateNightWorkAction.
     * Marcações reais no REP-P continuam 100% imutáveis enquanto o PTRP anexa os cálculos noturnos.
     */
    public function test_ptrp_calculate_daily_journey_integrates_night_work_and_preserves_punch_events(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $user->id,
            'sector_id' => $this->sector->id,
            'cpf' => '999.888.777-66',
            'legal_regime' => LegalRegime::CLT,
        ]);

        $date = Carbon::parse('2026-10-12', 'America/Sao_Paulo');

        // Batidas do dia: 22:00 (in) e 05:00 (out)
        // No PTRP de CalculateDailyJourneyAction: tipos 'in' e 'out'
        PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'nsr' => 1,
            'occurred_at_utc' => Carbon::parse('2026-10-13 01:00:00'),
            'occurred_at_local' => Carbon::parse('2026-10-12 22:00:00'),
            'direction' => 'in',
            'source' => 'web_portal',
            'fiscal_hash' => hash('sha256', '1'),
            'audit_chain_hash' => hash('sha256', 'chain1'),
            'payload_hash' => hash('sha256', 'payload1'),
        ]);

        PunchEvent::create([
            'id' => (string) Str::ulid(),
            'establishment_id' => $this->establishment->id,
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'nsr' => 2,
            'occurred_at_utc' => Carbon::parse('2026-10-13 08:00:00'),
            'occurred_at_local' => Carbon::parse('2026-10-13 05:00:00'),
            'direction' => 'out',
            'source' => 'web_portal',
            'fiscal_hash' => hash('sha256', '2'),
            'audit_chain_hash' => hash('sha256', 'chain2'),
            'payload_hash' => hash('sha256', 'payload2'),
        ]);

        $journey = app(CalculateDailyJourneyAction::class)->execute($employee, $date);

        $this->assertNotNull($journey->nightWorkResult);
        $this->assertEquals(25200, $journey->nightWorkResult->physicalNightSeconds);
        $this->assertEquals(28800, $journey->nightWorkResult->legalNightEquivalentSeconds);
        $this->assertEquals(420, $journey->nightWorkedMinutes());
        $this->assertEquals(480, $journey->legalNightEquivalentMinutes());
        $this->assertEquals(60, $journey->nightFictionalBonusMinutes());

        // PunchEvents permanecem imutáveis
        $this->assertEquals(2, PunchEvent::where('employee_id', $employee->id)->count());
    }
}
