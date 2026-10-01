<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Entrar no Sistema')] class extends Component
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
            return redirect()->intended('/');
        }

        $this->addError('email', 'As credenciais fornecidas estão incorretas.');
    }
};
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-indigo-50 to-gray-100 py-6 sm:py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6 bg-white p-6 sm:p-10 rounded-3xl shadow-xl border border-gray-100">
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white shadow-lg mb-3">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                PontoFácil
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Acesse sua conta para bater o ponto
            </p>
        </div>

        <form wire:submit="login" class="mt-6 space-y-4">
            <div>
                <label for="email" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">E-mail corporativo</label>
                <input wire:model="email" id="email" name="email" type="email" autocomplete="email" required class="block w-full px-4 py-3 text-base sm:text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 bg-white" placeholder="ex: seu.email@empresa.com">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Senha de acesso</label>
                <input wire:model="password" id="password" name="password" type="password" autocomplete="current-password" required class="block w-full px-4 py-3 text-base sm:text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 bg-white" placeholder="••••••••">
            </div>

            @error('email') 
                <div class="rounded-xl bg-red-50 p-3 border border-red-200">
                    <p class="text-xs text-red-700 font-semibold text-center">{{ $message }}</p>
                </div>
            @enderror

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center cursor-pointer select-none text-sm text-gray-700">
                    <input wire:model="remember" id="remember-me" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded-md">
                    <span class="ml-2">Lembrar de mim</span>
                </label>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 border border-transparent text-base font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 shadow-md transition touch-manipulation">
                    <span wire:loading.remove wire:target="login">Entrar no Sistema</span>
                    <span wire:loading wire:target="login" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Autenticando...
                    </span>
                </button>
            </div>
        </form>

        <div class="pt-4 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400">PontoFácil • KL Tecnologia • Portaria 671</p>
        </div>
    </div>
</div>