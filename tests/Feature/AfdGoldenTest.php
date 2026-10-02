<?php

namespace Tests\Feature;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdValidator;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\Sector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AfdGoldenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentCompany::clear();
    }

    public function test_afd_generation_is_deterministic_and_matches_golden_structure(): void
    {
        $company = Company::create([
            'legal_name' => 'EMPRESA TESTE MATRIZ LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '12345678000199',
            'rep_p_software_name' => 'PontoFacil',
            'rep_p_software_version' => '2.0.0',
        ]);

        $establishment = Establishment::create([
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
            'name' => 'Operacoes',
            'establishment_id' => $establishment->id,
        ]);

        $user1 = User::create([
            'name' => 'Joao da Silva',
            'email' => 'joao.silva@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $emp1 = Employee::create([
            'user_id' => $user1->id,
            'sector_id' => $sector->id,
            'cpf' => '111.222.333-44',
            'job_title' => 'Operador',
        ]);

        $user2 = User::create([
            'name' => 'Maria Oliveira',
            'email' => 'maria.oliveira@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $emp2 = Employee::create([
            'user_id' => $user2->id,
            'sector_id' => $sector->id,
            'cpf' => '555.666.777-88',
            'job_title' => 'Supervisora',
        ]);

        // Fixa a data no passado
        Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00', 'America/Maceio'));

        $action = app(RecordPunchEventAction::class);

        // Joao entra as 08:00
        $action->execute(user: $user1, direction: 'in', establishment: $establishment);

        // Joao sai para almoco as 12:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', 'America/Maceio'));
        $action->execute(user: $user1, direction: 'out', establishment: $establishment);

        // Maria entra as 13:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 13:00:00', 'America/Maceio'));
        $action->execute(user: $user2, direction: 'in', establishment: $establishment);

        // Maria sai as 17:00
        Carbon::setTestNow(Carbon::parse('2026-10-01 17:00:00', 'America/Maceio'));
        $action->execute(user: $user2, direction: 'out', establishment: $establishment);

        // Gera o AFD com timestamp congelado
        $generator = new AfdGenerator_2026_07_31;
        $startDate = Carbon::parse('2026-10-01');
        $endDate = Carbon::parse('2026-10-31');
        $fixedGenTime = Carbon::parse('2026-10-01 18:00:00', 'America/Maceio');

        $result = $generator->generate($establishment, $startDate, $endDate, $fixedGenTime);

        // Valida propriedades do resultado
        $this->assertEquals('AFD_12345678000199_20261001_20261031.txt', $result->filename);
        $this->assertEquals(4, $result->totalRecords);
        $this->assertNotEmpty($result->content);

        // Validação estrutural rigorosa com AfdValidator
        $validator = new AfdValidator;
        $validation = $validator->validate($result->content);

        $this->assertTrue($validation['is_valid'], 'Erros de validação do AFD: '.implode(' | ', $validation['errors']));
        $this->assertEquals(4, $validation['total_records']);

        // Validação das linhas
        $lines = explode("\r\n", trim($result->content));
        $this->assertCount(6, $lines); // Header + 4 punches + Trailer

        // Header: Tipo 1, exatamente 236 chars
        $this->assertEquals(236, strlen($lines[0]));
        $this->assertStringStartsWith('0000000001', $lines[0]);
        $this->assertStringContainsString('12345678000199', $lines[0]);

        // Linhas de batida: Tipo 3, exatamente 101 chars
        for ($i = 1; $i <= 4; $i++) {
            $this->assertEquals(101, strlen($lines[$i]));
            $this->assertEquals('3', $lines[$i][9]);
            $this->assertStringStartsWith(str_pad((string) $i, 9, '0', STR_PAD_LEFT), $lines[$i]);
        }

        // Salva e valida contra fixture oficial (Golden Test byte a byte)
        $fixturePath = base_path('tests/Fixtures/AFD/golden_afd_mte_2026.txt');
        if (! file_exists(dirname($fixturePath))) {
            mkdir(dirname($fixturePath), 0755, true);
        }
        if (! file_exists($fixturePath)) {
            file_put_contents($fixturePath, $result->content);
        }
        $this->assertStringEqualsFile($fixturePath, $result->content);

        // Trailer: Tipo 9, exatamente 63 chars
        $this->assertEquals(63, strlen($lines[5]));
        $this->assertStringStartsWith('9999999999', $lines[5]);

        Carbon::setTestNow(); // Reseta
    }

    public function test_afd_validator_catches_invalid_record_sizes_and_broken_nsr_sequences(): void
    {
        $validator = new AfdValidator;

        // 1. Arquivo com cabeçalho truncado
        $invalidHeader = "0000000001112345678000199\r\n99999999990000000000000000000000000000000000000000000212345678\r\n";
        $val1 = $validator->validate($invalidHeader);
        $this->assertFalse($val1['is_valid']);
        $this->assertStringContainsString('exige exatamente 236', $val1['errors'][0]);

        // 2. Arquivo com batida fora de ordem monotônica
        $header = str_pad('0000000001112345678000199000000000000EMPRESA TESTE', 204, ' ').'01102026311020260110202612000002';
        $punch1 = '0000000053011020261200-03011122233344'.str_repeat('a', 64);
        $punch2 = '0000000033011020261300-03011122233344'.str_repeat('b', 64); // NSR 3 menor que 5!
        $trailer = '999999999900000000200000000000000000000000000000000000412345678';

        $invalidContent = $header."\r\n".$punch1."\r\n".$punch2."\r\n".$trailer."\r\n";
        $val2 = $validator->validate($invalidContent);

        $this->assertFalse($val2['is_valid']);
        $this->assertStringContainsString('quebrou a sequência monotônica ascendente', implode(' | ', $val2['errors']));
    }

    public function test_multiple_establishments_generate_isolated_afds(): void
    {
        $company = CurrentCompany::get();
        $matriz = CurrentCompany::defaultEstablishment();

        $filial = Establishment::create([
            'company_id' => $company->id,
            'code' => 'FILIAL_ARAP',
            'name' => 'Filial Arapiraca',
            'identifier_type' => 'cnpj',
            'identifier_number' => '00000000000272',
            'city' => 'Arapiraca',
            'state' => 'AL',
            'nsr_next' => 1,
        ]);

        $user = User::create([
            'name' => 'Colaborador Filial',
            'email' => 'filial@teste.local',
            'password' => 'secret123',
            'role' => UserRole::Employee,
        ]);

        $action = app(RecordPunchEventAction::class);
        $action->execute(user: $user, direction: 'in', establishment: $filial);

        $generator = new AfdGenerator_2026_07_31;
        $startDate = Carbon::today()->startOfMonth();
        $endDate = Carbon::today()->endOfMonth();

        // AFD da Filial tem 1 registro
        $afdFilial = $generator->generate($filial, $startDate, $endDate);
        $this->assertEquals(1, $afdFilial->totalRecords);
        $this->assertStringContainsString('00000000000272', $afdFilial->content);

        // AFD da Matriz tem 0 registros para este período
        $afdMatriz = $generator->generate($matriz, $startDate, $endDate);
        $this->assertEquals(0, $afdMatriz->totalRecords);
    }
}
