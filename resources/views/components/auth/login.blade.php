<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Entrar no Sistema • PontoFácil')] class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login()
    {
        $credentials = $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();
            return redirect()->intended(route('home'));
        }

        $this->addError('email', 'As credenciais fornecidas estão incorretas. Verifique seu e-mail e senha.');
    }
};
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50/80 via-white to-indigo-100/50 py-8 sm:py-16 px-4 sm:px-6 lg:px-8 relative overflow-hidden" x-data="{ showPassword: false }">
    <!-- Efeitos decorativos de fundo suaves -->
    <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-indigo-200/30 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-purple-200/30 blur-3xl pointer-events-none"></div>

    <div class="max-w-md w-full space-y-6 bg-white/95 backdrop-blur-xs p-6 sm:p-10 rounded-3xl shadow-2xl shadow-indigo-950/5 border border-indigo-50/80 relative z-10">
        <!-- Logo e Cabeçalho -->
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-700 to-indigo-500 text-white shadow-lg shadow-indigo-500/25 mb-3.5 transform hover:scale-105 transition duration-200">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">
                PontoFácil
            </h1>
            
            <p class="mt-1.5 text-xs sm:text-sm text-gray-500 font-medium">
                Ponto Eletrônico Inteligente (Portaria 671)
            </p>
        </div>

        <form wire:submit="login" class="mt-6 space-y-4">
            <!-- Campo E-mail -->
            <div>
                <label for="email" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                    E-mail Corporativo
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <input wire:model="email" 
                           id="email" 
                           name="email" 
                           type="email" 
                           autocomplete="email" 
                           required 
                           class="block w-full pl-10 pr-4 py-3 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 bg-white transition shadow-2xs" 
                           placeholder="ex: seu.email@empresa.com">
                </div>
            </div>

            <!-- Campo Senha com Toggle Ver/Ocultar -->
            <div>
                <label for="password" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                    Senha de Acesso
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <input wire:model="password" 
                           id="password" 
                           name="password" 
                           :type="showPassword ? 'text' : 'password'" 
                           autocomplete="current-password" 
                           required 
                           class="block w-full pl-10 pr-11 py-3 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 bg-white transition shadow-2xs" 
                           placeholder="••••••••">
                    <button type="button" 
                            @click="showPassword = !showPassword" 
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 focus:outline-hidden"
                            :title="showPassword ? 'Ocultar senha' : 'Ver senha'">
                        <template x-if="!showPassword">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </template>
                        <template x-if="showPassword">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </template>
                    </button>
                </div>
            </div>

            <!-- Feedback de Erro -->
            @error('email') 
                <div class="rounded-xl bg-red-50 p-3 border border-red-200 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <p class="text-xs text-red-700 font-semibold">{{ $message }}</p>
                </div>
            @enderror

            <!-- Lembrar de mim -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center cursor-pointer select-none text-xs sm:text-sm text-gray-700 font-medium">
                    <input wire:model="remember" id="remember-me" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded-md transition">
                    <span class="ml-2">Manter conectado</span>
                </label>
            </div>

            <!-- Botão de Login -->
            <div class="pt-2">
                <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 border border-transparent text-sm sm:text-base font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 shadow-md shadow-indigo-600/25 transition duration-150 touch-manipulation focus:outline-hidden">
                    <span wire:loading.remove wire:target="login">Entrar no Sistema</span>
                    <span wire:loading wire:target="login" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Autenticando...
                    </span>
                </button>
            </div>
        </form>

        <!-- Link para Landing Page -->
        <div class="text-center pt-1">
            <a href="{{ route('landing') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold inline-flex items-center gap-1.5 hover:underline">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>Conheça a plataforma PontoFácil</span>
            </a>
        </div>

        <!-- Rodapé Institucional com Link da KL Tecnologia -->
        <div class="pt-6 border-t border-gray-100 text-center space-y-1.5">
            <p class="text-xs text-gray-500">
                PontoFácil • Desenvolvido por 
                <a href="https://kltecnologia.com" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="text-indigo-600 hover:text-indigo-800 font-bold transition hover:underline inline-flex items-center gap-1 group">
                    <span>KL Tecnologia</span>
                    <svg class="w-3 h-3 text-indigo-400 group-hover:text-indigo-600 transition" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </a>
            </p>
            <p class="text-[11px] text-gray-400">
                Sistema com arquitetura preparada para a Portaria 671 (REP-P + PTRP)
            </p>
        </div>
    </div>
</div>