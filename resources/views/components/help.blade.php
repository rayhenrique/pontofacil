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

<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow">
        <!-- Tabs Header -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex" aria-label="Tabs">
                <button wire:click="$set('activeTab', 'manual')" class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $activeTab === 'manual' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Manual do Usuário
                </button>
                <button wire:click="$set('activeTab', 'changelog')" class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm {{ $activeTab === 'changelog' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Novidades e Versões
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            @if($activeTab === 'manual')
                <div class="prose max-w-none text-gray-700" wire:transition>
                    <h2>Como usar o PontoFácil</h2>
                    
                    <h3>1. Batida de Ponto Diária</h3>
                    <p>Ao acessar o sistema, vá para a tela inicial <strong>"Bater Ponto"</strong>. O sistema ativará a câmera do seu dispositivo.</p>
                    <ul>
                        <li>Aponte a câmera para o QR Code fornecido pela empresa.</li>
                        <li>Autorize o uso da localização GPS (obrigatório para validar a batida).</li>
                        <li>O sistema identificará a leitura e gravará seu ponto.</li>
                    </ul>
                    
                    <h3>2. Espelho de Ponto</h3>
                    <p>No menu <strong>"Espelho de Ponto"</strong>, você pode visualizar todo o histórico das suas entradas e saídas. As informações estão agrupadas por dia e podem ser filtradas por mês e ano.</p>

                    @if(Auth::user()->role === App\Enums\UserRole::Admin)
                    <hr class="my-6">
                    <h3 class="text-indigo-600">Módulo Administrativo (RH)</h3>
                    <p>Como administrador, você possui acessos adicionais:</p>
                    <ul>
                        <li><strong>Espelho Geral:</strong> Você pode visualizar o espelho de ponto de qualquer funcionário através do menu suspenso.</li>
                        <li><strong>Ajuste Manual:</strong> Na guia "Ajuste Manual", você pode lançar ou corrigir pontos esquecidos ou falhos. Para garantir a segurança antifraude, toda inclusão exige uma justificativa que ficará cravada na <em>Trilha de Auditoria</em> do banco de dados, associada ao seu usuário.</li>
                    </ul>
                    @endif
                </div>
            @endif

            @if($activeTab === 'changelog')
                <div class="prose max-w-none text-gray-700 bg-gray-50 p-6 rounded-md border border-gray-100" wire:transition>
                    {!! $changelogHtml !!}
                </div>
            @endif
        </div>
    </div>
</div>