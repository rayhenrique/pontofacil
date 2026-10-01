<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ManageTestDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ponto:test-data {--clean : Remove todos os dados temporarios de teste}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera ou limpa dados ficticios de teste (colaborador, gestor, setor e batidas com calculo de horas)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('clean')) {
            return $this->cleanData();
        }

        return $this->seedData();
    }

    private function cleanData(): int
    {
        $this->info('Iniciando limpeza dos dados temporários de teste...');

        // 1. Remover time entries de teste adicionadas ao admin
        $adminPunchesDeleted = TimeEntry::where('accuracy', 9999)->delete();

        // 2. Localizar e remover usuários de teste
        $testEmails = [
            'carlos.teste@pontofacil.local',
            'gestor.teste@pontofacil.local',
        ];

        $users = User::whereIn('email', $testEmails)->get();
        foreach ($users as $user) {
            TimeEntry::where('user_id', $user->id)->delete();
            Employee::where('user_id', $user->id)->delete();
            $user->forceDelete();
        }

        // 3. Remover setor de teste
        Sector::where('description', 'DADOS_TEMPORARIOS_TESTE')->delete();

        $this->info("Limpeza concluída! {$adminPunchesDeleted} batidas avulsas e contas fictícias foram excluídas permanentemente.");

        return 0;
    }

    private function seedData(): int
    {
        $this->info('Criando dados fictícios temporários para teste...');

        // 1. Criar Setor de Teste
        $sector = Sector::updateOrCreate(
            ['name' => 'Tecnologia & Inovação (Teste)'],
            ['description' => 'DADOS_TEMPORARIOS_TESTE']
        );

        // 2. Criar Gestor de Teste
        $manager = User::updateOrCreate(
            ['email' => 'gestor.teste@pontofacil.local'],
            [
                'name' => 'Marcos Gestor (Teste)',
                'password' => Hash::make('password123'),
                'role' => UserRole::Manager,
            ]
        );

        $sector->update(['manager_id' => $manager->id]);

        // 3. Criar Funcionário de Teste
        $employeeUser = User::updateOrCreate(
            ['email' => 'carlos.teste@pontofacil.local'],
            [
                'name' => 'Carlos Silva (Colaborador Teste)',
                'password' => Hash::make('password123'),
                'role' => UserRole::Employee,
            ]
        );

        Employee::updateOrCreate(
            ['user_id' => $employeeUser->id],
            [
                'sector_id' => $sector->id,
                'cpf' => '111.222.333-44',
                'phone' => '(82) 99999-1234',
                'registration_number' => 'TEST-001',
            ]
        );

        // 4. Limpar batidas anteriores deste usuário de teste
        TimeEntry::where('user_id', $employeeUser->id)->delete();

        // Gerar ciclo padrão de 4 batidas por dia em várias datas:
        // Dias: Hoje (01/10/2026), 30/09/2026, 29/09/2026, 28/09/2026
        $daysConfig = [
            // Hoje (Outubro) - 8h cravadas
            [
                'date' => Carbon::now(),
                'punches' => [
                    ['time' => '08:00:00', 'type' => 'in'],
                    ['time' => '12:00:00', 'type' => 'out'],
                    ['time' => '13:00:00', 'type' => 'in'],
                    ['time' => '17:00:00', 'type' => 'out'],
                ],
            ],
            // 30/09/2026 (Setembro) - 8h 15m
            [
                'date' => Carbon::now()->subDay(),
                'punches' => [
                    ['time' => '08:05:00', 'type' => 'in'],
                    ['time' => '12:10:00', 'type' => 'out'],
                    ['time' => '13:10:00', 'type' => 'in'],
                    ['time' => '17:20:00', 'type' => 'out'],
                ],
            ],
            // 29/09/2026 (Setembro) - 9h 00m (com 1h de hora extra)
            [
                'date' => Carbon::now()->subDays(2),
                'punches' => [
                    ['time' => '07:55:00', 'type' => 'in'],
                    ['time' => '12:00:00', 'type' => 'out'],
                    ['time' => '13:05:00', 'type' => 'in'],
                    ['time' => '18:00:00', 'type' => 'out'],
                ],
            ],
            // 28/09/2026 (Setembro) - 8h 00m
            [
                'date' => Carbon::now()->subDays(3),
                'punches' => [
                    ['time' => '08:00:00', 'type' => 'in'],
                    ['time' => '12:00:00', 'type' => 'out'],
                    ['time' => '13:00:00', 'type' => 'in'],
                    ['time' => '17:00:00', 'type' => 'out'],
                ],
            ],
        ];

        foreach ($daysConfig as $day) {
            foreach ($day['punches'] as $punch) {
                [$h, $m, $s] = explode(':', $punch['time']);
                $timestamp = $day['date']->copy()->setTime((int) $h, (int) $m, (int) $s);

                TimeEntry::create([
                    'user_id' => $employeeUser->id,
                    'timestamp' => $timestamp,
                    'type' => $punch['type'],
                    'latitude' => -9.665800,
                    'longitude' => -35.735000,
                    'accuracy' => 10,
                    'is_manual' => false,
                ]);
            }
        }

        // 5. Também criar para o Admin atual (Ray Henrique) as 4 batidas de hoje para ele testar direto na tela dele
        $admin = User::where('role', UserRole::Admin)->first();
        if ($admin) {
            TimeEntry::where('user_id', $admin->id)->where('accuracy', 9999)->delete();

            $todayPunches = [
                ['time' => '08:02:00', 'type' => 'in'],
                ['time' => '12:04:00', 'type' => 'out'],
                ['time' => '13:06:00', 'type' => 'in'],
                ['time' => '17:08:00', 'type' => 'out'],
            ];

            foreach ($todayPunches as $punch) {
                [$h, $m, $s] = explode(':', $punch['time']);
                TimeEntry::create([
                    'user_id' => $admin->id,
                    'timestamp' => Carbon::now()->setTime((int) $h, (int) $m, (int) $s),
                    'type' => $punch['type'],
                    'latitude' => -9.665800,
                    'longitude' => -35.735000,
                    'accuracy' => 9999, // Flag especial para remoção fácil
                    'is_manual' => false,
                ]);
            }
        }

        $this->info('Dados fictícios criados com sucesso!');
        $this->line('');
        $this->line('=== CONTAS DE TESTE CRIADAS ===');
        $this->line('1. Colaborador: carlos.teste@pontofacil.local | Senha: password123 (Com 4 dias de batidas)');
        $this->line('2. Gestor:      gestor.teste@pontofacil.local | Senha: password123 (Responsável pelo setor de teste)');
        $this->line('3. Admin:       Seu usuário já possui 4 batidas lançadas para hoje!');
        $this->line('');
        $this->line('Para remover tudo após o teste, basta rodar:');
        $this->line('php artisan ponto:test-data --clean');

        return 0;
    }
}
