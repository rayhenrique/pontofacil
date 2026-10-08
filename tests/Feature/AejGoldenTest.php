<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AEJ\AejValidator;
use App\Domain\PTRP\Actions\CloseMonthlyPeriodAction;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\TimeBankAccount;
use App\Models\TimeBankTransaction;
use App\Models\TreatmentEvent;
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

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
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

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));

        // Registro 02: REPs utilizados (02|idRepAej|tpRep|numRegRep)
        $repParts = explode('|', $lines[1]);
        $this->assertSame('02', $repParts[0]);
        $this->assertSame('1', $repParts[1]); // idRepAej
        $this->assertSame('3', $repParts[2]); // 3 = REP-P
        $this->assertSame('', $repParts[3]); // Vazio quando pendente INPI (sem texto fictício)

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

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
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
     * Teste de Marcações e Tratamento (Registro 05 Oficial):
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

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
        $punchLines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '05|')));

        $this->assertCount(4, $punchLines);

        // Batida 1: Entrada 08:00 (par 1)
        // 05|idtVinculoAej|dataHoraMarc|idRepAej|tpMarc|seqEntSaida|fonteMarc|codHorContratual|motivo
        $p1 = explode('|', $punchLines[0]);
        $this->assertSame('05', $p1[0]);
        $this->assertSame('1', $p1[1]); // vínculo 1
        $this->assertSame('2026-01-05T08:00:00-0300', $p1[2]);
        $this->assertSame('1', $p1[3]); // idRepAej
        $this->assertSame('E', $p1[4]); // Entrada
        $this->assertSame('1', $p1[5]); // seqEntSaida (par 1)
        $this->assertSame('O', $p1[6]); // Fonte 'O' = Original REP
        $this->assertSame('1', $p1[7]); // codHorContratual na 1ª entrada
        $this->assertSame('', $p1[8]); // motivo vazio

        // Batida 2: Saída 12:00 (par 1)
        $p2 = explode('|', $punchLines[1]);
        $this->assertSame('05', $p2[0]);
        $this->assertSame('S', $p2[4]); // Saída
        $this->assertSame('1', $p2[5]); // seqEntSaida (par 1)
        $this->assertSame('O', $p2[6]);
        $this->assertSame('', $p2[7]);

        // Batida 3: Entrada 13:00 (par 2)
        $p3 = explode('|', $punchLines[2]);
        $this->assertSame('05', $p3[0]);
        $this->assertSame('E', $p3[4]); // Entrada
        $this->assertSame('2', $p3[5]); // seqEntSaida (par 2)
        $this->assertSame('O', $p3[6]);
        $this->assertSame('', $p3[7]);

        // Batida 4: Saída 17:00 (par 2)
        $p4 = explode('|', $punchLines[3]);
        $this->assertSame('05', $p4[0]);
        $this->assertSame('S', $p4[4]); // Saída
        $this->assertSame('2', $p4[5]); // seqEntSaida (par 2)
        $this->assertSame('O', $p4[6]);
        $this->assertSame('', $p4[7]);
    }

    /**
     * Teste de Identificação do Desenvolvedor / PTRP (Registro 08):
     */
    public function test_aej_developer_record_08_structure(): void
    {
        config([
            'compliance.developer.document_type' => '1',
            'compliance.developer.document' => '12345678000199',
            'compliance.developer.name' => 'PontoFacil Tecnologia Ltda',
            'compliance.developer.email' => 'compliance@pontofacil.local',
        ]);

        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: Carbon::parse('2026-02-01 09:30:00', 'America/Maceio'),
            forcePreview: false
        );

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
        $rec08 = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '08|')))[0] ?? '';
        $parts08 = explode('|', $rec08);

        $this->assertSame('08', $parts08[0]);
        $this->assertSame('PontoFacil', $parts08[1]); // nomePrograma
        $this->assertSame('2.5.0', $parts08[2]); // versaoPrograma
        $this->assertSame('1', $parts08[3]); // tpIdDev (1=CNPJ)
        $this->assertSame('12345678000199', $parts08[4]); // numIdDev
        $this->assertSame('PontoFacil Tecnologia Ltda', $parts08[5]); // razaoSocialDev
        $this->assertSame('compliance@pontofacil.local', $parts08[6]); // emailDev
    }

    /**
     * Teste de Trailer, Contadores (Registro 99) e Marcador de Assinatura CAdES:
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

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));

        // Penúltima linha é o Trailer (Tipo 99)
        $trailerIndex = count($lines) - 2;
        $trailerParts = explode('|', $lines[$trailerIndex]);

        $this->assertSame('99', $trailerParts[0]);
        $this->assertSame('1', $trailerParts[1]); // qt01
        $this->assertSame('1', $trailerParts[2]); // qt02
        $this->assertSame('1', $trailerParts[3]); // qt03
        $this->assertSame('1', $trailerParts[4]); // qt04
        $this->assertSame('4', $trailerParts[5]); // qt05
        $this->assertSame('0', $trailerParts[6]); // qt06 (0 para vínculo único)
        $this->assertSame('21', $trailerParts[7]); // qt07 (21 ausências apuradas nos dias úteis sem batida)
        $this->assertSame('1', $trailerParts[8]); // qt08 (PTRP)

        // Última linha é a assinatura externa CAdES (.p7s)
        $sigLine = $lines[count($lines) - 1];
        $this->assertSame(100, strlen($sigLine));
        $this->assertStringStartsWith('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', $sigLine);
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
     * Teste de Relação de Empregados e Matrícula eSocial (Registro 06):
     */
    public function test_aej_record_06_esocial_registration(): void
    {
        $generator = app(AejGenerator_2026_07_31::class);

        // 1. Empregado com vínculo único no AEJ NÃO deve gerar Registro 06
        $resultSingle = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: true
        );
        $linesSingle = explode("\r\n", rtrim($resultSingle->content, "\r\n"));
        $rec06Single = array_values(array_filter($linesSingle, fn ($l) => str_starts_with($l, '06|')));
        $this->assertEmpty($rec06Single, 'Empregado com vínculo único não deve gerar Registro 06.');

        // 2. Empregado com mais de um vínculo (mesmo CPF, dois vínculos/matrículas)
        Employee::create([
            'user_id' => $this->worker->id,
            'sector_id' => $this->employee->sector_id,
            'cpf' => $this->employee->cpf,
            'registration_number' => 'MAT-999',
            'job_title' => 'Instrutora Técnica',
            'work_schedule_id' => $this->employee->work_schedule_id,
        ]);

        $resultMulti = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: true
        );
        $linesMulti = explode("\r\n", rtrim($resultMulti->content, "\r\n"));
        $rec06Multi = array_values(array_filter($linesMulti, fn ($l) => str_starts_with($l, '06|')));

        $this->assertCount(2, $rec06Multi, 'Empregado com dois vínculos deve gerar dois Registros 06.');
        $this->assertSame('06|1|MAT-202', $rec06Multi[0]);
        $this->assertSame('06|2|MAT-999', $rec06Multi[1]);
    }

    /**
     * Teste de Inclusão Manual no Registro 05 (fonteMarc = 'I' com motivo obrigatório):
     */
    public function test_aej_record_05_manual_punch_source_i_with_reason(): void
    {
        // Registrar batida manual aprovada no PTRP
        TreatmentEvent::create([
            'employee_id' => $this->employee->id,
            'type' => TreatmentEventType::ManualPunchAdded,
            'status' => TreatmentEventStatus::Approved,
            'effective_at' => Carbon::parse('2026-01-06 08:00:00', 'America/Maceio'),
            'reason_text' => 'Esquecimento de cracha',
            'requested_by' => $this->worker->id,
            'approved_by' => $this->admin->id,
            'decided_at' => Carbon::parse('2026-01-06 08:30:00'),
        ]);

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: true
        );

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
        $manualPunchLines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '05|') && str_contains($l, '|I|')));

        $this->assertCount(1, $manualPunchLines);
        $p = explode('|', $manualPunchLines[0]);

        $this->assertSame('05', $p[0]);
        $this->assertSame('1', $p[1]); // vínculo
        $this->assertSame('2026-01-06T08:00:00-0300', $p[2]); // dataHoraMarc
        $this->assertSame('1', $p[3]); // idRepAej
        $this->assertSame('E', $p[4]); // tipo
        $this->assertSame('1', $p[5]); // seqEntSaida
        $this->assertSame('I', $p[6]); // fonteMarc = 'I' (incluída manualmente)
        $this->assertSame('1', $p[7]); // codHorContratual na primeira entrada
        $this->assertSame('Esquecimento de cracha', $p[8]); // motivo obrigatório

        // Validação formal com AejValidator
        $validator = app(AejValidator::class);
        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros de validação do AEJ com batida manual: '.implode('; ', $validation['errors']));
    }

    /**
     * Teste de Banco de Horas no Registro 07 (movimentações reais crédito e débito):
     */
    public function test_aej_record_07_time_bank_movements(): void
    {
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);

        // Crédito de 120 minutos (+2h)
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::OvertimeCredit,
            'minutes' => 120,
            'reference_date' => '2026-01-10',
            'description' => 'Horas extras de sabado',
            'created_by' => $this->admin->id,
        ]);

        // Débito/Compensação de 60 minutos (-1h)
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::CompensationDebit,
            'minutes' => -60,
            'reference_date' => '2026-01-15',
            'description' => 'Compensacao de folga parcial',
            'created_by' => $this->admin->id,
        ]);

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: true
        );

        $lines = explode("\r\n", rtrim($result->content, "\r\n"));
        $tbLines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '07|') && str_contains($l, '|3|')));

        $this->assertCount(2, $tbLines);

        // Crédito: 07|idtVinculoAej|3|data|120|1
        $credit = explode('|', $tbLines[0]);
        $this->assertSame('07', $credit[0]);
        $this->assertSame('1', $credit[1]);
        $this->assertSame('3', $credit[2]); // 3 = banco de horas
        $this->assertSame('2026-01-10', $credit[3]);
        $this->assertSame('120', $credit[4]); // qtMinutos
        $this->assertSame('1', $credit[5]); // tipoMovBH = '1' (crédito)

        // Débito: 07|idtVinculoAej|3|data|60|2
        $debit = explode('|', $tbLines[1]);
        $this->assertSame('07', $debit[0]);
        $this->assertSame('1', $debit[1]);
        $this->assertSame('3', $debit[2]); // 3 = banco de horas
        $this->assertSame('2026-01-15', $debit[3]);
        $this->assertSame('60', $debit[4]); // qtMinutos positivo
        $this->assertSame('2', $debit[5]); // tipoMovBH = '2' (compensação/débito)

        $validator = app(AejValidator::class);
        $validation = $validator->validate($result->content);
        $this->assertTrue($validation['is_valid'], 'Erros de validação do AEJ com movimentações de banco de horas: '.implode('; ', $validation['errors']));
    }

    /**
     * Teste de Registro 02 com INPI Homologado vs Pendente:
     */
    public function test_aej_record_02_with_inpi_homologated_and_pending(): void
    {
        $generator = app(AejGenerator_2026_07_31::class);

        // 1. Cenário Pendente: sem texto fictício, campo 4 vazio
        $resultPending = $generator->generate($this->establishment, 2026, 1, forcePreview: true);
        $linesPending = explode("\r\n", rtrim($resultPending->content, "\r\n"));
        $rec02Pending = explode('|', $linesPending[1]);
        $this->assertSame('02', $rec02Pending[0]);
        $this->assertSame('1', $rec02Pending[1]);
        $this->assertSame('3', $rec02Pending[2]);
        $this->assertSame('', $rec02Pending[3]);

        // 2. Cenário Homologado com número de registro INPI real
        $this->company->update([
            'inpi_registration_status' => 'registered',
            'inpi_registration_number' => 'BR5120260000010',
        ]);
        $this->establishment->refresh();

        $resultRegistered = $generator->generate($this->establishment, 2026, 1, forcePreview: true);
        $linesRegistered = explode("\r\n", rtrim($resultRegistered->content, "\r\n"));
        $rec02Registered = explode('|', $linesRegistered[1]);
        $this->assertSame('02', $rec02Registered[0]);
        $this->assertSame('1', $rec02Registered[1]);
        $this->assertSame('3', $rec02Registered[2]);
        $this->assertSame('BR5120260000010', $rec02Registered[3]);
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
        $resBadTrailer = $validator->validate("01|1|12345678000199|||EMPRESA|2026-01-01|2026-01-31|2026-02-01T09:30:00-0300|002\r\n99|1|5|0|0|0|0|0|0");
        $this->assertFalse($resBadTrailer['is_valid']);
        $this->assertStringContainsString('Trailer indica 5 registros Tipo 02', implode('; ', $resBadTrailer['errors']));
    }

    /**
     * Teste Crítico: Período fechado deve ser 100% imutável no Registro 07.
     * Alterações ou inserções posteriores em TimeBankTransaction não afetam o AEJ do período já fechado.
     */
    public function test_closed_period_time_bank_record_07_is_strictly_immutable_after_closing(): void
    {
        // 1. Fechar Janeiro
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $fixedGenTime = Carbon::parse('2026-02-01 09:30:00', 'America/Maceio');

        // 2. Gerar AEJ A
        $resultA = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: $fixedGenTime,
            forcePreview: false
        );

        // 3. Alterar / criar transação de banco depois do fechamento
        $account = TimeBankAccount::getOrCreateForEmployee($this->employee);
        TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => TimeBankTransactionType::ManualCredit,
            'minutes' => 999, // Inserção espúria posterior
            'balance_after' => 999,
            'reference_date' => '2026-01-15',
            'description' => 'Lancamento indevido pos fechamento',
            'created_by' => $this->admin->id,
        ]);

        // 4. Gerar novamente Janeiro
        $resultB = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            generationTime: $fixedGenTime,
            forcePreview: false
        );

        // 5. Conteúdo deve ser byte a byte idêntico ao AEJ A
        $this->assertSame(
            $resultA->content,
            $resultB->content,
            'O AEJ de competência fechada deve ser 100% imutável, gerado exclusivamente a partir dos snapshots congelados.'
        );
        $this->assertStringNotContainsString('999', $resultB->content);
    }

    /**
     * Teste de INPI pendente com fonteMarc = 'O':
     * O arquivo deve ser gerado sem bloquear (structurally_valid = true),
     * mas não é homologado fiscalmente (is_homologated = false, reason = pending_inpi).
     */
    public function test_aej_inpi_pending_is_structurally_valid_but_not_homologated(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: true
        );

        $this->assertFalse($result->isHomologated);
        $this->assertSame('pending_inpi', $result->homologationReason);

        $validator = app(AejValidator::class);
        $val = $validator->validate($result->content);

        $this->assertTrue($val['structurally_valid']);
        $this->assertFalse($val['is_homologated']);
        $this->assertSame('pending_inpi', $val['homologation_reason']);
    }

    /**
     * Teste de Certificado Pendente no AEJ:
     * Com INPI e desenvolvedor configurados, mas sem assinatura .p7s real,
     * o AEJ é válido estruturalmente, mas NUNCA homologado.
     */
    public function test_aej_pending_certificate_is_structurally_valid_but_not_homologated(): void
    {
        config([
            'compliance.developer.document' => '12345678000199',
            'compliance.developer.name' => 'Desenvolvedor PontoFacil',
            'compliance.developer.email' => 'dev@pontofacil.local',
        ]);

        $this->company->update([
            'inpi_registration_status' => 'registered',
            'inpi_registration_number' => 'BR5120260000000',
        ]);
        $this->establishment->refresh();

        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: false
        );

        $this->assertTrue($result->structureValid);
        $this->assertFalse($result->signatureValid);
        $this->assertSame('pending_certificate', $result->signatureStatus);
        $this->assertFalse($result->isHomologated);
        $this->assertSame('pending_certificate', $result->homologationReason);

        $validator = app(AejValidator::class);
        $val = $validator->validate($result->content);

        $this->assertTrue($val['structureValid']);
        $this->assertFalse($val['signatureValid']);
        $this->assertSame('pending_certificate', $val['signatureStatus']);
        $this->assertFalse($val['isHomologated']);
        $this->assertSame('pending_certificate', $val['homologationReason']);
    }

    /**
     * Teste do Marcador de Texto no AEJ:
     * O marcador "ASSINATURA_DIGITAL_EM_ARQUIVO_P7S" não representa assinatura real.
     */
    public function test_aej_marker_does_not_imply_signature_validity(): void
    {
        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: true
        );

        $this->assertStringContainsString('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', $result->content);

        $validator = app(AejValidator::class);
        $val = $validator->validate($result->content);

        $this->assertTrue($val['structureValid']);
        $this->assertFalse($val['signatureValid']);
        $this->assertFalse($val['isHomologated']);
    }

    /**
     * Teste de Assinatura Futura Válida no AEJ:
     * Quando fornecida assinatura CAdES (.p7s) com INPI e desenvolvedor completos,
     * torna-se homologado (isHomologated = true, signatureValid = true).
     */
    public function test_aej_future_valid_cades_signature_becomes_homologated(): void
    {
        config([
            'compliance.developer.document' => '12345678000199',
            'compliance.developer.name' => 'Desenvolvedor PontoFacil',
            'compliance.developer.email' => 'dev@pontofacil.local',
        ]);

        $this->company->update([
            'inpi_registration_status' => 'registered',
            'inpi_registration_number' => 'BR5120260000000',
        ]);
        $this->establishment->refresh();

        $this->createClosedPeriodWithPunches();

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $this->establishment,
            year: 2026,
            month: 1,
            forcePreview: false
        );

        $validator = app(AejValidator::class);
        $mockP7s = 'VALID_CADES_P7S_SIGNATURE_BINARY_MOCK_ICP_BRASIL';
        $val = $validator->validate($result->content, $mockP7s);

        $this->assertTrue($val['structureValid']);
        $this->assertTrue($val['signatureValid']);
        $this->assertSame('signed', $val['signatureStatus']);
        $this->assertTrue($val['isHomologated']);
        $this->assertNull($val['homologationReason']);
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
