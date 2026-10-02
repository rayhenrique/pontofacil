<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Models\SystemSetting;
use App\Models\Company;
use App\Domain\Company\Services\CurrentCompany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Configurações')] class extends Component
{
    use WithFileUploads;

    public $logo;
    public ?string $current_logo_url = null;

    public string $company_legal_name = '';
    public string $company_trade_name = '';
    public string $company_cnpj = '';
    public string $company_phone = '';
    public string $company_email = '';
    public string $company_address = '';
    public string $company_city = '';
    public string $company_state = '';
    public string $company_postal_code = '';
    public string $company_header_state = '';
    public string $company_header_entity = '';
    public string $company_header_sub_entity = '';

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
        $company = CurrentCompany::get();
        $this->company_legal_name = (string) ($company->legal_name ?? '');
        $this->company_trade_name = (string) ($company->trade_name ?? '');
        $this->company_cnpj = (string) ($company->formatted_cnpj ?? $company->cnpj ?? '');
        $this->company_phone = (string) ($company->phone ?? '(82) 3543-1114');
        $this->company_email = (string) ($company->email ?? 'rhsaudetv@gmail.com');
        $this->company_address = (string) ($company->address ?? 'Rua Vereador Manoel Firmino, 108 – Centro');
        $this->company_city = (string) ($company->city ?? 'Teotônio Vilela');
        $this->company_state = (string) ($company->state ?? 'AL');
        $this->company_postal_code = (string) ($company->postal_code ?? '57265-000');
        $this->company_header_state = (string) ($company->header_state ?? 'ESTADO DE ALAGOAS');
        $this->company_header_entity = (string) ($company->header_entity ?? ($company->legal_name ?: 'PREFEITURA MUNICIPAL DE TEOTÔNIO VILELA'));
        $this->company_header_sub_entity = (string) ($company->header_sub_entity ?? ($company->trade_name ?: 'SECRETARIA MUNICIPAL DE SAÚDE'));
        $this->current_logo_url = $company->logo_url;

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

    public function saveCompany()
    {
        $this->validate([
            'company_legal_name' => 'required|string|max:150',
            'company_trade_name' => 'required|string|max:100',
            'company_cnpj' => 'required|string|max:25',
            'company_header_state' => 'nullable|string|max:100',
            'company_header_entity' => 'nullable|string|max:150',
            'company_header_sub_entity' => 'nullable|string|max:150',
            'company_address' => 'nullable|string|max:255',
            'company_city' => 'nullable|string|max:100',
            'company_state' => 'nullable|string|max:50',
            'company_postal_code' => 'nullable|string|max:20',
            'company_phone' => 'nullable|string|max:30',
            'company_email' => 'nullable|email|max:100',
            'logo' => 'nullable|image|max:2048',
        ]);

        $company = CurrentCompany::get();
        $cleanCnpj = preg_replace('/\D/', '', $this->company_cnpj);

        $data = [
            'legal_name' => $this->company_legal_name,
            'trade_name' => $this->company_trade_name,
            'cnpj' => $cleanCnpj ?: $this->company_cnpj,
            'header_state' => $this->company_header_state,
            'header_entity' => $this->company_header_entity,
            'header_sub_entity' => $this->company_header_sub_entity,
            'address' => $this->company_address,
            'city' => $this->company_city,
            'state' => $this->company_state,
            'postal_code' => $this->company_postal_code,
            'phone' => $this->company_phone,
            'email' => $this->company_email,
        ];

        if ($this->logo) {
            $path = $this->logo->store('company', 'public');
            $data['logo_path'] = $path;
            $this->current_logo_url = asset('storage/' . $path);
            $this->logo = null;
        }

        $company->update($data);

        $establishment = $company->defaultEstablishment();
        if ($establishment) {
            $establishment->update([
                'identifier_number' => $cleanCnpj ?: $establishment->identifier_number,
                'address' => $this->company_address ?: $establishment->address,
                'city' => $this->company_city ?: $establishment->city,
                'state' => $this->company_state ?: $establishment->state,
                'postal_code' => $this->company_postal_code ?: $establishment->postal_code,
            ]);
        }

        // Sincroniza em SystemSetting para manter retrocompatibilidade com relatórios existentes
        SystemSetting::updateOrCreate(['key' => 'report_header_state'], ['value' => $this->company_header_state]);
        SystemSetting::updateOrCreate(['key' => 'report_header_entity'], ['value' => $this->company_header_entity]);
        SystemSetting::updateOrCreate(['key' => 'report_header_sub_entity'], ['value' => $this->company_header_sub_entity]);
        SystemSetting::updateOrCreate(['key' => 'report_header_address'], ['value' => $this->company_address]);
        SystemSetting::updateOrCreate(['key' => 'report_header_cnpj'], ['value' => $company->formatted_cnpj]);
        SystemSetting::updateOrCreate(['key' => 'report_header_phone'], ['value' => $this->company_phone]);
        SystemSetting::updateOrCreate(['key' => 'report_header_email'], ['value' => $this->company_email]);
        if (!empty($data['logo_path'])) {
            SystemSetting::updateOrCreate(['key' => 'company_logo_url'], ['value' => $this->current_logo_url]);
        }

        CurrentCompany::clear();

        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Dados da Empresa Atualizados!',
            'message' => 'As informações cadastrais e o logotipo da empresa foram salvos com sucesso e já estão vigentes na Folha de Ponto.',
            'buttonText' => 'OK'
        ]);
    }

    public function removeLogo()
    {
        $company = CurrentCompany::get();
        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->update(['logo_path' => null]);
            SystemSetting::where('key', 'company_logo_url')->delete();
        }
        $this->current_logo_url = null;
        $this->logo = null;
        CurrentCompany::clear();

        $this->dispatch('app-modal-alert', [
            'type' => 'info',
            'title' => 'Logotipo Removido',
            'message' => 'O logotipo personalizado foi removido. A Folha de Ponto voltará a utilizar o brasão padrão.',
            'buttonText' => 'OK'
        ]);
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
            'created_by' => Auth::id(),
        ]);

        if ($wasEnabled !== $this->time_bank_enabled) {
            Log::info($this->time_bank_enabled ? 'time_bank.enabled' : 'time_bank.disabled', [
                'policy_id' => $policy->id,
                'by' => Auth::id(),
            ]);
        } else {
            Log::info('time_bank.policy_changed', [
                'policy_id' => $policy->id,
                'closing_mode' => $this->time_bank_closing_mode,
                'by' => Auth::id(),
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

<div class="max-w-5xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8 space-y-6" x-data="settingsComponent(@js($qr_code_hash))">
    <!-- Header Principal -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Configurações</h2>
        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Gestão cadastral da empresa, logotipo oficial, QR Code corporativo, geolocalização e banco de horas</p>
    </div>

    @if($message)
        <div class="rounded-xl p-4 {{ $status === 'success' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-red-50 text-red-900 border border-red-200' }}">
            <p class="text-sm font-semibold">{{ $message }}</p>
        </div>
    @endif

    <!-- Card 1: Dados da Empresa & Logotipo Oficial -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="border-b border-gray-100 pb-4 mb-6">
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
                Dados da Empresa & Logotipo Oficial
            </h3>
            <p class="text-xs text-gray-500 mt-1">Configure as informações institucionais da organização e o logotipo impresso no cabeçalho da Folha de Ponto.</p>
        </div>

        <form wire:submit="saveCompany" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Coluna Esquerda: Logotipo e Preview -->
                <div class="flex flex-col items-center p-5 bg-slate-50/80 border border-slate-200 rounded-2xl text-center space-y-4">
                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Logotipo da Organização</span>
                    
                    <div class="w-full h-36 border-2 border-dashed border-slate-300 rounded-2xl bg-white flex items-center justify-center p-3 relative overflow-hidden group">
                        @if($logo)
                            <img src="{{ $logo->temporaryUrl() }}" alt="Preview Novo Logotipo" class="max-h-full max-w-full object-contain" />
                            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-indigo-600 text-[10px] font-bold text-white shadow-xs">
                                Novo Selecionado
                            </div>
                        @elseif($current_logo_url)
                            <div class="relative w-full h-full flex items-center justify-center">
                                <img src="{{ $current_logo_url }}" 
                                     alt="Logotipo Atual" 
                                     class="max-h-full max-w-full object-contain"
                                     onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');" />
                                <div class="hidden flex flex-col items-center justify-center text-slate-400 space-y-1">
                                    <svg class="w-10 h-10 stroke-1 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    <span class="text-xs font-medium text-slate-400">Imagem inacessível</span>
                                    <span class="text-[10px] text-amber-600 font-medium">(Faça um novo upload abaixo)</span>
                                </div>
                                <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-emerald-600 text-[10px] font-bold text-white shadow-xs">
                                    Ativo na Folha
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center text-slate-400 space-y-1">
                                <svg class="w-10 h-10 stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <span class="text-xs font-medium">Nenhum logotipo anexado</span>
                                <span class="text-[10px] text-slate-400">(Usa brasão padrão)</span>
                            </div>
                        @endif
                    </div>

                    <div class="w-full space-y-2">
                        <label class="block">
                            <span class="sr-only">Escolher logotipo</span>
                            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="block w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer border border-slate-200 rounded-xl bg-white p-1" />
                        </label>
                        @error('logo') <span class="text-red-500 text-xs block text-left">{{ $message }}</span> @enderror
                        
                        <div wire:loading wire:target="logo" class="text-xs text-indigo-600 font-semibold flex items-center justify-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Enviando imagem...
                        </div>

                        <p class="text-[11px] text-slate-500 text-left leading-relaxed">
                            Formato PNG, JPG, WebP ou SVG (máx. 2MB). Ideal fundo transparente.
                        </p>

                        @if($current_logo_url || $logo)
                            <button type="button" wire:click="removeLogo" class="w-full py-1.5 px-3 bg-red-50 hover:bg-red-100 text-red-700 rounded-xl text-xs font-semibold transition border border-red-200/80">
                                Remover Logotipo Customizado
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Coluna Direita: Informações Cadastrais e do Cabeçalho -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Razão Social</label>
                            <input type="text" wire:model="company_legal_name" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="Empresa S/A" required>
                            @error('company_legal_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Nome Fantasia</label>
                            <input type="text" wire:model="company_trade_name" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="Nome Fantasia" required>
                            @error('company_trade_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">CNPJ</label>
                            <input type="text" wire:model="company_cnpj" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-mono" placeholder="00.000.000/0001-00" required>
                            @error('company_cnpj') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Telefone</label>
                            <input type="text" wire:model="company_phone" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="(82) 3543-1114">
                            @error('company_phone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">E-mail de Contato / RH</label>
                            <input type="email" wire:model="company_email" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="rh@empresa.com">
                            @error('company_email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Endereço da Sede (Logradouro e Número)</label>
                        <input type="text" wire:model="company_address" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="Rua / Av., 100 - Bairro">
                        @error('company_address') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Cidade</label>
                            <input type="text" wire:model="company_city" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="Maceió">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">UF / Estado</label>
                            <input type="text" wire:model="company_state" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" placeholder="AL">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">CEP</label>
                            <input type="text" wire:model="company_postal_code" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-mono" placeholder="57000-000">
                        </div>
                    </div>

                    <!-- Campos do Cabeçalho da Folha de Ponto -->
                    <div class="pt-3 border-t border-slate-100">
                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block mb-2">Cabeçalho da Folha de Ponto Oficial</span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Órgão / Entidade Principal</label>
                                <input type="text" wire:model="company_header_entity" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg" placeholder="PREFEITURA MUNICIPAL...">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Sub-Entidade / Secretaria</label>
                                <input type="text" wire:model="company_header_sub_entity" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg" placeholder="SECRETARIA DE SAÚDE...">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Linha Superior (Estado/UF)</label>
                                <input type="text" wire:model="company_header_state" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg" placeholder="ESTADO DE ALAGOAS">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-3 border-t border-gray-100">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm transition">
                    <span wire:loading.remove wire:target="saveCompany, logo">Salvar Dados da Empresa</span>
                    <span wire:loading wire:target="saveCompany, logo">Salvando Dados...</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Card 2: QR Code Section -->
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
                    <span class="text-sm font-bold text-gray-900 tracking-tight">{{ $company_trade_name ?: 'PontoFácil' }}</span>
                    <p class="text-xs text-gray-500">Ponto Eletrônico (REP-P / REP-A)</p>
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

    <!-- Card 3: GPS & Geolocation Settings -->
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

    <!-- Card 4: Banco de Horas Section -->
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
