<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] #[Title('Configurações da Empresa e QR Code')] class extends Component
{
    public string $qr_code_hash = '';
    public string $company_latitude = '-9.6658';
    public string $company_longitude = '-35.7350';
    public string $allowed_radius_meters = '100';

    public bool $time_bank_enabled = false;
    public string $time_bank_closing_mode = 'CARRY_OVER';
    public string $time_bank_policy_name = 'Regra de Banco de Horas da Empresa';

    public string $message = '';
    public string $status = '';

    public function mount()
    {
        $settings = SystemSetting::whereIn('key', [
            'qr_code_hash',
            'company_latitude',
            'company_longitude',
            'allowed_radius_meters',
        ])->pluck('value', 'key');

        if (empty($settings['qr_code_hash'])) {
            $newHash = Str::random(40);
            SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => $newHash]);
            $this->qr_code_hash = $newHash;
        } else {
            $this->qr_code_hash = $settings['qr_code_hash'];
        }

        $this->company_latitude = $settings['company_latitude'] ?? '-9.6658';
        $this->company_longitude = $settings['company_longitude'] ?? '-35.7350';
        $this->allowed_radius_meters = $settings['allowed_radius_meters'] ?? '100';

        $currentPolicy = \App\Models\TimeBankPolicy::current();
        $this->time_bank_enabled = (bool) $currentPolicy->enabled;
        $this->time_bank_closing_mode = $currentPolicy->closing_mode?->value ?? 'CARRY_OVER';
        $this->time_bank_policy_name = $currentPolicy->name ?? 'Regra de Banco de Horas da Empresa';
    }

    public function regenerateQrCode()
    {
        $this->qr_code_hash = Str::random(40);
        SystemSetting::updateOrCreate(['key' => 'qr_code_hash'], ['value' => $this->qr_code_hash]);

        $this->message = 'Novo QR Code gerado com sucesso! Os colaboradores deverão ler este novo código para registrar o ponto.';
        $this->status = 'success';
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Novo QR Code Gerado!',
            'message' => 'O QR Code oficial da empresa foi atualizado com sucesso. Imprima a nova versão para fixação na entrada.',
            'buttonText' => 'Entendido'
        ]);
    }

    public function saveGps()
    {
        $this->validate([
            'company_latitude' => 'required|numeric|between:-90,90',
            'company_longitude' => 'required|numeric|between:-180,180',
            'allowed_radius_meters' => 'required|numeric|min:10|max:5000',
        ]);

        SystemSetting::updateOrCreate(['key' => 'company_latitude'], ['value' => $this->company_latitude]);
        SystemSetting::updateOrCreate(['key' => 'company_longitude'], ['value' => $this->company_longitude]);
        SystemSetting::updateOrCreate(['key' => 'allowed_radius_meters'], ['value' => $this->allowed_radius_meters]);

        $this->message = 'Coordenadas e raio de segurança da empresa atualizados com sucesso.';
        $this->status = 'success';
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Parâmetros GPS Atualizados!',
            'message' => 'As coordenadas da sede e o raio de segurança antifraude foram salvos com sucesso.',
            'buttonText' => 'OK'
        ]);
    }

    public function saveTimeBank()
    {
        $this->validate([
            'time_bank_policy_name' => 'required|string|max:120',
            'time_bank_closing_mode' => 'required|in:CARRY_OVER,MONTHLY_RESET',
        ]);

        $previousPolicy = \App\Models\TimeBankPolicy::current();
        $wasEnabled = $previousPolicy->enabled;

        $policy = \App\Models\TimeBankPolicy::create([
            'name' => $this->time_bank_policy_name,
            'enabled' => $this->time_bank_enabled,
            'closing_mode' => $this->time_bank_closing_mode,
            'valid_from' => now()->startOfMonth()->toDateString(),
            'created_by' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        if ($wasEnabled !== $this->time_bank_enabled) {
            \Illuminate\Support\Facades\Log::info($this->time_bank_enabled ? 'time_bank.enabled' : 'time_bank.disabled', [
                'policy_id' => $policy->id,
                'by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        } else {
            \Illuminate\Support\Facades\Log::info('time_bank.policy_changed', [
                'policy_id' => $policy->id,
                'closing_mode' => $this->time_bank_closing_mode,
                'by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }

        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Regras de Banco de Horas Atualizadas!',
            'message' => $this->time_bank_enabled
                ? 'O Banco de Horas está ATIVADO com política ' . ($this->time_bank_closing_mode === 'CARRY_OVER' ? 'de Acúmulo Contínuo.' : 'de Zeramento Mensal no fechamento.')
                : 'O Banco de Horas foi DESATIVADO para a empresa.',
            'buttonText' => 'OK'
        ]);
    }
};
?>

<div class="max-w-4xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6" x-data="settingsComponent(@js($qr_code_hash))">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Configurações da Empresa & QR Code</h2>
        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Gestão do QR Code corporativo físico e parâmetros de geolocalização antifraude (Portaria 671)</p>
    </div>

    @if($message)
        <div class="rounded-xl p-4 {{ $status === 'success' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-red-50 text-red-900 border border-red-200' }}">
            <p class="text-sm font-semibold">{{ $message }}</p>
        </div>
    @endif

    <!-- QR Code Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="border-b border-gray-100 pb-4 mb-6">
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" /></svg>
                QR Code Oficial da Empresa
            </h3>
            <p class="text-xs text-gray-500 mt-1">Imprima este QR Code e fixe-o na entrada do estabelecimento para que os colaboradores façam a leitura ao bater o ponto.</p>
        </div>

        <div class="flex flex-col md:flex-row items-center gap-8 justify-around">
            <!-- Canvas QR Code Display -->
            <div id="print-area" class="flex flex-col items-center p-6 bg-gray-50 border border-gray-200 rounded-2xl shadow-xs text-center">
                <div class="mb-3">
                    <span class="text-sm font-bold text-gray-900 tracking-tight">PontoFácil • KL Tecnologia</span>
                    <p class="text-xs text-gray-500">Ponto Eletrônico (REP-A)</p>
                </div>
                
                <canvas id="qrcode-canvas" class="rounded-xl shadow-xs bg-white p-3 border border-gray-200"></canvas>
                
                <div class="mt-4 max-w-[260px]">
                    <p class="text-xs text-gray-400 font-mono break-all">{{ $qr_code_hash }}</p>
                    <p class="text-xs text-indigo-700 font-semibold mt-2">Aponte a câmera para registrar</p>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex-1 space-y-4 w-full">
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-800 space-y-1">
                    <p class="font-bold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                        Segurança do QR Code
                    </p>
                    <p>Caso o QR Code impresso seja vazado ou precise ser rotacionado por motivos de segurança, clique no botão abaixo para gerar uma nova chave. Os colaboradores precisarão ler o novo código.</p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="button" @click="printQrCode()" class="flex-1 inline-flex items-center justify-center px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.076-.672-2.152-1.34-3.08-1.528-2.122-4.004-3.52-6.88-3.749m12.35 15.029v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 15.75v6.75m15 0v-6.75a2.25 2.25 0 0 0-2.25-2.25H16.5m3.75 9H3.75" /></svg>
                        Imprimir QR Code
                    </button>

                    <button type="button" @click="window.showModalConfirm({
                        title: 'Invalidar e Gerar Novo QR Code',
                        message: 'Tem certeza de que deseja invalidar o QR Code atual e gerar um novo? O código impresso atual deixará de funcionar imediatamente para todos os colaboradores.',
                        confirmText: 'Sim, Gerar Novo',
                        cancelText: 'Cancelar',
                        isDanger: true,
                        onConfirm: () => $wire.regenerateQrCode()
                    })" class="inline-flex items-center justify-center px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl text-sm font-semibold transition">
                        <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        Gerar Novo Código
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GPS & Geolocation Settings -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="border-b border-gray-100 pb-4 mb-6">
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                Geolocalização & Raio de Validação (GPS)
            </h3>
            <p class="text-xs text-gray-500 mt-1">Defina as coordenadas exatas da sede da empresa e a distância máxima permitida para o registro do ponto.</p>
        </div>

        <form wire:submit="saveGps" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Latitude</label>
                    <input type="text" wire:model="company_latitude" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-mono" placeholder="-9.6658" required>
                    @error('company_latitude') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Longitude</label>
                    <input type="text" wire:model="company_longitude" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-mono" placeholder="-35.7350" required>
                    @error('company_longitude') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Raio Permitido (metros)</label>
                    <input type="number" wire:model="allowed_radius_meters" min="10" max="5000" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="100" required>
                    @error('allowed_radius_meters') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-3 border-t">
                <button type="button" @click="detectCurrentLocation()" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                    <svg class="w-4 h-4 mr-1.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                    Capturar Minha Posição Atual (GPS)
                </button>

                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                    <span wire:loading.remove wire:target="saveGps">Salvar Coordenadas</span>
                    <span wire:loading wire:target="saveGps">Salvando...</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Banco de Horas Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="border-b border-gray-100 pb-4 mb-6">
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                Banco de Horas & Fechamento de Competência (PTRP)
            </h3>
            <p class="text-xs text-gray-500 mt-1">Defina se a empresa utiliza regime de banco de horas e a regra aplicada no fechamento formal de cada mês.</p>
        </div>

        <form wire:submit="saveTimeBank" class="space-y-6">
            <!-- Ativação Geral -->
            <div class="flex items-center justify-between p-4 rounded-xl border {{ $time_bank_enabled ? 'bg-indigo-50/50 border-indigo-200' : 'bg-gray-50 border-gray-200' }}">
                <div>
                    <span class="text-sm font-bold text-gray-900">Regime de Banco de Horas</span>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $time_bank_enabled ? 'O banco de horas está ATIVADO. Horas extras e déficits serão computados no ledger.' : 'O banco de horas está DESATIVADO (padrão). Horas extras e atrasos serão tratados fora do banco.' }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="time_bank_enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            @if($time_bank_enabled)
                <div class="space-y-4 pt-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nome / Identificação da Política</label>
                        <input type="text" wire:model="time_bank_policy_name" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="Ex: Acordo Coletivo 2026 - Banco de Horas Semestral" required>
                        @error('time_bank_policy_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Comportamento no Fechamento Mensal da Competência</label>
                        
                        <div class="grid sm:grid-cols-2 gap-4">
                            <!-- Opção CARRY_OVER -->
                            <label class="relative flex flex-col p-4 border rounded-2xl cursor-pointer transition {{ $time_bank_closing_mode === 'CARRY_OVER' ? 'border-indigo-600 bg-indigo-50/40 ring-2 ring-indigo-500/20' : 'border-gray-200 hover:border-gray-300 bg-white' }}">
                                <div class="flex items-center gap-3 mb-2">
                                    <input type="radio" wire:model="time_bank_closing_mode" value="CARRY_OVER" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <span class="text-sm font-bold text-gray-900">Acumular para o mês seguinte</span>
                                </div>
                                <p class="text-xs text-gray-500 leading-relaxed pl-7">
                                    O saldo positivo ou negativo permanece íntegro e continua no mês subsequente. Nenhuma movimentação artificial é criada.
                                </p>
                            </label>

                            <!-- Opção MONTHLY_RESET -->
                            <label class="relative flex flex-col p-4 border rounded-2xl cursor-pointer transition {{ $time_bank_closing_mode === 'MONTHLY_RESET' ? 'border-indigo-600 bg-indigo-50/40 ring-2 ring-indigo-500/20' : 'border-gray-200 hover:border-gray-300 bg-white' }}">
                                <div class="flex items-center gap-3 mb-2">
                                    <input type="radio" wire:model="time_bank_closing_mode" value="MONTHLY_RESET" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <span class="text-sm font-bold text-gray-900">Zerar ao fechar o mês</span>
                                </div>
                                <p class="text-xs text-gray-500 leading-relaxed pl-7">
                                    No fechamento formal pelo RH, é registrado um lançamento compensatório no ledger (positivo ou negativo) para iniciar o próximo mês com saldo zero, preservando o histórico integral.
                                </p>
                            </label>
                        </div>
                        @error('time_bank_closing_mode') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-3.5 bg-amber-50/80 border border-amber-200/80 rounded-xl text-xs text-amber-900 leading-relaxed flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        <span><strong>Regra de Auditoria:</strong> O zeramento nunca acontece automaticamente pela virada do relógio à meia-noite. Ele só é acionado quando o RH executa o procedimento formal de <strong>Fechar Competência</strong>.</span>
                    </div>
                </div>
            @endif

            <div class="flex justify-end pt-3 border-t border-gray-100">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                    <span wire:loading.remove wire:target="saveTimeBank">Salvar Regras de Banco de Horas</span>
                    <span wire:loading wire:target="saveTimeBank">Salvando Regras...</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('settingsComponent', (qrHash) => ({
                currentHash: qrHash,

                init() {
                    this.renderQrCode();
                    this.$watch('currentHash', () => this.renderQrCode());
                },

                renderQrCode() {
                    const canvas = document.getElementById('qrcode-canvas');
                    if (!canvas || !window.QRCode) return;

                    window.QRCode.toCanvas(canvas, this.currentHash, {
                        width: 220,
                        margin: 1,
                        color: {
                            dark: '#1e1b4b',
                            light: '#ffffff'
                        }
                    }, (error) => {
                        if (error) console.error('Erro ao renderizar QR Code:', error);
                    });
                },

                detectCurrentLocation() {
                    if (!navigator.geolocation) {
                        window.showModalAlert('warning', 'Geolocalização Indisponível', 'Geolocalização não é suportada pelo seu navegador.');
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            @this.set('company_latitude', pos.coords.latitude.toFixed(6));
                            @this.set('company_longitude', pos.coords.longitude.toFixed(6));
                            window.showModalAlert('success', 'Localização Capturada!', 'Coordenadas capturadas com sucesso! Clique no botão "Salvar Coordenadas" para confirmar a gravação.');
                        },
                        (err) => {
                            window.showModalAlert('error', 'Falha ao Obter Localização', 'Não foi possível obter a localização: ' + err.message);
                        },
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                },

                printQrCode() {
                    window.print();
                }
            }));
        });
    </script>
</div>
