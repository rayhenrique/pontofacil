<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Sector;
use App\Models\Employee;
use App\Models\SystemSetting;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new #[Layout('layouts.app')] #[Title('Folha de Ponto de Funcionário • Modelo Oficial')] class extends Component
{
    public $userId;
    public $month;
    public $year;
    public $fillMode = 'punches'; // 'punches' ou 'blank'
    public $includeRubrica = true;

    // Cabeçalho Oficial
    public $headerState = 'ESTADO DE ALAGOAS';
    public $headerEntity = 'PREFEITURA MUNICIPAL DE TEOTÔNIO VILELA';
    public $headerSubEntity = 'SECRETARIA MUNICIPAL DE SAÚDE';
    public $headerAddress = 'Rua Vereador Manoel Firmino, 108 – Centro – Teotônio Vilela – Alagoas';
    public $headerCnpj = '11.780.685/0001-52';
    public $headerPhone = '(82) 3543-1114';
    public $headerEmail = 'rhsaudetv@gmail.com';
    public ?string $companyLogoUrl = null;

    // Metadados do Servidor
    public $workload = '40h';
    public $zone = 'Urbana';
    public $jobTitle = '';
    public $contractType = 'Efetivo';
    public $location = '';

    public function mount()
    {
        $this->month = (int) (request('month') ?? now()->month);
        $this->year = (int) (request('year') ?? now()->year);

        $currentUser = Auth::user();
        $requestedUserId = request('userId');

        if ($currentUser->role === UserRole::Admin) {
            $this->userId = $requestedUserId ? (int) $requestedUserId : $currentUser->id;
        } elseif ($currentUser->role === UserRole::Manager) {
            $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
            $allowedUserIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id')->push($currentUser->id);

            if ($requestedUserId && $allowedUserIds->contains((int) $requestedUserId)) {
                $this->userId = (int) $requestedUserId;
            } else {
                $this->userId = $currentUser->id;
            }
        } else {
            $this->userId = $currentUser->id;
        }

        $this->loadSettings();
        $this->loadEmployeeData();
    }

    public function loadSettings()
    {
        $company = \App\Domain\Company\Services\CurrentCompany::get();
        if (!empty($company->header_state)) $this->headerState = $company->header_state;
        if (!empty($company->header_entity)) $this->headerEntity = $company->header_entity;
        if (!empty($company->header_sub_entity)) $this->headerSubEntity = $company->header_sub_entity;
        if (!empty($company->address)) {
            $addr = $company->address;
            if ($company->city) $addr .= " – {$company->city}";
            if ($company->state) $addr .= " – {$company->state}";
            $this->headerAddress = $addr;
        }
        if (!empty($company->cnpj)) $this->headerCnpj = $company->formatted_cnpj;
        if (!empty($company->phone)) $this->headerPhone = $company->phone;
        if (!empty($company->email)) $this->headerEmail = $company->email;
        if (!empty($company->logo_url)) $this->companyLogoUrl = $company->logo_url;

        $settings = SystemSetting::whereIn('key', [
            'report_header_state',
            'report_header_entity',
            'report_header_sub_entity',
            'report_header_address',
            'report_header_cnpj',
            'report_header_phone',
            'report_header_email',
            'company_logo_url',
        ])->pluck('value', 'key');

        if (!empty($settings['report_header_state'])) $this->headerState = $settings['report_header_state'];
        if (!empty($settings['report_header_entity'])) $this->headerEntity = $settings['report_header_entity'];
        if (!empty($settings['report_header_sub_entity'])) $this->headerSubEntity = $settings['report_header_sub_entity'];
        if (!empty($settings['report_header_address'])) $this->headerAddress = $settings['report_header_address'];
        if (!empty($settings['report_header_cnpj'])) $this->headerCnpj = $settings['report_header_cnpj'];
        if (!empty($settings['report_header_phone'])) $this->headerPhone = $settings['report_header_phone'];
        if (!empty($settings['report_header_email'])) $this->headerEmail = $settings['report_header_email'];
        if (!empty($settings['company_logo_url'])) $this->companyLogoUrl = $settings['company_logo_url'];
    }

    public function updatedUserId()
    {
        $this->loadEmployeeData();
    }

    public function loadEmployeeData()
    {
        $targetUser = User::with('employee.sector')->find($this->userId);
        if ($targetUser && $targetUser->employee) {
            $emp = $targetUser->employee;
            $this->location = optional($emp->sector)->name ?? 'Secretaria Municipal de Saúde';
            $this->jobTitle = $emp->job_title ?: ($targetUser->role === UserRole::Admin ? 'Administrador do Sistema' : ($targetUser->role === UserRole::Manager ? 'Coordenador / Gestor' : 'Servidor Público'));
            $this->contractType = $emp->contract_type ?: 'Efetivo';
            $this->workload = $emp->workload ?: '40h';
            $this->zone = $emp->zone ?: 'Urbana';
        } else {
            $this->location = 'Secretaria Municipal de Saúde';
            $this->jobTitle = ($targetUser && $targetUser->role === UserRole::Admin) ? 'Administrador do Sistema' : (($targetUser && $targetUser->role === UserRole::Manager) ? 'Coordenador / Gestor' : 'Servidor Público');
            $this->contractType = 'Efetivo';
            $this->workload = '40h';
            $this->zone = 'Urbana';
        }
    }

    public function with()
    {
        $currentUser = Auth::user();
        $isAdmin = $currentUser->role === UserRole::Admin;
        $isManager = $currentUser->role === UserRole::Manager;

        $selectableUsers = collect([]);
        if ($isAdmin) {
            $selectableUsers = User::orderBy('name')->get();
        } elseif ($isManager) {
            $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
            $employeeUserIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id');
            $allowedUserIds = $employeeUserIds->push($currentUser->id)->unique();
            $selectableUsers = User::whereIn('id', $allowedUserIds)->orderBy('name')->get();
        } else {
            $selectableUsers = collect([$currentUser]);
        }

        $canSelectUser = $isAdmin || ($isManager && $selectableUsers->count() > 1);

        $selectedUser = User::with('employee.sector')->find($this->userId) ?? $currentUser;

        // Dados do mês e ano
        $carbonMonth = Carbon::createFromDate($this->year, $this->month, 1)->locale('pt_BR');
        $monthNameUpper = strtoupper($carbonMonth->translatedFormat('F'));
        $daysInMonth = $carbonMonth->daysInMonth;

        // Puxar batidas de ponto se fillMode === 'punches'
        $entriesByDate = collect([]);
        if ($this->fillMode === 'punches') {
            $entries = TimeEntry::where('user_id', $this->userId)
                ->whereYear('timestamp', $this->year)
                ->whereMonth('timestamp', $this->month)
                ->orderBy('timestamp', 'asc')
                ->get();

            $entriesByDate = $entries->groupBy(function ($entry) {
                return Carbon::parse($entry->timestamp)->format('Y-m-d');
            });
        }

        // Montar linhas dos dias do mês (01 até o último dia do mês)
        $daysRows = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDate = Carbon::createFromDate($this->year, $this->month, $d)->locale('pt_BR');
            $dateKey = $currentDate->format('Y-m-d');
            $dayNum = sprintf('%02d', $d);

            $isSat = $currentDate->isSaturday();
            $isSun = $currentDate->isSunday();

            $dayEntries = $entriesByDate->get($dateKey, collect([]));
            $hasEntries = $dayEntries->isNotEmpty();

            $row = [
                'day' => $dayNum,
                'isSaturday' => $isSat,
                'isSunday' => $isSun,
                'hasEntries' => $hasEntries,
                'entr1' => '',
                'rubrica1' => '',
                'saida1' => '',
                'rubrica2' => '',
                'entr2' => '',
                'rubrica3' => '',
                'saida2' => '',
                'rubrica4' => '',
            ];

            if ($this->fillMode === 'punches' && $hasEntries) {
                // Preencher com os horários registrados
                $sorted = $dayEntries->sortBy('timestamp')->values();
                $count = $sorted->count();

                if ($count === 1) {
                    $row['entr1'] = Carbon::parse($sorted[0]->timestamp)->format('H:i');
                    $row['saida1'] = ': ';
                    $row['entr2'] = ': ';
                    $row['saida2'] = ': ';
                } elseif ($count === 2) {
                    $row['entr1'] = Carbon::parse($sorted[0]->timestamp)->format('H:i');
                    $row['saida1'] = ': ';
                    $row['entr2'] = ': ';
                    $row['saida2'] = Carbon::parse($sorted[1]->timestamp)->format('H:i');
                } elseif ($count === 3) {
                    $row['entr1'] = Carbon::parse($sorted[0]->timestamp)->format('H:i');
                    $row['saida1'] = Carbon::parse($sorted[1]->timestamp)->format('H:i');
                    $row['entr2'] = Carbon::parse($sorted[2]->timestamp)->format('H:i');
                    $row['saida2'] = ': ';
                } else {
                    $row['entr1'] = Carbon::parse($sorted[0]->timestamp)->format('H:i');
                    $row['saida1'] = Carbon::parse($sorted[1]->timestamp)->format('H:i');
                    $row['entr2'] = Carbon::parse($sorted[2]->timestamp)->format('H:i');
                    $row['saida2'] = Carbon::parse($sorted[3]->timestamp)->format('H:i');
                }

                if ($this->includeRubrica) {
                    if (!empty($row['entr1']) && $row['entr1'] !== ': ') $row['rubrica1'] = '✓';
                    if (!empty($row['saida1']) && $row['saida1'] !== ': ') $row['rubrica2'] = '✓';
                    if (!empty($row['entr2']) && $row['entr2'] !== ': ') $row['rubrica3'] = '✓';
                    if (!empty($row['saida2']) && $row['saida2'] !== ': ') $row['rubrica4'] = '✓';
                }
            } else {
                // Sem batidas ou modo folha em branco
                if ($isSat) {
                    // No modelo impresso, sábados exibem a palavra SÁBADO
                    $row['entr1'] = '';
                    $row['rubrica1'] = 'SÁBADO';
                    $row['saida1'] = '';
                    $row['rubrica2'] = 'SÁBADO';
                    $row['entr2'] = '';
                    $row['rubrica3'] = 'SÁBADO';
                    $row['saida2'] = '';
                    $row['rubrica4'] = 'SÁBADO';
                } elseif ($isSun) {
                    // No modelo impresso, domingos exibem a palavra DOMINGO
                    $row['entr1'] = '';
                    $row['rubrica1'] = 'DOMINGO';
                    $row['saida1'] = '';
                    $row['rubrica2'] = 'DOMINGO';
                    $row['entr2'] = '';
                    $row['rubrica3'] = 'DOMINGO';
                    $row['saida2'] = '';
                    $row['rubrica4'] = 'DOMINGO';
                } else {
                    // Dias normais sem batida trazem ': ' para escrita manual
                    $row['entr1'] = ': ';
                    $row['saida1'] = ': ';
                    $row['entr2'] = ': ';
                    $row['saida2'] = ': ';
                }
            }

            $daysRows[] = $row;
        }

        return [
            'selectedUser' => $selectedUser,
            'canSelectUser' => $canSelectUser,
            'selectableUsers' => $selectableUsers,
            'monthNameUpper' => $monthNameUpper,
            'daysInMonth' => $daysInMonth,
            'daysRows' => $daysRows,
        ];
    }
};
?>

<div class="print-wrapper py-2 sm:py-6 px-1 sm:px-4 lg:px-8">

    <!-- BARRA SUPERIOR DE CONTROLES E FILTROS (ESCONDIDA NA IMPRESSÃO) -->
    <div class="no-print max-w-5xl mx-auto mb-6 bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 space-y-4">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Folha de Ponto de Funcionário
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    Modelo oficial de folha de frequência para conferência, assinatura física e arquivamento no RH.
                </p>
            </div>

            <!-- Botões de Ação -->
            <div class="flex items-center gap-2.5">
                <button type="button" 
                        onclick="window.print()" 
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl text-sm font-bold shadow-md shadow-indigo-600/20 transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.076-.641-2.072-1.185-2.97a9.387 9.387 0 0 0-2.316-2.482M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                    </svg>
                    <span>Imprimir Folha (A4)</span>
                </button>

                <a href="{{ route('timesheet') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold transition">
                    Voltar
                </a>
            </div>
        </div>

        <!-- Filtros Principais -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
            @if($canSelectUser)
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Servidor / Funcionário</label>
                <select wire:model.live="userId" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @foreach($selectableUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Mês</label>
                <select wire:model.live="month" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ sprintf('%02d', $m) }} - {{ Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Ano</label>
                <select wire:model.live="year" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Modo de Preenchimento</label>
                <select wire:model.live="fillMode" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-medium">
                    <option value="punches">Preencher c/ Batidas do Sistema</option>
                    <option value="blank">Folha em Branco (p/ preenchimento manual)</option>
                </select>
            </div>
        </div>

        <!-- Campos de Metadados Editáveis na Visualização -->
        <div x-data="{ showAdvanced: false }" class="pt-2 border-t border-gray-100">
            <button @click="showAdvanced = !showAdvanced" type="button" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 focus:outline-hidden">
                <span x-text="showAdvanced ? '− Ocultar Personalização de Cabeçalho / Servidor' : '+ Personalizar Cabeçalho / Cargo / Vínculo / Local'"></span>
            </button>

            <div x-show="showAdvanced" x-cloak class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-3 pt-3">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Cargo</label>
                    <input type="text" wire:model.live="jobTitle" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Vínculo</label>
                    <input type="text" wire:model.live="contractType" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Carga Horária</label>
                    <input type="text" wire:model.live="workload" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Zona</label>
                    <input type="text" wire:model.live="zone" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 uppercase mb-0.5">Local / Setor</label>
                    <input type="text" wire:model.live="location" class="block w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg">
                </div>
            </div>
        </div>

    </div>

    <!-- FOLHA DE PONTO OFICIAL (A4 FIDELIDADE 100% AO MODELO PDF) -->
    <div class="print-container max-w-[820px] mx-auto bg-white border border-gray-300 shadow-md p-6 sm:p-8 text-black print-sheet" style="font-family: Arial, Helvetica, 'Times New Roman', sans-serif;">
        
        <!-- CABEÇALHO DO ÓRGÃO PÚBLICO / EMPRESA -->
        <div class="flex items-center gap-4 border-b border-black pb-2 mb-2">
            <!-- Brasão / Logo -->
            <div class="w-16 h-16 shrink-0 flex items-center justify-center overflow-hidden">
                @if(!empty($companyLogoUrl))
                    <img src="{{ $companyLogoUrl }}" 
                         alt="Logotipo da Empresa" 
                         class="max-h-16 max-w-16 object-contain" 
                         onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');" />
                    <div class="hidden">
                        <svg viewBox="0 0 100 100" class="w-14 h-14" xmlns="http://www.w3.org/2000/svg">
                            <!-- Faixas tricolores estilizadas do município -->
                            <rect x="15" y="10" width="18" height="65" rx="3" fill="#0284c7" />
                            <rect x="38" y="10" width="18" height="65" rx="3" fill="#eab308" />
                            <rect x="61" y="10" width="18" height="65" rx="3" fill="#dc2626" />
                            <path d="M10 82h80v8H10z" fill="#0f172a" />
                            <text x="50" y="98" font-size="7" font-weight="900" text-anchor="middle" fill="#0f172a">TEOTÔNIO VILELA</text>
                        </svg>
                    </div>
                @else
                    <svg viewBox="0 0 100 100" class="w-14 h-14" xmlns="http://www.w3.org/2000/svg">
                        <!-- Faixas tricolores estilizadas do município -->
                        <rect x="15" y="10" width="18" height="65" rx="3" fill="#0284c7" />
                        <rect x="38" y="10" width="18" height="65" rx="3" fill="#eab308" />
                        <rect x="61" y="10" width="18" height="65" rx="3" fill="#dc2626" />
                        <path d="M10 82h80v8H10z" fill="#0f172a" />
                        <text x="50" y="98" font-size="7" font-weight="900" text-anchor="middle" fill="#0f172a">TEOTÔNIO VILELA</text>
                    </svg>
                @endif
            </div>

            <!-- Dados Institucionais Centralizados -->
            <div class="flex-1 text-center text-black leading-tight">
                <div class="text-[12px] font-bold tracking-wider uppercase">{{ $headerState }}</div>
                <div class="text-[14px] font-black uppercase tracking-tight">{{ $headerEntity }}</div>
                <div class="text-[12px] font-bold uppercase">{{ $headerSubEntity }}</div>
                <div class="text-[10px] mt-0.5 font-normal">
                    {{ $headerAddress }} &nbsp; <strong>CNPJ:</strong> {{ $headerCnpj }}
                </div>
                <div class="text-[10px] font-normal">
                    <strong>Telefone:</strong> {{ $headerPhone }} &nbsp; <strong>EMAIL:</strong> {{ $headerEmail }}
                </div>
            </div>
        </div>

        <!-- TÍTULO DO DOCUMENTO -->
        <div class="text-center my-2">
            <h1 class="text-[15px] font-black uppercase tracking-wide">
                FOLHA DE PONTO DE FUNCIONÁRIO
            </h1>
        </div>

        <!-- METADADOS DO SERVIDOR / COMPETÊNCIA (TABELA DE DADOS) -->
        <div class="text-[11px] leading-relaxed mb-2 font-normal space-y-0.5">
            <div class="flex justify-between items-baseline">
                <div>
                    <strong>Competência/{{ $monthNameUpper }}- Ano:</strong> {{ $year }}
                </div>
                <div>
                    <strong>Carga Horária:</strong> {{ $workload ?: '40h' }}
                </div>
                <div class="w-28 text-left">
                    <strong>Zona:</strong> {{ $zone ?: 'Urbana' }}
                </div>
            </div>

            <div class="flex justify-between items-baseline">
                <div class="flex-1 truncate mr-4">
                    <strong>Nome do Servidor:</strong> <span class="uppercase font-semibold">{{ mb_strtoupper($selectedUser->name) }}</span>
                </div>
                <div class="w-48 truncate">
                    <strong>Cargo:</strong> {{ $jobTitle ?: 'Servidor Público' }}
                </div>
                <div class="w-28 text-left">
                    <strong>Vinculo:</strong> {{ $contractType ?: 'Efetivo' }}
                </div>
            </div>

            <div>
                <strong>Local:</strong> {{ $location ?: (optional(optional($selectedUser->employee)->sector)->name ?? 'Secretaria Municipal de Saúde') }}
            </div>
        </div>

        <!-- TABELA DE FREQUÊNCIA (31 DIAS COM SUPER-HEADERS) -->
        <div class="w-full overflow-hidden">
            <table class="w-full border-collapse border border-black text-center text-[10px] leading-tight">
                <thead>
                    <!-- Linha 1 de Cabeçalho: Divisão Matutino / Vespertino -->
                    <tr class="font-bold text-[10px] bg-slate-50 print:bg-transparent">
                        <th colspan="5" class="border border-black py-1 tracking-wide">
                            Horário Matutino
                        </th>
                        <th colspan="4" class="border border-black py-1 tracking-wide">
                            Horário Vespertino
                        </th>
                    </tr>

                    <!-- Linha 2 de Cabeçalho: Colunas Detalhadas -->
                    <tr class="font-bold text-[9px] bg-slate-50 print:bg-transparent">
                        <th class="border border-black py-1 w-[38px]">DIA</th>
                        <th class="border border-black py-1 w-[60px]">ENTR 1</th>
                        <th class="border border-black py-1 w-[85px]">RUBRICA</th>
                        <th class="border border-black py-1 w-[60px]">SAÍDA 1</th>
                        <th class="border border-black py-1 w-[85px]">RUBRICA</th>
                        
                        <th class="border border-black py-1 w-[60px]">ENTR 2</th>
                        <th class="border border-black py-1 w-[85px]">RUBRICA</th>
                        <th class="border border-black py-1 w-[60px]">SAÍDA 2</th>
                        <th class="border border-black py-1 w-[85px]">RUBRICA</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($daysRows as $row)
                        <tr class="h-[18px] sm:h-[19px] print:h-[17px] {{ $row['isSaturday'] || $row['isSunday'] ? 'bg-slate-100/50 print:bg-transparent' : '' }}">
                            <!-- Dia -->
                            <td class="border border-black font-bold text-[9px]">
                                {{ $row['day'] }}
                            </td>

                            <!-- ENTR 1 -->
                            <td class="border border-black font-mono text-[9px] {{ $row['entr1'] && $row['entr1'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                {{ $row['entr1'] }}
                            </td>

                            <!-- RUBRICA 1 -->
                            <td class="border border-black text-[9px] {{ $row['rubrica1'] === 'SÁBADO' || $row['rubrica1'] === 'DOMINGO' ? 'font-bold' : '' }}">
                                {{ $row['rubrica1'] }}
                            </td>

                            <!-- SAÍDA 1 -->
                            <td class="border border-black font-mono text-[9px] {{ $row['saida1'] && $row['saida1'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                {{ $row['saida1'] }}
                            </td>

                            <!-- RUBRICA 2 -->
                            <td class="border border-black text-[9px] {{ $row['rubrica2'] === 'SÁBADO' || $row['rubrica2'] === 'DOMINGO' ? 'font-bold' : '' }}">
                                {{ $row['rubrica2'] }}
                            </td>

                            <!-- ENTR 2 -->
                            <td class="border border-black font-mono text-[9px] {{ $row['entr2'] && $row['entr2'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                {{ $row['entr2'] }}
                            </td>

                            <!-- RUBRICA 3 -->
                            <td class="border border-black text-[9px] {{ $row['rubrica3'] === 'SÁBADO' || $row['rubrica3'] === 'DOMINGO' ? 'font-bold' : '' }}">
                                {{ $row['rubrica3'] }}
                            </td>

                            <!-- SAÍDA 2 -->
                            <td class="border border-black font-mono text-[9px] {{ $row['saida2'] && $row['saida2'] !== ': ' ? 'font-bold' : 'text-slate-400 print:text-black' }}">
                                {{ $row['saida2'] }}
                            </td>

                            <!-- RUBRICA 4 -->
                            <td class="border border-black text-[9px] {{ $row['rubrica4'] === 'SÁBADO' || $row['rubrica4'] === 'DOMINGO' ? 'font-bold' : '' }}">
                                {{ $row['rubrica4'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- BLOCO DE ASSINATURAS (EXATAMENTE COMO NO MODELO PDF) -->
        <div class="mt-8 page-break-avoid text-black">
            <!-- Linha 1: Servidor, Coordenador, Responsável pelo Setor -->
            <div class="grid grid-cols-3 gap-6 text-center text-[10px]">
                <div>
                    <div class="border-b border-black w-4/5 mx-auto mb-1"></div>
                    <span class="font-bold tracking-wider">SERVIDOR (a)</span>
                </div>

                <div>
                    <div class="border-b border-black w-4/5 mx-auto mb-1"></div>
                    <span class="font-bold tracking-wider">Coordenador (a)</span>
                </div>

                <div>
                    <div class="border-b border-black w-4/5 mx-auto mb-1"></div>
                    <span class="font-bold tracking-wider">RESPONSÁVEL PELO SETOR</span>
                </div>
            </div>

            <!-- Linha 2: Setor de Recursos Humanos -->
            <div class="mt-6 text-center text-[10px]">
                <div class="border-b border-black w-56 mx-auto mb-1"></div>
                <span class="font-bold tracking-wider">Setor de Recursos Humanos</span>
            </div>
        </div>

    </div>

</div>

<!-- ESTILOS ESPECÍFICOS DE IMPRESSÃO (A4 1 PÁGINA PERFEITA) -->
<style>
    @media print {
        @page {
            size: A4 portrait;
            margin: 6mm 8mm 6mm 8mm;
        }

        /* Oculta tudo que não faz parte do formulário */
        .no-print,
        header,
        nav,
        aside,
        button,
        footer,
        .fixed,
        [role="dialog"] {
            display: none !important;
        }

        body, html {
            background: #ffffff !important;
            color: #000000 !important;
            margin: 0 !important;
            padding: 0 !important;
            font-size: 10pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Reset all layout wrappers on print */
        div[class*="md:pl-"], div[class*="pl-"], main, .print-wrapper {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        .print-container {
            max-width: 100% !important;
            width: 100% !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 auto !important;
        }

        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }

        th, td {
            border: 1px solid #000000 !important;
            padding-top: 1px !important;
            padding-bottom: 1px !important;
        }

        .page-break-avoid {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
    }
</style>
