<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ponto:admin:create
                            {--email= : O e-mail institucional do administrador}
                            {--name= : O nome do administrador}
                            {--password= : A senha inicial (atenção ao histórico de comandos shell)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria uma conta de Administrador do Sistema de forma segura sem expor senhas em logs ou terminal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Provisionamento Administrativo Seguro — PontoFácil');

        $email = $this->option('email');
        if (! $email) {
            $email = $this->ask('E-mail institucional do administrador');
        }

        $email = trim((string) $email);

        $validator = Validator::make(
            ['email' => $email],
            ['email' => 'required|email|max:255']
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->components->error("Já existe um usuário cadastrado com o e-mail [{$email}]. Operação cancelada para evitar sobrescrita indevida.");

            return self::FAILURE;
        }

        $name = $this->option('name');
        if (! $name) {
            $name = $this->ask('Nome completo do administrador', 'Administrador');
        }
        $name = trim((string) $name);

        $password = $this->option('password');
        if (! $password) {
            $password = $this->secret('Senha do administrador (mínimo de 10 caracteres, caracteres ocultos)');
            $confirmation = $this->secret('Confirmação da senha');

            if ($password !== $confirmation) {
                $this->components->error('A confirmação da senha não confere.');

                return self::FAILURE;
            }
        }

        $isProduction = app()->environment('production');
        $minChars = $isProduction ? 12 : 8;

        if (strlen((string) $password) < $minChars) {
            $this->components->error("A senha deve conter no mínimo {$minChars} caracteres".($isProduction ? ' em ambiente de produção.' : '.'));

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
        ]);

        $this->components->success("Administrador [{$user->email}] criado com sucesso com privilégios de Administrador/RH.");

        return self::SUCCESS;
    }
}
