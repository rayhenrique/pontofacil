<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\PunchEvent;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\TimeClock\Actions\DetermineNextPunchDirectionAction;
use App\Domain\TimeClock\Actions\RecordPunchEventAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Registro de Ponto')] class extends Component
{
    public $message = '';
    public $status = '';
    public string $establishmentTimezone = '';
    public string $timezoneLabel = '';
    public string $greetingText = '';
    public string $currentDateFormatted = '';
    public ?array $confirmationData = null;
    public bool $hasHistoryLoadError = false;

    public function mount()
    {
        $user = Auth::user();
        $employee = $user ? $user->employee : null;
        $sector = $employee ? $employee->sector : null;
        $establishment = $sector?->establishment ?? CurrentCompany::defaultEstablishment();

        $this->establishmentTimezone = $establishment?->resolvedTimezone() ?? config('app.timezone', 'America/Maceio');
        $now = Carbon::now($this->establishmentTimezone);
        $offset = $now->format('P');
        $this->timezoneLabel = "Horário do estabelecimento ({$offset})";

        $hour = (int) $now->format('H');
        $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
        $firstName = $user ? explode(' ', trim($user->name))[0] : 'colaborador';
        $this->greetingText = "{$greeting}, {$firstName}";
        $this->currentDateFormatted = ucfirst($now->translatedFormat('l, d \d\e F'));
    }

    /**
     * Retorna a última marcação histórica do colaborador autenticado para destaque no card.
     */
    public function getLatestPunchProperty(): ?PunchEvent
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        try {
            return PunchEvent::with('receipt')
                ->where('user_id', $user->id)
                ->orderBy('occurred_at_utc', 'desc')
                ->first();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Retorna os registros reais de hoje do colaborador autenticado para exibição no painel de jornada.
     */
    public function getTodayPunchesProperty()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        try {
            $tz = $this->establishmentTimezone ?: config('app.timezone', 'America/Maceio');
            $todayDateStr = Carbon::now($tz)->toDateString();

            return PunchEvent::with('receipt')
                ->where('user_id', $user->id)
                ->whereDate('occurred_at_local', $todayDateStr)
                ->orderBy('occurred_at_local', 'asc')
                ->get();
        } catch (\Throwable $e) {
            report($e);
            $this->hasHistoryLoadError = true;
            return collect();
        }
    }

    /**
     * Consulta o status de uma tentativa de marcação para reconciliação pós-falha de rede.
     */
    public function checkPunchStatus(?string $idempotencyKey = null): ?array
    {
        if (! $idempotencyKey) {
            return null;
        }

        $punch = PunchEvent::with('receipt')
            ->where('user_id', Auth::id())
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $punch) {
            return null;
        }

        $tz = $punch->timezone ?: $this->establishmentTimezone;
        $tipoStr = $punch->direction === 'in' ? 'Entrada' : 'Saída';
        $nsrFormatted = str_pad((string) $punch->nsr, 9, '0', STR_PAD_LEFT);

        $this->status = 'success';
        $this->message = "Ponto confirmado via reconciliação! ({$tipoStr} às " . $punch->occurred_at_local->format('H:i:s') . " - NSR #{$nsrFormatted})";

        $reconciledData = [
            'id' => $punch->id,
            'direction' => $punch->direction,
            'direction_title' => $punch->direction === 'in' ? 'Entrada registrada' : 'Saída registrada',
            'direction_badge' => $punch->direction === 'in' ? 'Entrada' : 'Saída',
            'time' => $punch->occurred_at_local->format('H:i:s'),
            'date' => $punch->occurred_at_local->translatedFormat('d \d\e F \d\e Y'),
            'nsr' => $punch->nsr,
            'nsr_formatted' => $nsrFormatted,
            'timezone' => $tz,
            'status' => 'success',
            'has_warning' => $punch->location_valid === false,
            'warning_message' => $punch->location_valid === false ? 'Registro confirmado com observação de perímetro na auditoria.' : null,
            'receipt_status' => $punch->receipt ? 'available' : 'pending',
            'receipt_code' => $punch->receipt?->verification_code,
            'receipt_url' => $punch->receipt ? route('receipts.pdf', ['code' => $punch->receipt->verification_code]) : null,
            'occurred_at_local' => $punch->occurred_at_local->toIso8601String(),
        ];

        $this->confirmationData = $reconciledData;

        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Ponto Reconciliado com Sucesso!',
            'message' => "Sua {$tipoStr} foi confirmada às " . $punch->occurred_at_local->format('H:i:s') . " (NSR #{$nsrFormatted}) no fuso horário {$tz}.",
            'buttonText' => 'Concluir'
        ]);

        return $reconciledData;
    }

    public function registerPunch($qrCodeHash, $latitude = null, $longitude = null, $accuracy = null, ?string $idempotencyKey = null)
    {
        $user = Auth::user();
        $employee = $user ? $user->employee : null;
        $sector = $employee ? $employee->sector : null;
        $establishment = $sector?->establishment ?? CurrentCompany::defaultEstablishment();

        $settings = SystemSetting::whereIn('key', [
            'qr_code_hash', 'company_latitude', 'company_longitude', 'allowed_radius_meters'
        ])->pluck('value', 'key');

        // 1. Resolução do QR Code esperado (Regra Híbrida: Setor -> Global)
        $expectedQrCode = ($sector && !empty($sector->qr_code_hash))
            ? $sector->qr_code_hash
            : ($settings['qr_code_hash'] ?? null);

        if ($qrCodeHash !== $expectedQrCode) {
            $this->message = "QR Code inválido ou não autorizado para o seu setor.";
            $this->status = 'error';
            $this->confirmationData = null;
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'QR Code Não Autorizado',
                'message' => 'O código escaneado não é válido para o seu setor ou matriz da empresa.',
                'buttonText' => 'Escanear Novamente'
            ]);
            return [
                'success' => false,
                'status' => 'error',
                'message' => $this->message,
            ];
        }

        // 2. Resolução das Coordenadas e Raio esperados (Sem inventar coordenadas de Maceió quando não configuradas)
        $hasSectorLocation = $sector && $sector->latitude !== null && $sector->longitude !== null;
        $hasCompanyLocation = !empty($settings['company_latitude']) && !empty($settings['company_longitude']);

        $targetLatitude = null;
        $targetLongitude = null;
        $allowedRadius = null;
        $hasReferenceLocation = false;
        $locationName = '';

        if ($hasSectorLocation) {
            $targetLatitude = (float) $sector->latitude;
            $targetLongitude = (float) $sector->longitude;
            $allowedRadius = ($sector->allowed_radius_meters !== null)
                ? (float) $sector->allowed_radius_meters
                : (!empty($settings['allowed_radius_meters']) ? (float) $settings['allowed_radius_meters'] : 100.0);
            $hasReferenceLocation = true;
            $locationName = "do seu setor ({$sector->name})";
        } elseif ($hasCompanyLocation) {
            $targetLatitude = (float) $settings['company_latitude'];
            $targetLongitude = (float) $settings['company_longitude'];
            $allowedRadius = !empty($settings['allowed_radius_meters']) ? (float) $settings['allowed_radius_meters'] : 100.0;
            $hasReferenceLocation = true;
            $locationName = "da empresa";
        }

        $distance = null;
        $locationValid = null;
        if ($latitude !== null && $longitude !== null) {
            if ($hasReferenceLocation) {
                $distance = $this->calculateDistance(
                    (float) $latitude,
                    (float) $longitude,
                    $targetLatitude,
                    $targetLongitude
                );
                $locationValid = ($distance <= $allowedRadius);
            } else {
                // Perímetro não configurado: registra evidência física sem calcular distância contra localização fictícia
                $distance = null;
                $locationValid = null;
            }
        }

        // 3. Determinação robusta da direção de batida baseada na sequência persistida do colaborador (PunchEvent)
        $type = app(DetermineNextPunchDirectionAction::class)->execute($user);

        // 4. Fuso horário do estabelecimento ou fallback oficial da aplicação
        $tz = $establishment?->resolvedTimezone() ?? config('app.timezone', 'America/Maceio');

        // 5. Gravação atômica única através do RecordPunchEventAction (REP-P -> ARP -> Projeção TimeEntry)
        $nsrFormatted = null;
        $nowInTz = Carbon::now($tz);
        try {
            $recordPunchAction = app(RecordPunchEventAction::class);
            $punchEvent = $recordPunchAction->execute(
                user: $user,
                direction: $type,
                latitude: $latitude !== null ? (float) $latitude : null,
                longitude: $longitude !== null ? (float) $longitude : null,
                accuracy: $accuracy !== null ? (float) $accuracy : null,
                qrLocationValid: true,
                locationValid: $locationValid,
                source: 'web_pwa',
                establishment: $establishment,
                locationDistanceMeters: $distance !== null ? (float) round($distance, 2) : null,
                idempotencyKey: $idempotencyKey
            );
            $nsrFormatted = str_pad((string) $punchEvent->nsr, 9, '0', STR_PAD_LEFT);
            $nowInTz = $punchEvent->occurred_at_local;
        } catch (\Throwable $e) {
            report($e);
            $this->status = 'error';
            $this->message = "Falha ao registrar ponto oficial REP-P. Nenhuma marcação foi registrada. Tente novamente.";
            $this->confirmationData = null;
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro no Registro REP-P',
                'message' => 'Ocorreu uma falha ao persistir a marcação oficial. Nenhuma batida foi gravada. Por favor, tente novamente.',
                'buttonText' => 'Entendido'
            ]);
            return [
                'success' => false,
                'status' => 'error',
                'message' => $this->message,
            ];
        }

        $tipoStr = $type === 'in' ? 'Entrada' : 'Saída';
        $nsrInfo = $nsrFormatted ? " (NSR #{$nsrFormatted})" : '';

        $distRounded = $distance !== null ? round($distance) : null;
        $radiusRounded = $allowedRadius !== null ? round($allowedRadius) : null;

        // 6. Montagem dos dados persistidos para a confirmação operacional
        $this->confirmationData = [
            'id' => $punchEvent->id,
            'direction' => $punchEvent->direction,
            'direction_title' => $type === 'in' ? 'Entrada registrada' : 'Saída registrada',
            'direction_badge' => $type === 'in' ? 'Entrada' : 'Saída',
            'time' => $nowInTz->format('H:i:s'),
            'date' => $nowInTz->translatedFormat('d \d\e F \d\e Y'),
            'nsr' => $punchEvent->nsr,
            'nsr_formatted' => $nsrFormatted,
            'timezone' => $tz,
            'status' => $locationValid === false ? 'warning' : 'success',
            'has_warning' => $locationValid === false,
            'warning_message' => $locationValid === false
                ? "O GPS indicou que você estava a {$distRounded}m {$locationName} (raio autorizado: {$radiusRounded}m). Esta evidência foi registrada para fins de auditoria, sem invalidar seu ponto."
                : null,
            'receipt_status' => $punchEvent->receipt ? 'available' : 'pending',
            'receipt_code' => $punchEvent->receipt?->verification_code,
            'receipt_url' => $punchEvent->receipt ? route('receipts.pdf', ['code' => $punchEvent->receipt->verification_code]) : null,
        ];

        // 7. Alerta de Auditoria se fora do raio (não impede o registro nem altera horário oficial)
        if ($locationValid === false) {
            $this->message = "Ponto registrado com aviso! ({$tipoStr} às " . $nowInTz->format('H:i:s') . "{$nsrInfo} - Fora do raio permitido)";
            $this->status = 'warning';
            $this->dispatch('app-modal-alert', [
                'type' => 'warning',
                'title' => 'Ponto Registrado (Fora da Área Permitida)',
                'message' => "Sua {$tipoStr} foi confirmada com sucesso às " . $nowInTz->format('H:i:s') . "{$nsrInfo}, porém o GPS indicou que você estava a {$distRounded}m {$locationName} (raio autorizado: {$radiusRounded}m). Esta evidência foi registrada para fins de auditoria.",
                'buttonText' => 'Concluir'
            ]);
        } else {
            $this->message = "Ponto registrado com sucesso! ({$tipoStr} às " . $nowInTz->format('H:i:s') . "{$nsrInfo})";
            $this->status = 'success';
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Ponto Registrado com Sucesso!',
                'message' => "Sua {$tipoStr} foi confirmada às " . $nowInTz->format('H:i:s') . "{$nsrInfo} no fuso horário {$tz} (GMT" . $nowInTz->format('P') . ").",
                'buttonText' => 'Concluir'
            ]);
        }

        return [
            'success' => true,
            'status' => $this->status,
            'punch' => $this->confirmationData,
        ];
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
};
?>

<div class="time-punch-module w-full max-w-5xl mx-auto py-2 sm:py-4 px-2 sm:px-4"
     x-data="timePunchComponent({ 
         timezone: '{{ $establishmentTimezone }}', 
         timezoneLabel: '{{ $timezoneLabel }}' 
     })">
    
    <!-- Anunciador de acessibilidade para tecnologias assistivas -->
    <div class="sr-only" aria-live="polite" x-text="statusAnnouncement"></div>

    <!-- Header Superior (Minimalista, Funcional e Não-Decorativo) -->
    <div class="mb-4 sm:mb-6 px-1">
        <!-- Linha 1: Data Compacta -->
        <p class="text-xs sm:text-sm font-medium text-gray-500 tracking-normal">
            {{ $currentDateFormatted }}
        </p>

        <!-- Linha 2: Relógio Digital com Números Tabulares e Ícone Discreto -->
        <div class="flex items-center justify-between mt-0.5">
            <div class="font-mono text-3xl min-[360px]:text-4xl sm:text-5xl font-extrabold text-gray-950 tracking-tight tabular-nums" x-text="currentTime">
                {{ Carbon::now($establishmentTimezone)->format('H:i:s') }}
            </div>
            <div class="text-gray-400 p-1" title="Horário de referência do estabelecimento">
                <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </div>

        <!-- Linha 3: Identificação Contextual do Fuso Horário e Saudação -->
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1 text-[11px] sm:text-xs text-gray-500 font-medium">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span x-text="timezoneLabel">{{ $timezoneLabel }}</span>
            </span>
            <span class="text-gray-300 hidden sm:inline">&bull;</span>
            <span class="text-gray-600 hidden sm:inline">{{ $greetingText }}</span>
        </div>
    </div>

    <!-- Composição Responsiva: 2 Colunas no Desktop / Empilhamento no Mobile -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-start">
        
        <!-- Coluna Esquerda (Mobile: Topo / Desktop: lg:col-span-5 xl:col-span-5): Card de Registro e Scanner -->
        <div class="lg:col-span-5 xl:col-span-5 bg-white rounded-2xl border border-gray-200/80 p-4 sm:p-6 shadow-xs flex flex-col justify-between">
            <div>
                <!-- Cabeçalho do Card (Conforme Wireframe 1) -->
                <div class="text-center pb-2">
                    <h2 class="text-base sm:text-lg font-bold text-gray-900 tracking-tight">Registrar ponto</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Leia o QR Code disponibilizado pela empresa</p>
                </div>

                <!-- Alertas Inline de Feedback -->
                @if($message)
                    <div class="mt-3 rounded-xl p-3 flex items-start gap-2.5 text-left text-xs sm:text-sm {{ $status === 'success' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : ($status === 'warning' ? 'bg-amber-50 text-amber-900 border border-amber-200' : 'bg-red-50 text-red-900 border border-red-200') }}">
                        <div class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 mt-0.5 {{ $status === 'success' ? 'text-emerald-600' : ($status === 'warning' ? 'text-amber-600' : 'text-red-600') }}">
                            @if($status === 'success')
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            @elseif($status === 'warning')
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            @else
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            @endif
                        </div>
                        <p class="font-medium flex-1">{{ $message }}</p>
                    </div>
                @endif

                <!-- Área Central do Viewfinder / Scanner (Proporção Quadrada, Borda Discreta) -->
                <div class="my-4">
                    <div class="relative w-full max-w-[220px] sm:max-w-[250px] aspect-square mx-auto overflow-hidden rounded-2xl border-2 border-dashed transition-all duration-200 shadow-inner flex items-center justify-center bg-slate-50"
                         :class="{
                             'border-slate-200': state === 'idle',
                             'border-indigo-400': state === 'starting' || state === 'scanning',
                             'border-emerald-400': state === 'location' || state === 'submitting',
                             'border-red-300': state === 'error',
                             'border-amber-300': state === 'unknown'
                         }">
                        
                        <!-- Elemento real de vídeo do Html5Qrcode -->
                        <div id="qr-reader" 
                             class="absolute inset-0 w-full h-full overflow-hidden rounded-2xl flex items-center justify-center [&_video]:w-full [&_video]:h-full [&_video]:object-cover [&_video]:rounded-2xl [&_#qr-reader__scan_region]:w-full [&_#qr-reader__scan_region]:h-full"></div>
                        
                        <!-- Estado 1: Idle (Leitura não iniciada - Ícone de retícula simples conforme Wireframe) -->
                        <div x-show="state === 'idle'" class="absolute inset-0 z-10 bg-slate-50 flex flex-col items-center justify-center p-4 text-center select-none text-gray-400">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-2 text-gray-500">
                                <svg class="w-10 h-10 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.875 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 14.25h1.5v1.5h-1.5zM18.75 14.25h1.5v1.5h-1.5zM14.25 18.75h1.5v1.5h-1.5zM18.75 18.75h1.5v1.5h-1.5zM16.5 16.5h1.5v1.5h-1.5z" />
                                </svg>
                            </div>
                            <p class="text-xs sm:text-sm font-semibold text-gray-700">Leitura não iniciada</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Toque no botão abaixo para ativar a câmera</p>
                        </div>

                        <!-- Estado 2: Starting (Ativando câmera) -->
                        <div x-show="state === 'starting'" style="display: none;" class="absolute inset-0 z-10 bg-indigo-50/90 backdrop-blur-xs flex flex-col items-center justify-center p-4 text-center select-none">
                            <div class="inline-flex p-3 rounded-full bg-indigo-100 text-indigo-600 mb-2">
                                <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <p class="text-xs font-bold text-gray-800">Ativando câmera...</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">Solicitando permissão no navegador</p>
                        </div>

                        <!-- Estado 3: Scanning Overlay (Aponte para o QR Code) -->
                        <div x-show="state === 'scanning'" style="display: none;" class="pointer-events-none absolute inset-0 z-20 flex flex-col items-center justify-between p-3 text-indigo-500">
                            <div class="w-full flex justify-between">
                                <div class="w-4 h-4 border-t-2 border-l-2 border-indigo-600 rounded-tl"></div>
                                <div class="w-4 h-4 border-t-2 border-r-2 border-indigo-600 rounded-tr"></div>
                            </div>
                            <span class="text-[10px] sm:text-[11px] font-semibold text-white bg-gray-900/70 px-2.5 py-0.5 rounded-full backdrop-blur-xs">
                                Aponte para o QR Code
                            </span>
                            <div class="w-full flex justify-between">
                                <div class="w-4 h-4 border-b-2 border-l-2 border-indigo-600 rounded-bl"></div>
                                <div class="w-4 h-4 border-b-2 border-r-2 border-indigo-600 rounded-br"></div>
                            </div>
                        </div>

                        <!-- Estado 4: Location (QR Code decodificado, obtendo evidência) -->
                        <div x-show="state === 'location'" style="display: none;" class="absolute inset-0 z-10 bg-slate-50 flex flex-col items-center justify-center p-4 text-center select-none">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <p class="text-xs font-bold text-gray-900">QR Code identificado</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">Obtendo localização (opcional)...</p>
                        </div>

                        <!-- Estado 5: Submitting (Enviando marcação) -->
                        <div x-show="state === 'submitting'" style="display: none;" class="absolute inset-0 z-10 bg-slate-50 flex flex-col items-center justify-center p-4 text-center select-none">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <p class="text-xs font-bold text-gray-900">Registrando marcação...</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">Preservando registro oficial REP-P</p>
                        </div>

                        <!-- Estado 6: Error (Falha confirmada de câmera ou código) -->
                        <div x-show="state === 'error'" style="display: none;" class="absolute inset-0 z-10 bg-red-50/95 flex flex-col items-center justify-center p-4 text-center select-none">
                            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            </div>
                            <p class="text-xs font-bold text-red-900" x-text="errorMessage || 'Não foi possível ler o código'"></p>
                            <p class="text-[11px] text-red-700/80 mt-1 max-w-[200px]" x-text="errorAdvice || 'Verifique o código e tente novamente.'"></p>
                        </div>

                        <!-- Estado 7: Unknown (Instabilidade de rede com status desconhecido) -->
                        <div x-show="state === 'unknown'" style="display: none;" class="absolute inset-0 z-10 bg-amber-50/95 flex flex-col items-center justify-center p-4 text-center select-none">
                            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            </div>
                            <p class="text-xs font-bold text-amber-900">Conexão instável</p>
                            <p class="text-[11px] text-amber-800 mt-1 max-w-[200px]">Não foi possível confirmar o resultado da solicitação.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação Dinâmicos por Estado (Área de Toque Confortável >= 48px) -->
            <div class="pt-2">
                <!-- Botão 1: Idle -> Iniciar Leitura -->
                <button x-show="state === 'idle'"
                        @click="startScanner($el)"
                        type="button"
                        class="w-full min-h-[48px] inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm sm:text-base font-bold rounded-xl shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 transition touch-manipulation cursor-pointer">
                    Iniciar leitura do QR Code
                </button>

                <!-- Botão 2: Starting ou Scanning -> Cancelar Leitura -->
                <button x-show="state === 'starting' || state === 'scanning'"
                        style="display: none;"
                        @click="stopScanner()"
                        type="button"
                        class="w-full min-h-[48px] inline-flex items-center justify-center px-6 py-3 border border-rose-200 text-sm sm:text-base font-bold rounded-xl shadow-xs text-rose-700 bg-rose-50 hover:bg-rose-100 active:bg-rose-200 transition touch-manipulation cursor-pointer">
                    <svg class="w-5 h-5 mr-2 -ml-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Cancelar leitura
                </button>

                <!-- Botão 3: Location ou Submitting -> Desabilitado com Progresso -->
                <button x-show="state === 'location' || state === 'submitting'"
                        style="display: none;"
                        disabled
                        type="button"
                        class="w-full min-h-[48px] inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm sm:text-base font-bold rounded-xl text-white bg-indigo-500/80 cursor-not-allowed">
                    <svg class="animate-spin w-5 h-5 mr-2 -ml-1 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="state === 'location' ? 'Obtendo localização...' : 'Registrando marcação...'"></span>
                </button>

                <!-- Botão 4: Error -> Tentar Novamente -->
                <button x-show="state === 'error'"
                        style="display: none;"
                        @click="resetToIdle(); startScanner($el);"
                        type="button"
                        class="w-full min-h-[48px] inline-flex items-center justify-center px-6 py-3 border border-transparent text-sm sm:text-base font-bold rounded-xl shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 transition touch-manipulation cursor-pointer">
                    <svg class="w-5 h-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Tentar novamente
                </button>

                <!-- Botão 5: Unknown -> Ações de Reconciliação sem Duplicidade -->
                <div x-show="state === 'unknown'" style="display: none;" class="flex flex-col sm:flex-row gap-2 w-full">
                    <button @click="verifyUnknownStatus()"
                            :disabled="reconciling"
                            type="button"
                            class="flex-1 min-h-[44px] inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition cursor-pointer">
                        <span x-text="reconciling ? 'Verificando...' : 'Verificar se foi registrado'"></span>
                    </button>
                    <button @click="retrySameAttempt()"
                            :disabled="reconciling"
                            type="button"
                            class="min-h-[44px] inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition cursor-pointer">
                        Reenviar
                    </button>
                    <button @click="resetToIdle()"
                            type="button"
                            class="min-h-[44px] inline-flex items-center justify-center px-3 py-2.5 rounded-xl text-xs font-semibold text-gray-500 hover:text-gray-700 transition cursor-pointer">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>

        <!-- Coluna Direita (Mobile: Abaixo do Scanner / Desktop: lg:col-span-7 xl:col-span-7): Contexto Operacional -->
        <div class="lg:col-span-7 xl:col-span-7 space-y-4 sm:space-y-5">
            
            <!-- Card 1: Última Marcação (Conforme Wireframe 2 do Usuário) -->
            <div class="bg-white rounded-2xl border border-gray-200/80 p-4 sm:p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm sm:text-base font-bold text-gray-900 tracking-tight">Última marcação</h3>
                    @if($this->latestPunch)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold {{ $this->latestPunch->location_valid === false ? 'text-amber-700' : 'text-emerald-700' }}">
                            <svg class="w-3.5 h-3.5 {{ $this->latestPunch->location_valid === false ? 'text-amber-600' : 'text-emerald-600' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span>{{ $this->latestPunch->location_valid === false ? 'Com observação' : 'Confirmada' }}</span>
                        </span>
                    @else
                        <span class="text-xs text-gray-400 font-medium">Nenhum registro</span>
                    @endif
                </div>

                @if($this->latestPunch)
                    <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm sm:text-base font-bold text-gray-900">
                                {{ $this->latestPunch->direction === 'in' ? 'Entrada' : 'Saída' }} &middot; <span class="font-mono tabular-nums">{{ $this->latestPunch->occurred_at_local->format('H:i') }}</span>
                            </span>
                            <span class="text-[11px] text-gray-400 font-mono hidden sm:inline">
                                (NSR #{{ str_pad($this->latestPunch->nsr, 9, '0', STR_PAD_LEFT) }})
                            </span>
                        </div>
                        <div>
                            @if($this->latestPunch->receipt)
                                <a href="{{ route('receipts.pdf', ['code' => $this->latestPunch->receipt->verification_code]) }}"
                                   target="_blank"
                                   class="text-xs sm:text-sm font-bold text-gray-900 hover:text-indigo-600 transition inline-flex items-center gap-1 group">
                                    <span>Comprovante</span>
                                    <span class="text-gray-400 group-hover:text-indigo-600 font-normal">&nearr;</span>
                                </a>
                            @else
                                <a href="{{ route('receipts.center') }}"
                                   class="text-xs sm:text-sm font-bold text-gray-900 hover:text-indigo-600 transition inline-flex items-center gap-1 group">
                                    <span>Comprovantes</span>
                                    <span class="text-gray-400 group-hover:text-indigo-600 font-normal">&nearr;</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-500 mt-2 pt-2 border-t border-gray-100">
                        Você ainda não possui marcações neste período.
                    </p>
                @endif
            </div>

            <!-- Card 2: Sua Jornada Hoje (Registros Reais do Dia) -->
            <div class="bg-white rounded-2xl border border-gray-200/80 p-4 sm:p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="pb-3 mb-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-gray-900 tracking-tight">Sua jornada hoje</h3>
                            <p class="text-xs text-gray-500">Histórico de batidas no estabelecimento</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            {{ count($this->todayPunches) }} {{ count($this->todayPunches) === 1 ? 'registro' : 'registros' }}
                        </span>
                    </div>

                    <!-- Conteúdo: Falha de carregamento vs Vazio vs Lista de Marcações -->
                    @if($hasHistoryLoadError)
                        <div class="py-6 sm:py-8 text-center select-none">
                            <div class="w-10 h-10 rounded-xl bg-red-50 border border-red-100 mx-auto mb-2 flex items-center justify-center text-red-500">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                            </div>
                            <p class="text-xs sm:text-sm font-bold text-red-700">Não foi possível carregar seu histórico agora.</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">Tente atualizar a página em instantes.</p>
                        </div>
                    @elseif($this->todayPunches->isEmpty())
                        <div class="py-6 sm:py-8 text-center select-none">
                            <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200/60 mx-auto mb-2.5 flex items-center justify-center text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <p class="text-xs sm:text-sm font-bold text-gray-800">Você ainda não possui marcações neste período.</p>
                            <p class="text-[11px] text-gray-500 mt-1 max-w-xs mx-auto">
                                Nenhuma marcação registrada hoje. Ao confirmar a leitura do QR Code, seus registros diários e comprovantes serão exibidos aqui.
                            </p>
                        </div>
                    @else
                        <div class="space-y-2 my-2">
                            @foreach($this->todayPunches as $punch)
                                <div class="flex items-center justify-between py-2 px-3 rounded-xl bg-slate-50/80 border border-slate-200/70 text-xs sm:text-sm hover:bg-slate-100/60 transition">
                                    <div class="flex items-center gap-2.5">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-[10px] font-bold {{ $punch->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800' }}">
                                            {{ $punch->direction === 'in' ? 'E' : 'S' }}
                                        </span>
                                        <div>
                                            <p class="font-bold text-gray-900 leading-tight">
                                                {{ $punch->direction === 'in' ? 'Entrada' : 'Saída' }}
                                            </p>
                                            <p class="font-mono text-[10px] text-gray-400">
                                                NSR #{{ str_pad($punch->nsr, 9, '0', STR_PAD_LEFT) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2.5 text-right">
                                        <span class="font-mono text-base font-extrabold text-gray-900 tabular-nums">
                                            {{ $punch->occurred_at_local->format('H:i') }}
                                        </span>
                                        @if($punch->receipt)
                                            <a href="{{ route('receipts.pdf', ['code' => $punch->receipt->verification_code]) }}"
                                               target="_blank"
                                               title="Baixar comprovante fiscal (PDF)"
                                               class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 border border-indigo-100 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Rodapé do Card com Atalho para Central de Comprovantes -->
                <div class="pt-3 mt-2 border-t border-gray-100 flex items-center justify-between text-xs">
                    <span class="text-gray-500 font-medium">Registros oficiais REP-P</span>
                    <a href="{{ route('receipts.center') }}" 
                       class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline inline-flex items-center gap-1 py-1 px-1.5 rounded-lg hover:bg-indigo-50/50 transition">
                        Ver todos os comprovantes &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Específico e Tranquilo de Confirmação de Marcação (Acessível) -->
    <div x-show="state === 'success' || state === 'warning'"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true"
         aria-labelledby="punch-modal-title"
         @keydown.escape.window="resetToIdle()">
        
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" @click="resetToIdle()"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative z-10 w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-5 sm:p-6 text-left shadow-xl transition-all sm:my-8 border border-gray-100"
                 @click.stop>
                
                <div class="text-center">
                    <!-- Ícone de Sucesso / Observação -->
                    <div class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center mb-3.5 shadow-2xs"
                         :class="state === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                        <template x-if="state === 'warning'">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </template>
                        <template x-if="state === 'success'">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </template>
                    </div>

                    <!-- Título Operacional -->
                    <h3 id="punch-modal-title" class="text-lg sm:text-xl font-bold text-gray-900" x-text="punchConfirmation?.direction_title || 'Registro confirmado'"></h3>
                    <p class="text-xs text-gray-500 mt-0.5" x-text="punchConfirmation?.date"></p>

                    <!-- Mostrador com Horário Oficial e NSR Retornados pelo Servidor -->
                    <div class="my-4 py-3.5 px-4 bg-slate-50 border border-slate-200/80 rounded-xl">
                        <div class="font-mono text-3xl sm:text-4xl font-extrabold text-indigo-950 tracking-tight tabular-nums" x-text="punchConfirmation?.time"></div>
                        <div class="mt-1 font-mono text-xs font-semibold text-indigo-700" x-text="'NSR ' + (punchConfirmation?.nsr_formatted || punchConfirmation?.nsr)"></div>
                    </div>

                    <!-- Observação de Localização (Quando fora do raio, exibida como advertência contextual) -->
                    <template x-if="punchConfirmation?.has_warning">
                        <div class="mb-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-left flex items-start gap-2.5 text-xs text-amber-900 leading-relaxed">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            <span x-text="punchConfirmation?.warning_message"></span>
                        </div>
                    </template>

                    <!-- Situação do Comprovante (Tratado separadamente do registro do ponto) -->
                    <div class="mb-5 p-3 rounded-xl border text-left text-xs"
                         :class="punchConfirmation?.receipt_status === 'available' ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-700'">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <div>
                                    <p class="font-bold text-xs" x-text="punchConfirmation?.receipt_status === 'available' ? 'Comprovante emitido' : 'Comprovante em emissão'"></p>
                                    <p class="font-mono text-[10px] text-gray-500" x-text="punchConfirmation?.receipt_code || 'Acesse na Central de Comprovantes'"></p>
                                </div>
                            </div>

                            <template x-if="punchConfirmation?.receipt_url">
                                <a :href="punchConfirmation?.receipt_url"
                                   target="_blank"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition">
                                    Ver comprovante
                                </a>
                            </template>
                        </div>
                    </div>

                    <!-- Botão de Conclusão -->
                    <button type="button" 
                            @click="resetToIdle()" 
                            class="w-full min-h-[44px] inline-flex items-center justify-center px-6 py-3 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 transition cursor-pointer">
                        Concluir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Alpine.js com Máquina de Estados e Resiliência -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('timePunchComponent', (config = {}) => ({
                state: 'idle', // idle, starting, scanning, location, submitting, success, warning, error, unknown
                html5QrcodeScanner: null,
                timezone: config.timezone || 'America/Maceio',
                timezoneLabel: config.timezoneLabel || 'Horário do estabelecimento',
                currentTime: '',
                currentDate: '',
                currentAttemptId: null,
                errorMessage: '',
                errorAdvice: '',
                punchConfirmation: @json($confirmationData),
                networkErrorMsg: '',
                reconciling: false,
                lastDecodedText: null,
                lastCoords: null,
                triggerElement: null,
                isStartingCancelled: false,
                coordsPromise: null,

                init() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);
                },

                get stateBadgeLabel() {
                    switch (this.state) {
                        case 'starting': return 'Ativando câmera...';
                        case 'scanning': return 'Leitor ativo';
                        case 'location': return 'Localizando...';
                        case 'submitting': return 'Registrando...';
                        case 'error': return 'Falha na leitura';
                        case 'unknown': return 'Verificando conexão';
                        default: return 'Leitor pronto';
                    }
                },

                get statusAnnouncement() {
                    switch (this.state) {
                        case 'starting': return 'Ativando câmera do dispositivo.';
                        case 'scanning': return 'Câmera ativada. Aponte para o QR Code da empresa.';
                        case 'location': return 'QR Code identificado. Obtendo evidência de localização.';
                        case 'submitting': return 'Registrando marcação oficial no servidor.';
                        case 'success': return 'Ponto confirmado com sucesso.';
                        case 'warning': return 'Ponto confirmado com observação de perímetro.';
                        case 'error': return 'Falha na leitura do QR Code.';
                        case 'unknown': return 'Resultado pendente de confirmação com o servidor.';
                        default: return 'Leitura de ponto pronta para iniciar.';
                    }
                },

                updateClock() {
                    const now = new Date();
                    try {
                        this.currentTime = now.toLocaleTimeString('pt-BR', { timeZone: this.timezone });
                    } catch (e) {
                        this.currentTime = now.toLocaleTimeString('pt-BR');
                    }
                },

                generateIdempotencyKey() {
                    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
                        return crypto.randomUUID();
                    }
                    return 'pf_' + Date.now() + '_' + Math.random().toString(36).substring(2, 12);
                },

                prefetchCoordinates() {
                    if (!navigator.geolocation) {
                        this.coordsPromise = Promise.resolve(null);
                        return;
                    }

                    this.coordsPromise = new Promise((resolve) => {
                        let resolved = false;
                        const timeoutId = setTimeout(() => {
                            if (!resolved) {
                                resolved = true;
                                resolve(null);
                            }
                        }, 5000);

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                if (!resolved) {
                                    resolved = true;
                                    clearTimeout(timeoutId);
                                    resolve({
                                        latitude: position.coords.latitude,
                                        longitude: position.coords.longitude,
                                        accuracy: position.coords.accuracy
                                    });
                                }
                            },
                            (error) => {
                                if (!resolved) {
                                    resolved = true;
                                    clearTimeout(timeoutId);
                                    resolve(null);
                                }
                            },
                            { enableHighAccuracy: true, timeout: 4500, maximumAge: 10000 }
                        );
                    });
                },

                getCoordinates() {
                    if (this.coordsPromise) {
                        return this.coordsPromise;
                    }
                    this.prefetchCoordinates();
                    return this.coordsPromise;
                },

                async startScanner(triggerEl = null) {
                    if (this.state === 'starting' || this.state === 'scanning' || this.state === 'location' || this.state === 'submitting') {
                        return;
                    }

                    if (triggerEl) {
                        this.triggerElement = triggerEl;
                    }

                    // Validação de contexto seguro (HTTPS)
                    if (typeof window !== 'undefined' && window.isSecureContext === false && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                        this.state = 'error';
                        this.errorMessage = 'O acesso à câmera requer uma conexão segura (HTTPS).';
                        this.errorAdvice = 'Acesse o sistema utilizando um endereço com https:// para utilizar a câmera.';
                        return;
                    }

                    // Validação de suporte a getUserMedia
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        this.state = 'error';
                        this.errorMessage = 'Navegador sem suporte a captura de câmera.';
                        this.errorAdvice = 'Utilize uma versão recente de Chrome, Edge, Safari ou Firefox.';
                        return;
                    }

                    this.state = 'starting';
                    this.errorMessage = '';
                    this.errorAdvice = '';
                    this.isStartingCancelled = false;

                    // Solicita localização proativamente em segundo plano (aciona permissão de GPS no navegador)
                    this.prefetchCoordinates();

                    // Limpeza de estado de sessões anteriores
                    await this.cleanupScanner();

                    if (this.isStartingCancelled) {
                        return;
                    }

                    this.html5QrcodeScanner = new Html5Qrcode("qr-reader");

                    const scanConfig = {
                        fps: 15,
                        qrbox: (viewfinderWidth, viewfinderHeight) => {
                            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                            const qrboxSize = Math.floor(minEdge * 0.8);
                            return {
                                width: Math.max(120, Math.min(qrboxSize, 220)),
                                height: Math.max(120, Math.min(qrboxSize, 220))
                            };
                        }
                    };

                    try {
                        let cameras = [];
                        try {
                            cameras = await Html5Qrcode.getCameras();
                        } catch (e) {
                            console.warn("Não foi possível listar câmeras antecipadamente:", e);
                        }

                        if (this.isStartingCancelled) {
                            await this.cleanupScanner();
                            return;
                        }

                        let cameraConfig = { facingMode: "environment" };

                        if (cameras && cameras.length > 0) {
                            // Em smartphones (mais de uma câmera), prioriza a traseira
                            const backCam = cameras.find(c => {
                                const label = (c.label || '').toLowerCase();
                                return label.includes('back') || label.includes('traseira') || label.includes('rear') || label.includes('environment');
                            });

                            if (backCam) {
                                cameraConfig = backCam.id;
                            } else if (cameras.length > 1) {
                                cameraConfig = cameras[cameras.length - 1].id;
                            } else {
                                // Notebook / desktop com câmera única
                                cameraConfig = cameras[0].id;
                            }
                        }

                        await this.html5QrcodeScanner.start(
                            cameraConfig,
                            scanConfig,
                            (decodedText) => this.onScanSuccess(decodedText),
                            () => { /* frames intermediários silenciosos */ }
                        );

                        if (this.isStartingCancelled) {
                            await this.cleanupScanner();
                            return;
                        }

                        this.state = 'scanning';
                    } catch (err) {
                        console.warn("Falha na seleção inicial de câmera, tentando modo padrão/frontal:", err);

                        // Fallback para câmera user/frontal (comum em notebooks com restrição em environment)
                        if (!this.isStartingCancelled) {
                            try {
                                await this.html5QrcodeScanner.start(
                                    { facingMode: "user" },
                                    scanConfig,
                                    (decodedText) => this.onScanSuccess(decodedText),
                                    () => {}
                                );

                                if (this.isStartingCancelled) {
                                    await this.cleanupScanner();
                                    return;
                                }

                                this.state = 'scanning';
                                return;
                            } catch (fallbackErr) {
                                console.error("Falha final ao inicializar câmera:", fallbackErr);
                                if (!this.isStartingCancelled) {
                                    this.handleCameraError(fallbackErr || err);
                                }
                            }
                        }
                    }
                },

                async stopScanner() {
                    this.isStartingCancelled = true;
                    this.state = 'idle';
                    await this.cleanupScanner();
                },

                async cleanupScanner() {
                    if (this.html5QrcodeScanner) {
                        try {
                            if (this.html5QrcodeScanner.isScanning) {
                                await this.html5QrcodeScanner.stop();
                            }
                            await this.html5QrcodeScanner.clear();
                        } catch (e) {
                            console.warn("Aviso ao liberar Html5Qrcode:", e);
                        } finally {
                            this.html5QrcodeScanner = null;
                        }
                    }

                    // Garante liberação física de quaisquer MediaStreamTracks de vídeo da webcam
                    const videoEl = document.querySelector('#qr-reader video');
                    if (videoEl && videoEl.srcObject) {
                        try {
                            const tracks = videoEl.srcObject.getTracks();
                            tracks.forEach(track => track.stop());
                            videoEl.srcObject = null;
                        } catch (e) {}
                    }
                },

                handleCameraError(err) {
                    this.state = 'error';
                    const name = err.name || '';
                    const msg = (err.message || '').toLowerCase();

                    if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || msg.includes('permission') || msg.includes('denied')) {
                        this.errorMessage = 'Permissão de câmera não concedida.';
                        this.errorAdvice = 'Para liberar o acesso, abra as permissões do site nas configurações do navegador (ícone de ajustes ou cadeado ao lado do endereço).';
                    } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || msg.includes('not found') || msg.includes('no camera')) {
                        this.errorMessage = 'Nenhuma câmera encontrada no aparelho.';
                        this.errorAdvice = 'Verifique se o dispositivo possui câmera habilitada e conectada.';
                    } else if (name === 'NotReadableError' || name === 'TrackStartError' || msg.includes('in use') || msg.includes('could not start')) {
                        this.errorMessage = 'A câmera está ocupada por outro aplicativo.';
                        this.errorAdvice = 'Feche outros aplicativos ou abas que possam estar usando a câmera e tente novamente.';
                    } else if (name === 'OverconstrainedError') {
                        this.errorMessage = 'Não foi possível selecionar a câmera adequada.';
                        this.errorAdvice = 'O aparelho não atende aos requisitos de resolução do leitor.';
                    } else {
                        this.errorMessage = 'Não foi possível inicializar a câmera.';
                        this.errorAdvice = 'Verifique as configurações do dispositivo ou tente novamente.';
                    }
                },

                onScanSuccess(decodedText) {
                    // Bloqueio imediato de callbacks concorrentes durante a mesma tentativa
                    if (this.state !== 'scanning') {
                        return;
                    }

                    this.state = 'location';
                    this.lastDecodedText = decodedText;

                    // Desativação imediata da câmera após decodificação
                    this.cleanupScanner();

                    if (!this.currentAttemptId) {
                        this.currentAttemptId = this.generateIdempotencyKey();
                    }
                    const attemptKey = this.currentAttemptId;

                    // Coleta não-bloqueante de GPS
                    this.getCoordinates().then((coords) => {
                        this.lastCoords = coords;
                        this.state = 'submitting';
                        this.sendPunchRequest(attemptKey, decodedText, coords);
                    });
                },

                sendPunchRequest(attemptKey, decodedText, coords) {
                    const lat = coords ? coords.latitude : null;
                    const lon = coords ? coords.longitude : null;
                    const acc = coords ? coords.accuracy : null;

                    @this.call('registerPunch', decodedText, lat, lon, acc, attemptKey)
                        .then((result) => {
                            if (result && result.status === 'warning') {
                                this.state = 'warning';
                                this.punchConfirmation = result.punch || result;
                            } else if (result && result.status === 'error') {
                                this.state = 'error';
                                this.errorMessage = result.message || 'QR Code não autorizado.';
                                this.errorAdvice = 'Aponte a câmera para o QR Code oficial da sua empresa.';
                            } else {
                                this.state = 'success';
                                this.punchConfirmation = result ? (result.punch || result) : null;
                            }

                            this.currentAttemptId = null;
                            this.lastDecodedText = null;
                            this.lastCoords = null;
                        })
                        .catch((error) => {
                            console.error("Erro na comunicação com o servidor:", error);
                            this.state = 'unknown';
                            this.networkErrorMsg = 'Não foi possível confirmar o resultado da solicitação devido a uma oscilação na conexão.';
                        });
                },

                verifyUnknownStatus() {
                    if (!this.currentAttemptId) {
                        this.resetToIdle();
                        return;
                    }

                    this.reconciling = true;
                    @this.call('checkPunchStatus', this.currentAttemptId)
                        .then((reconciled) => {
                            this.reconciling = false;
                            if (reconciled) {
                                this.state = reconciled.has_warning ? 'warning' : 'success';
                                this.punchConfirmation = reconciled;
                                this.currentAttemptId = null;
                                this.lastDecodedText = null;
                                this.lastCoords = null;
                            } else {
                                this.networkErrorMsg = 'O servidor confirma que a tentativa anterior não foi gravada. Você pode reenviar o registro agora.';
                            }
                        })
                        .catch(() => {
                            this.reconciling = false;
                            this.networkErrorMsg = 'A conexão continua instável. Tente novamente em instantes.';
                        });
                },

                retrySameAttempt() {
                    if (!this.lastDecodedText || !this.currentAttemptId) {
                        this.resetToIdle();
                        return;
                    }
                    this.state = 'submitting';
                    this.sendPunchRequest(this.currentAttemptId, this.lastDecodedText, this.lastCoords);
                },

                resetToIdle() {
                    this.state = 'idle';
                    this.errorMessage = '';
                    this.errorAdvice = '';
                    this.punchConfirmation = null;
                    this.currentAttemptId = null;
                    this.lastDecodedText = null;
                    this.lastCoords = null;
                    this.coordsPromise = null;
                    this.networkErrorMsg = '';
                    this.reconciling = false;
                    this.isStartingCancelled = true;

                    this.cleanupScanner();

                    if (this.triggerElement && typeof this.triggerElement.focus === 'function') {
                        this.$nextTick(() => {
                            this.triggerElement.focus();
                        });
                    }
                }
            }));
        });
    </script>
</div>