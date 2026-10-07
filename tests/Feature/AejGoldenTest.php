<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AEJ\AejValidator;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes Oficiais de Golden Fixture do Arquivo Eletrônico de Jornada (AEJ)
 * do Programa de Tratamento de Registro de Ponto (PTRP) - Portaria 671/2021 MTP (Anexo VI vigente 31/07/2026).
 *
 * REGRA INVIOLÁVEL:
 * NENHUM teste nesta suíte deve criar, atualizar ou sobrescrever automaticamente os arquivos de fixture.
 * As fixtures em tests/Fixtures/AEJ são arquivos de referência estritamente somente-leitura.
 * Se uma fixture não existir ou for corrompida, o teste DEVE falhar.
 * Qualquer atualização de fixture deve ocorrer manualmente e de forma consciente pelo desenvolvedor/auditor.
 */
class AejGoldenTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Establishment $establishment;

    protected User $admin;

    protected User $worker;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();

        $this->company = Company::create([
            'legal_name' => 'EMPRESA FISCAL MODELO LTDA',
            'trade_name' => 'Fiscal Modelo',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
            'inpi_registration_status' => 'pending_registration',
        ]);

        $this->establishment = Establishment::create([
            'company_id' => $this->company->id,
            'code' => 'SEDE',
            'name' => 'Sede Maceio',
            'identifier_type' => 'cnpj',
            'identifier_number' => '12345678000199',
            'city' => 'Maceio',
            'state' => 'AL',
            'timezone' => 'America/Maceio',
            'nsr_next' => 1,
        ]);

        $sector = Sector::create([
            'name' => 'Operacoes',
            'establishment_id' => $this->establishment->id,
        ]);

        $this->admin = User::create([
            'name' => 'Auditor Admin',
            'email' => 'admin@modelo.local',
            'password' => 'secret123',
            'role' => UserRole::Admin,
        ]);

        $this->worker = User::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria@modelo.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $schedule = WorkSchedule::createDefault40h();

        $this->employee = Employee::create([
            'user_id' => $this->worker->id,
            'sector_id' => $sector->id,
            'work_schedule_id' => $schedule->id,
            'registration_number' => 'MAT-202',
            'cpf' => '98765432100',
            'job_title' => 'Assistente Administrativo',
        ]);
    }

    /**
     * Teste de Período Fechado:
     * O AEJ definitivo só pode ser emitido para competência formalmente fechada.
     */
    public function test_aej_official_requires_closed_period_unless_preview(): void
    {
        $generator = app(AejGenerator_2026_07_31::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ainda não foi fechada. Para emitir o AEJ fiscal definitivo, feche a competência formalmente.');

        $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: false
        );
    }

    /**
     * Teste de Modo Prévia:
     * Permite emissão em aberto com indicador claro de prévia.
     */
    public function test_aej_preview_mode_generates_valid_content_marked_as_preview(): void
    {
        $generator = app(AejGenerator_2026_07_31::class);
        $validator = app(AejValidator::class);

        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 10:00:00'),
            forcePreview: true
        );

        $this->assertTrue($result->isPreview);
        $this->assertStringContainsString('PREVIA-NAO-FECHADA', $result->content);
        $this->assertStringStartsWith('AEJ_PREVIA_', $result->filename);

        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros de validação do AEJ Prévia: '.implode('; ', $validation['errors']));
    }

    /**
     * Teste Byte a Byte contra Golden Fixture oficial congelada.
     */
    public function test_aej_official_generation_matches_golden_fixture_byte_by_byte(): void
    {
        $fixturePath = base_path('tests/Fixtures/AEJ/golden_aej_mte_2026.txt');

        // O teste deve falhar se a fixture de referência não existir
        $this->assertFileExists(
            $fixturePath,
            "A fixture oficial {$fixturePath} não foi encontrada. O teste jamais deve criar ou sobrescrever fixtures automaticamente."
        );

        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $fixedGenTime = Carbon::parse('2026-02-01 09:30:00', 'America/Maceio');

        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: $fixedGenTime,
            forcePreview: false
        );

        $this->assertFalse($result->isPreview);
        $this->assertStringStartsWith('AEJ_12345678000199_202601.txt', $result->filename);

        // Validação formal com AejValidator
        $validator = app(AejValidator::class);
        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros de validação estrutural do AEJ: '.implode('; ', $validation['errors']));

        // Comparação estrita byte a byte contra a fixture versionada
        $this->assertStringEqualsFile(
            $fixturePath,
            $result->content,
            'O AEJ gerado difere da fixture oficial do MTE. As fixtures são somente leitura e não devem ser alteradas automaticamente.'
        );
    }

    /**
     * Teste de Cabeçalho (Registro 01):
     * Valida campos obrigatórios: tipoReg, tpIdt, CNPJ, Razão Social, datas ISO, versão.
     */
    public function test_aej_header_structure_and_mandatory_fields(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00', 'America/Maceio'),
            forcePreview: false
        );

        $lines = explode("\r\n", trim($result->content));
        $headerParts = explode('|', $lines[0]);

        $this->assertSame('01', $headerParts[0]);
        $this->assertSame('1', $headerParts[1]); // 1 = CNPJ
        $this->assertSame('12345678000199', $headerParts[2]);
        $this->assertSame('EMPRESA FISCAL MODELO LTDA', $headerParts[5]);
        $this->assertSame('2026-01-01', $headerParts[6]);
        $this->assertSame('2026-01-31', $headerParts[7]);
        $this->assertSame('2026-02-01T09:30:00-0300', $headerParts[8]);
        $this->assertSame('001', $headerParts[9]);
    }

    /**
     * Teste de Empregados e REPs (Registros 02 e 03):
     */
    public function test_aej_rep_and_employee_record_structure(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00', 'America/Maceio'),
            forcePreview: false
        );

        $lines = explode("\r\n", trim($result->content));

        // Registro 02: REPs utilizados
        $repParts = explode('|', $lines[1]);
        $this->assertSame('02', $repParts[0]);
        $this->assertSame('1', $repParts[1]); // idRepAej
        $this->assertSame('3', $repParts[2]); // 3 = REP-P
        $this->assertSame('PENDENTE REGISTRO', $repParts[3]);

        // Registro 03: Vínculos
        $empParts = explode('|', $lines[2]);
        $this->assertSame('03', $empParts[0]);
        $this->assertSame('1', $empParts[1]); // idtVinculoAej
        $this->assertSame('98765432100', $empParts[2]); // CPF
        $this->assertSame('Maria Oliveira', $empParts[3]);
    }

    /**
     * Teste de Jornada e Horários Contratuais (Registro 04):
     */
    public function test_aej_schedule_and_journey_records(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00', 'America/Maceio'),
            forcePreview: false
        );

        $lines = explode("\r\n", trim($result->content));
        $schedParts = explode('|', $lines[3]);

        $this->assertSame('04', $schedParts[0]);
        $this->assertSame('1', $schedParts[1]); // codHorContratual
        $this->assertSame('480', $schedParts[2]); // durJornada em minutos (8h)
        $this->assertSame('0800', $schedParts[3]); // Entrada 1
        $this->assertSame('1200', $schedParts[4]); // Saída 1
        $this->assertSame('1300', $schedParts[5]); // Entrada 2
        $this->assertSame('1700', $schedParts[6]); // Saída 2
    }

    /**
     * Teste de Marcações e Tratamento (Registro 05):
     */
    public function test_aej_punches_and_treatments_records(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00', 'America/Maceio'),
            forcePreview: false
        );

        $lines = explode("\r\n", trim($result->content));
        $punchLines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '05|')));

        $this->assertCount(4, $punchLines);

        // Batida 1: Entrada 08:00
        $p1 = explode('|', $punchLines[0]);
        $this->assertSame('05', $p1[0]);
        $this->assertSame('1', $p1[1]); // vínculo 1
        $this->assertSame('2026-01-05T08:00:00-0300', $p1[2]);
        $this->assertSame('3', $p1[3]); // NSR (1=establishment, 2=employee, 3..6=punches)
        $this->assertSame('1', $p1[4]); // REP
        $this->assertSame('E', $p1[5]); // Entrada
        $this->assertSame('1', $p1[6]); // Fonte 1 = Original REP

        // Batida 2: Saída 12:00
        $p2 = explode('|', $punchLines[1]);
        $this->assertSame('S', $p2[5]);

        // Batida 3: Entrada 13:00
        $p3 = explode('|', $punchLines[2]);
        $this->assertSame('E', $p3[5]);

        // Batida 4: Saída 17:00
        $p4 = explode('|', $punchLines[3]);
        $this->assertSame('S', $p4[5]);
    }

    /**
     * Teste de Trailer e Contadores (Registro 99):
     */
    public function test_aej_trailer_and_record_counters(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00', 'America/Maceio'),
            forcePreview: false
        );

        $lines = explode("\r\n", trim($result->content));
        $trailerParts = explode('|', end($lines));

        $this->assertSame('99', $trailerParts[0]);
        $this->assertSame('1', $trailerParts[1]); // qt01
        $this->assertSame('1', $trailerParts[2]); // qt02
        $this->assertSame('1', $trailerParts[3]); // qt03
        $this->assertSame('1', $trailerParts[4]); // qt04
        $this->assertSame('4', $trailerParts[5]); // qt05
        $this->assertSame('1', $trailerParts[6]); // qt06 (matrícula)
        $this->assertSame('21', $trailerParts[7]); // qt07 (21 ausências apuradas nos dias úteis sem batida)
        $this->assertSame('1', $trailerParts[8]); // qt08 (PTRP)
    }

    /**
     * Teste de Sanitização e Caracteres Especiais:
     * Garante que caracteres especiais e pipes nos dados do empregado ou empresa não corrompam os campos.
     */
    public function test_aej_sanitization_and_special_characters(): void
    {
        $this->employee->update([
            'job_title' => 'Coordenação & Operações | Nível 2',
        ]);
        $this->worker->update([
            'name' => 'João Gonçalves d’Ávila | Silva',
        ]);

        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: true
        );

        $validator = app(AejValidator::class);
        $validation = $validator->validate($result->content);

        $this->assertTrue($validation['is_valid'], 'Pipes acidentais não devem corromper a estrutura do AEJ: '.implode('; ', $validation['errors']));
    }

    /**
     * Teste de Validação de Campos Obrigatórios e Malformação.
     */
    public function test_aej_validator_detects_malformed_records(): void
    {
        $validator = app(AejValidator::class);

        // Arquivo vazio
        $resEmpty = $validator->validate('');
        $this->assertFalse($resEmpty['is_valid']);

        // Arquivo com cabeçalho incorreto (sem tipo 01)
        $resNoHeader = $validator->validate("02|1|3|INPI\r\n99|0|1|0|0|0|0|0|0");
        $this->assertFalse($resNoHeader['is_valid']);
        $this->assertStringContainsString('Tipo 01', $resNoHeader['errors'][0]);

        // Arquivo com trailer com contadores divergentes
        $resBadTrailer = $validator->validate("01|1|12345678000199|||EMPRESA|2026-01-01|2026-01-31|2026-02-01T09:30:00-0300|001\r\n99|1|5|0|0|0|0|0|0");
        $this->assertFalse($resBadTrailer['is_valid']);
        $this->assertStringContainsString('Trailer indica 5 registros Tipo 02', implode('; ', $resBadTrailer['errors']));
    }

    /**
     * Cria e fecha formalmente a competência Janeiro/2026 com marcações determinísticas.
     */
    protected function createClosedPeriodWithPunches(): void
    {
        $punchAction = app(RecordPunchEventAction::class);

        Carbon::setTestNow(Carbon::parse('2026-01-05 08:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'in', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-01-05 13:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'in', establishment: $this->establishment);

        Carbon::setTestNow(Carbon::parse('2026-01-05 17:00:00', 'America/Maceio'));
        $punchAction->execute(user: $this->worker, direction: 'out', establishment: $this->establishment);

        Carbon::setTestNow();

        app(CloseMonthlyPeriodAction::class)->execute(
            year: 2026,
            month: 1,
            closedBy: $this->admin
        );
    }
}
