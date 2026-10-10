<?php

use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Services\TimesheetJourneyService;
use App\Enums\UserRole;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Espelho de Ponto')] class extends Component
{
    use WithFileUploads;

    public $month;
    public $year;
    public $userId;

    public string $userSearch = '';

    public $showTreatmentModal = false;
    public $reqDate = '';
    public $reqTime = '08:00';
    public $reqType = 'manual_punch_added';
    public $reqReason = '';
    public $reqAttachment = null;

    public function mount(?int $userId = null): void
    {
        $this->month = (int) (request('month') ?? now()->month);
        $this->year = (int) (request('year') ?? now()->year);

        $targetId = $userId ?? (int) request('userId', Auth::id());
        $this->validateAuthorizedUserId($targetId);
        $this->userId = $targetId;
        $this->reqDate = now()->toDateString();
    }

    public function updatingUserId($value): void
    {
        $this->validateAuthorizedUserId((int) $value);
    }

    /**
     * Validação rigorosa de autorização no servidor (anti-tampering de Livewire).
     * - Colaborador: visualiza unicamente o seu próprio espelho.
     * - Gestor: visualiza somente colaboradores dos setores geridos por ele.
     * - Admin: visualiza colaboradores da empresa/instalação dedicada.
     */
    protected function validateAuthorizedUserId(int $targetUserId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            abort(401);
        }

        if ($targetUserId === (int) $currentUser->id) {
            return;
        }

        if ($currentUser->role === UserRole::Admin) {
            if (! User::where('id', $targetUserId)->exists()) {
                abort(404, 'Colaborador não encontrado.');
            }
            return;
        }

        if ($currentUser->role === UserRole::Manager) {
            $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
            $allowedUserIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id');

            if (! $allowedUserIds->contains($targetUserId)) {
                abort(403, 'Acesso não autorizado aos dados deste colaborador.');
            }
            return;
        }

        abort(403, 'Acesso restrito ao próprio espelho de ponto.');
    }

    public function selectUser(int $id): void
    {
        $this->validateAuthorizedUserId($id);
        $this->userId = $id;
        $this->userSearch = '';
    }

    public function openTreatmentModal(?string $date = null): void
    {
        $this->validateAuthorizedUserId((int) $this->userId);

        $parsedDate = $date ? Carbon::parse($date) : now();
        if (ClosedPeriod::isClosed($parsedDate->year, $parsedDate->month)) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Período Fechado',
                'message' => sprintf('A competência %02d/%04d encontra-se fechada e congelada. Solicitações retroativas não são permitidas.', $parsedDate->month, $parsedDate->year),
                'buttonText' => 'Entendido',
            ]);
            return;
        }

        $this->reqDate = $date ?? now()->toDateString();
        $this->reqTime = '08:00';
        $this->reqType = 'manual_punch_added';
        $this->reqReason = '';
        $this->reqAttachment = null;
        $this->showTreatmentModal = true;
    }

    public function submitTreatmentRequest(): void
    {
        $this->validateAuthorizedUserId((int) $this->userId);

        $currentUser = Auth::user();
        // Colaborador comum não pode solicitar ajuste em nome de terceiros
        if ($currentUser->role === UserRole::Employee && (int) $this->userId !== (int) $currentUser->id) {
            abort(403, 'Você não possui permissão para solicitar ajustes para outro colaborador.');
        }

        $this->validate([
            'reqDate' => 'required|date',
            'reqTime' => 'required|date_format:H:i',
            'reqType' => 'required|in:manual_punch_added,absence_justified,punch_disregarded',
            'reqReason' => 'required|string|min:5|max:500',
        ]);

        $effectiveAt = Carbon::parse($this->reqDate . ' ' . $this->reqTime);
        if (ClosedPeriod::isClosed($effectiveAt->year, $effectiveAt->month)) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Competência Fechada',
                'message' => 'Esta competência encontra-se formalmente fechada e congelada fiscalmente. Solicitações de tratamento retroativas não são permitidas.',
                'buttonText' => 'Fechar',
            ]);
            return;
        }

        $employee = Employee::where('user_id', $this->userId)->first();
        if (! $employee) {
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Perfil Incompleto',
                'message' => 'Colaborador não possui cadastro funcional ativo para vincular o tratamento.',
                'buttonText' => 'OK',
            ]);
            return;
        }

        $typeEnum = match ($this->reqType) {
            'manual_punch_added' => TreatmentEventType::ManualPunchAdded,
            'absence_justified' => TreatmentEventType::AbsenceJustified,
            'punch_disregarded' => TreatmentEventType::PunchDisregarded,
        };

        // Armazenamento em disco privado (local) para proteger documentos sensíveis (LGPD)
        $attachmentPath = null;
        if ($this->reqAttachment) {
            $this->validate([
                'reqAttachment' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);
            $attachmentPath = $this->reqAttachment->store('treatment_attachments', 'local');
        }

        try {
            app(\App\Domain\PTRP\Actions\RequestTreatmentEventAction::class)->execute(
                employee: $employee,
                type: $typeEnum,
                effectiveAt: $effectiveAt,
                reasonText: $this->reqReason,
                requestedBy: $currentUser,
                attachmentPath: $attachmentPath,
            );

            $this->showTreatmentModal = false;
            $this->reqAttachment = null;
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Solicitação Enviada com Sucesso!',
                'message' => 'Sua solicitação de tratamento/justificativa foi enviada ao RH para análise e aprovação.',
                'buttonText' => 'OK',
            ]);
        } catch (\Throwable $e) {
            // Em caso de falha, remove arquivo temporário já persistido no disco privado
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }

            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro ao Enviar Solicitação',
                'message' => $e->getMessage(),
                'buttonText' => 'Fechar',
            ]);
        }
    }

    protected function maskCpf(?string $cpf): string
    {
        $clean = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($clean) !== 11) {
            return '***.***.***-**';
        }

        return substr($clean, 0, 3) . '.***.***-' . substr($clean, -2);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->validateAuthorizedUserId((int) $this->userId);
        $currentUser = Auth::user();

        $isAdmin = $currentUser->role === UserRole::Admin;
        $isManager = $currentUser->role === UserRole::Manager;

        $targetUser = User::with('employee.sector')->findOrFail($this->userId);

        // Busca autorizada com paginação e máscara de CPF (sem expor lista total no frontend)
        $canSelectUser = $isAdmin || $isManager;
        $searchResults = collect([]);

        if ($canSelectUser && $this->userSearch !== '') {
            $term = trim($this->userSearch);
            $cleanTerm = preg_replace('/\D/', '', $term);

            $query = User::with('employee')
                ->where(function ($q) use ($term, $cleanTerm) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");

                    if ($cleanTerm !== '') {
                        $q->orWhereHas('employee', fn ($eq) => $eq->where('cpf', 'like', "%{$cleanTerm}%"));
                    }
                });

            if ($isManager) {
                $managedSectorIds = Sector::where('manager_id', $currentUser->id)->pluck('id');
                $allowedIds = Employee::whereIn('sector_id', $managedSectorIds)->pluck('user_id')->push($currentUser->id);
                $query->whereIn('id', $allowedIds);
            }

            $searchResults = $query->orderBy('name')
                ->take(15)
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'masked_cpf' => $this->maskCpf($u->employee?->cpf),
                    'job_title' => $u->employee?->job_title ?? ($u->role ? $u->role->label() : 'Colaborador'),
                ]);
        }

        // Resolução Oficial via Domínio PTRP
        $ptrpData = app(TimesheetJourneyService::class)->resolveMonthData(
            targetUser: $targetUser,
            year: (int) $this->year,
            month: (int) $this->month
        );

        return array_merge($ptrpData, [
            'canSelectUser' => $canSelectUser,
            'targetUser' => $targetUser,
            'searchResults' => $searchResults,
        ]);
    }
};
?>

<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 border-b border-gray-200 pb-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Espelho de Ponto</h2>
                    @if($isClosedPeriod)
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-300">
                            Competência Fechada (Snapshot v{{ $snapshotVersion }})
                        </span>
                    @endif
                </div>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    Histórico auditado sob o PTRP (Portaria 671/2021 MTP) · 
                    <span class="font-semibold text-gray-700">{{ $targetUser->name }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if(! $isClosedPeriod)
                    <button wire:click="openTreatmentModal" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-2xs transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        <span>Solicitar Ajuste</span>
                    </button>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-100 text-gray-500 rounded-xl text-xs font-semibold border border-gray-200 select-none" title="Competência formalmente fechada pelo DP/RH. Solicitações bloqueadas.">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                        Período Fechado
                    </span>
                @endif

                <a href="{{ route('folha-ponto', ['userId' => $this->userId, 'month' => $this->month, 'year' => $this->year]) }}" 
                   class="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs sm:text-sm font-bold shadow-2xs transition">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>Imprimir Folha Oficial</span>
                </a>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    Bater Ponto
                </a>
            </div>
        </div>

        <!-- Filters (Busca Segura no Servidor com Debounce) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6 pb-6 border-b border-gray-200">
            @if($canSelectUser)
            <div class="sm:col-span-2 lg:col-span-1 relative" x-data="{ open: false }">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Colaborador <span class="text-indigo-600 font-normal">(buscar por Nome ou CPF)</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="userSearch"
                           @focus="open = true"
                           @click.outside="open = false"
                           placeholder="Buscar: {{ $targetUser->name }}"
                           class="block w-full pl-9 pr-9 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" />
                    @if($userSearch !== '')
                        <button type="button"
                                wire:click="$set('userSearch', '')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif
                </div>

                <!-- Autocomplete com Resultados Autorizados e CPF Mascarado -->
                @if($searchResults->isNotEmpty() || $userSearch !== '')
                <div x-show="open && $wire.userSearch.length > 0"
                     x-cloak
                     class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto bg-white rounded-xl shadow-xl border border-gray-200 py-1 text-sm divide-y divide-gray-100">
                    @forelse($searchResults as $u)
                        <button type="button"
                                wire:click="selectUser({{ $u['id'] }})"
                                @click="open = false"
                                class="w-full text-left px-3.5 py-2.5 flex items-center justify-between gap-2 transition hover:bg-gray-50 cursor-pointer {{ (int)$userId === (int)$u['id'] ? 'bg-indigo-50/80 font-semibold' : '' }}">
                            <div class="truncate">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $u['name'] }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $u['job_title'] }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-200">
                                    {{ $u['masked_cpf'] }}
                                </span>
                            </div>
                        </button>
                    @empty
                        <div class="px-4 py-3 text-xs text-gray-500 text-center">
                            Nenhum colaborador autorizado localizado com "<span class="font-medium">{{ $userSearch }}</span>".
                        </div>
                    @endforelse
                </div>
                @endif
            </div>
            @endif
            
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Mês</label>
                <select wire:model.live="month" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}">{{ sprintf('%02d', $i) }} - {{ Carbon::create(null, $i, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Ano</label>
                <select wire:model.live="year" class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    @for($i = now()->year - 2; $i <= now()->year + 1; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <!-- Card Banco de Horas (Se ativado na política vigente) -->
        @if($policy && $policy->enabled && $timeBankSummary)
        <div class="bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 text-white rounded-2xl p-5 mb-6 shadow-md border border-indigo-800/60">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-800/80 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold tracking-tight text-white">Banco de Horas</h3>
                        <p class="text-[11px] text-indigo-300">Regra: {{ $policy->closing_mode->label() }}</p>
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-indigo-300 tracking-wider block">Saldo Atual</span>
                    <span class="text-2xl font-black font-mono {{ $timeBankSummary->currentBalanceMinutes >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $timeBankSummary->formattedCurrentBalance() }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-mono">
                <div>
                    <span class="text-indigo-300 text-[10px] uppercase font-sans font-semibold block">Saldo Anterior</span>
                    <span class="font-bold text-slate-200 text-sm">{{ $timeBankSummary->formattedPreviousBalance() }}</span>
                </div>
                <div>
                    <span class="text-emerald-300 text-[10px] uppercase font-sans font-semibold block">Créditos do Mês</span>
                    <span class="font-bold text-emerald-400 text-sm">+{{ $timeBankSummary->formattedMonthCredits() }}</span>
                </div>
                <div>
                    <span class="text-rose-300 text-[10px] uppercase font-sans font-semibold block">Débitos do Mês</span>
                    <span class="font-bold text-rose-400 text-sm">{{ $timeBankSummary->formattedMonthDebits() }}</span>
                </div>
                <div>
                    <span class="text-amber-300 text-[10px] uppercase font-sans font-semibold block">Ajustes Manuais</span>
                    <span class="font-bold text-amber-400 text-sm">{{ $timeBankSummary->formattedMonthAdjustments() }}</span>
                </div>
            </div>

            @if($timeBankSummary->closingResetMinutes !== 0)
                <div class="mt-4 pt-3 border-t border-indigo-800/80 flex flex-wrap items-center justify-between gap-2 text-xs font-mono bg-indigo-950/60 p-2.5 rounded-xl">
                    <span class="text-purple-300 font-sans font-bold">Encerramento mensal / Zeramento formal:</span>
                    <span class="font-bold text-purple-300">{{ $timeBankSummary->formattedClosingReset() }}</span>
                    <span class="text-indigo-200 font-sans">Saldo transportado: <strong>00:00</strong></span>
                </div>
            @endif
        </div>
        @endif

        <!-- Monthly Summary KPI Cards -->
        @if($totalPunches > 0 || $totalWorkedMinutes > 0)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <div class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <p class="text-xs font-semibold text-indigo-900 uppercase tracking-wider">Total Trabalhado</p>
                        @if($isPartial)
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200" title="Apuração parcial: existem dias com pendência ou jornadas incompletas no mês">Parcial</span>
                        @endif
                    </div>
                    <p class="text-xl font-black text-indigo-950 font-mono">{{ $totalMonthFormatted }}</p>
                    @if($isClosedPeriod)
                        <p class="text-[10px] text-indigo-700/80 mt-0.5">Apuração formal congelada</p>
                    @else
                        <p class="text-[10px] text-gray-500 mt-0.5">Apuração minuto a minuto PTRP</p>
                    @endif
                </div>
            </div>

            <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-emerald-900 uppercase tracking-wider">Dias Trabalhados</p>
                    <p class="text-xl font-black text-emerald-950 font-mono">{{ $workedDaysCount }} {{ $workedDaysCount === 1 ? 'dia' : 'dias' }}</p>
                    <p class="text-[10px] text-gray-500 mt-0.5" title="Apenas datas com jornada concluída e apurada">
                        {{ $completedDaysCount }} concluídas @if($incompleteDaysCount > 0) · <span class="text-rose-600 font-bold">{{ $incompleteDaysCount }} incompleta(s)</span>@endif
                    </p>
                </div>
            </div>

            <div class="bg-purple-50/60 border border-purple-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-600 text-white flex items-center justify-center flex-shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-purple-900 uppercase tracking-wider">Média por Dia</p>
                    <p class="text-xl font-black text-purple-950 font-mono">{{ $avgFormatted }}</p>
                    <p class="text-[10px] text-gray-500 mt-0.5 truncate max-w-[220px]" title="{{ $avgCriteria }}">{{ $avgCriteria }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Timesheet Data -->
        <div class="space-y-4" data-loading-class="opacity-50" wire:transition>
            @forelse($groupedEntries as $date => $dayEntries)
                <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs" wire:key="day-{{ $date }}">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                        <h3 class="text-sm font-bold text-gray-800 capitalize flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            {{ Carbon::parse($date)->isoFormat('dddd, LL') }}
                        </h3>
                        <div class="flex items-center gap-2 flex-wrap">
                            @if(isset($daysCalculated[$date]))
                                @php $dayCalc = $daysCalculated[$date]; @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $dayCalc['status_badge_class'] }}">
                                    @if($dayCalc['is_open'])
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    @endif
                                    @if($dayCalc['status'] === 'concluded')
                                        {{ $dayCalc['formatted'] }} trabalhadas
                                    @else
                                        {{ $dayCalc['formatted'] }} ({{ $dayCalc['status_label'] }})
                                    @endif
                                </span>
                            @endif
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                {{ count($dayEntries) }} {{ count($dayEntries) === 1 ? 'registro' : 'registros' }}
                            </span>
                        </div>
                    </div>

                    @if(isset($daysCalculated[$date]['notes']) && count($daysCalculated[$date]['notes']) > 0)
                        <div class="bg-indigo-50/40 px-4 py-1.5 border-b border-indigo-100 text-[11px] text-indigo-800 space-y-0.5">
                            @foreach($daysCalculated[$date]['notes'] as $note)
                                <p class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                    {{ $note }}
                                </p>
                            @endforeach
                        </div>
                    @endif

                    <ul class="divide-y divide-gray-100">
                        @foreach($dayEntries as $entry)
                            <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-gray-50 transition" wire:key="entry-{{ $entry->id }}">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold {{ $entry->type === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $entry->type === 'in' ? 'Entrada' : 'Saída' }}
                                    </span>
                                    <span class="text-base text-gray-900 font-mono font-bold">
                                        {{ Carbon::parse($entry->timestamp)->format('H:i:s') }}
                                    </span>
                                    @if($entry->is_manual)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 text-amber-800">
                                            Ajuste Manual
                                        </span>
                                    @endif
                                    @if($entry->nsr)
                                        <span class="text-xs font-mono text-gray-400">
                                            NSR #{{ str_pad((string)$entry->nsr, 9, '0', STR_PAD_LEFT) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3">
                                    @if($entry->has_valid_location)
                                        <div class="text-xs text-gray-500 font-mono flex items-center gap-1" title="Coordenadas geográficas registradas no ponto">
                                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                            <span>Lat: {{ number_format($entry->latitude, 4) }}, Lng: {{ number_format($entry->longitude, 4) }}</span>
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400 font-sans flex items-center gap-1" title="Sem coordenadas de GPS registradas no ato da batida">
                                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                            <span class="italic">Localização não disponível</span>
                                        </div>
                                    @endif

                                    @if(isset($entry->receipt) && $entry->receipt)
                                        <a href="{{ route('receipts.pdf', ['code' => $entry->receipt->verification_code]) }}"
                                           target="_blank"
                                           title="Baixar comprovante fiscal oficial (PDF)"
                                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition">
                                            <span>Comprovante</span>
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="text-center py-12 text-gray-500 bg-gray-50 rounded-xl" wire:key="empty-state">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <p class="text-sm font-medium text-gray-600">Nenhum registro encontrado para este período.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal Solicitação de Tratamento / Justificativa -->
    @if($showTreatmentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">Solicitar Ajuste ou Justificativa</h3>
                    </div>
                    <button wire:click="$set('showTreatmentModal', false)" class="text-gray-400 hover:text-gray-600 cursor-pointer">✕</button>
                </div>

                <form wire:submit="submitTreatmentRequest" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Tipo de Solicitação</label>
                        <select wire:model="reqType" class="w-full px-3 py-2.5 border rounded-xl bg-white font-medium">
                            <option value="manual_punch_added">Inclusão de Batida Manual (Esquecimento)</option>
                            <option value="absence_justified">Abono de Falta / Atestado Médico</option>
                            <option value="punch_disregarded">Desconsideração de Marcação Indevida</option>
                        </select>
                        @error('reqType') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Data da Ocorrência</label>
                            <input type="date" wire:model="reqDate" class="w-full px-3 py-2 border rounded-xl font-mono" required>
                            @error('reqDate') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 uppercase mb-1">Horário Previsto</label>
                            <input type="time" wire:model="reqTime" class="w-full px-3 py-2 border rounded-xl font-mono" required>
                            @error('reqTime') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Justificativa e Motivo Legal (Obrigatório)</label>
                        <textarea wire:model="reqReason" rows="3" class="w-full px-3 py-2 border rounded-xl" placeholder="Descreva detalhadamente a justificativa para avaliação do RH/Gestor..." required></textarea>
                        @error('reqReason') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 uppercase mb-1">Anexo / Atestado Médico (Opcional / Obrigatório para Abono)</label>
                        <input type="file" wire:model="reqAttachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-xl p-1 bg-gray-50">
                        <p class="text-[10px] text-gray-400 mt-1">Armazenamento seguro e confidencial. Formatos aceitos: PDF, PNG, JPG (máx. 5MB). Indispensável para auditoria do RH e abono legal de faltas.</p>
                        @error('reqAttachment') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        <div wire:loading wire:target="reqAttachment" class="text-[11px] text-indigo-600 font-semibold mt-1">
                            Enviando anexo confidencial... aguarde.
                        </div>
                    </div>

                    <div class="p-3 bg-indigo-50/70 border border-indigo-100 rounded-xl text-indigo-900 leading-relaxed">
                        Sua solicitação será enviada com carimbo de auditoria e status <strong>Pendente</strong>, sendo validada pelo Gestor do Setor e pela Coordenação de RH antes da apuração final.
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" wire:click="$set('showTreatmentModal', false)" class="px-4 py-2 border rounded-xl font-bold text-gray-600 hover:bg-gray-50 cursor-pointer">Cancelar</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-sm cursor-pointer">Enviar Solicitação</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>