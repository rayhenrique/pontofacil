<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] #[Title('Ajuda e Novidades')] class extends Component
{
    public $activeTab = 'manual';
    public $changelogHtml = '';

    public function mount()
    {
        $path = base_path('versoes.md');
        if (File::exists($path)) {
            $this->changelogHtml = Str::markdown(File::get($path));
        }
    }
};
?>

<div class="max-w-4xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
        <!-- Tabs Header (Touch Friendly) -->
        <div class="border-b border-gray-200 bg-gray-50/50">
            <nav class="flex" aria-label="Tabs">
                <button wire:click="$set('activeTab', 'manual')" class="w-1/2 py-3.5 px-3 text-center border-b-2 font-semibold text-sm transition {{ $activeTab === 'manual' ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Manual do Usuário
                </button>
                <button wire:click="$set('activeTab', 'changelog')" class="w-1/2 py-3.5 px-3 text-center border-b-2 font-semibold text-sm transition {{ $activeTab === 'changelog' ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Novidades e Versões
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-4 sm:p-8">
            @if($activeTab === 'manual')
                <div class="prose max-w-none text-gray-700 text-sm sm:text-base space-y-4" wire:transition>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 border-b pb-3">Como usar o PontoFácil</h2>
                    
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-indigo-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs">1</span>
                            Batida de Ponto Diária
                        </h3>
                        <p class="mt-1 text-gray-600">Ao acessar o sistema, vá para a tela inicial <strong>"Bater Ponto"</strong>. O sistema ativará a câmera do seu dispositivo.</p>
                        <ul class="list-disc list-inside mt-2 space-y-1 text-gray-600 text-xs sm:text-sm">
                            <li>Aponte a câmera para o QR Code físico fixado na empresa.</li>
                            <li>Autorize o uso da localização GPS do navegador (necessário para validação da distância).</li>
                            <li>O sistema fará a leitura e gravará a batida com carimbo de tempo inviolável do servidor.</li>
                        </ul>
                    </div>
                    
                    <div class="pt-2">
                        <h3 class="text-base sm:text-lg font-bold text-indigo-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs">2</span>
                            Espelho de Ponto
                        </h3>
                        <p class="mt-1 text-gray-600">No menu <strong>"Espelho de Ponto"</strong>, você visualiza todo o histórico de entradas e saídas agrupadas por dia, com opção de filtrar por mês e ano.</p>
                    </div>

                    @if(Auth::user()->role === App\Enums\UserRole::Admin)
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="text-base sm:text-lg font-bold text-purple-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-purple-100 text-purple-700 inline-flex items-center justify-center text-xs">★</span>
                            Módulo Administrativo (RH)
                        </h3>
                        <p class="mt-1 text-gray-600">Como administrador, você tem acesso às seguintes ferramentas:</p>
                        <ul class="list-disc list-inside mt-2 space-y-1.5 text-gray-600 text-xs sm:text-sm">
                            <li><strong>Setores e Funcionários:</strong> Cadastro e manutenção dos departamentos e colaboradores.</li>
                            <li><strong>Espelho Geral & Relatórios:</strong> Filtragem por período, colaborador ou setor para fechamento de folha.</li>
                            <li><strong>Ajuste Manual:</strong> Lançamento retroativo de batidas esquecidas com justificativa obrigatória.</li>
                            <li><strong>Trilha de Auditoria:</strong> Histórico imutável de todas as intervenções manuais realizadas.</li>
                        </ul>
                    </div>
                    @elseif(Auth::user()->role === App\Enums\UserRole::Manager)
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="text-base sm:text-lg font-bold text-indigo-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs">★</span>
                            Módulo de Gestão de Equipe
                        </h3>
                        <p class="mt-1 text-gray-600">Como gestor, você tem acesso às seguintes ferramentas:</p>
                        <ul class="list-disc list-inside mt-2 space-y-1.5 text-gray-600 text-xs sm:text-sm">
                            <li><strong>Gerenciar Funcionários:</strong> Cadastro e visualização dos colaboradores exclusivamente vinculados aos setores sob sua responsabilidade.</li>
                        </ul>
                    </div>
                    @endif
                </div>
            @endif

            @if($activeTab === 'changelog')
                <div class="prose max-w-none text-gray-700 bg-gray-50 p-4 sm:p-6 rounded-xl border border-gray-200 text-xs sm:text-sm" wire:transition>
                    {!! $changelogHtml !!}
                </div>
            @endif
        </div>
    </div>
</div>