<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] #[Title('Registro de Ponto')] class extends Component
{
    public $message = '';
    public $status = '';

    public function registerPunch($qrCodeHash, $latitude = null, $longitude = null, $accuracy = null)
    {
        $user = Auth::user();
        $employee = $user ? $user->employee : null;
        $sector = $employee ? $employee->sector : null;

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
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'QR Code Não Autorizado',
                'message' => 'O código escaneado não é válido para o seu setor ou matriz da empresa.',
                'buttonText' => 'Escanear Novamente'
            ]);
            return;
        }

        // 2. Resolução das Coordenadas e Raio esperados (Regra Híbrida: Setor -> Global)
        $hasCustomLocation = $sector && $sector->latitude !== null && $sector->longitude !== null;

        $targetLatitude = $hasCustomLocation
            ? (float) $sector->latitude
            : (float) ($settings['company_latitude'] ?? -9.6658);

        $targetLongitude = $hasCustomLocation
            ? (float) $sector->longitude
            : (float) ($settings['company_longitude'] ?? -35.7350);

        $allowedRadius = ($sector && $sector->allowed_radius_meters !== null)
            ? (float) $sector->allowed_radius_meters
            : (float) ($settings['allowed_radius_meters'] ?? 100);

        $locationName = $hasCustomLocation ? "do seu setor ({$sector->name})" : "da empresa";

        $distance = null;
        $locationValid = null;
        if ($latitude !== null && $longitude !== null) {
            $distance = $this->calculateDistance(
                (float) $latitude,
                (float) $longitude,
                $targetLatitude,
                $targetLongitude
            );
            $locationValid = ($distance <= $allowedRadius);
        }

        // 3. Determinação da direção com base no ledger oficial REP-P (PunchEvent como fonte de verdade)
        $tz = $sector?->establishment?->timezone ?? 'America/Maceio';
        $lastPunch = \App\Models\PunchEvent::where('user_id', Auth::id())
            ->whereDate('occurred_at_local', now($tz)->toDateString())
            ->orderBy('occurred_at_utc', 'desc')
            ->first();

        $type = ($lastPunch && $lastPunch->direction === 'in') ? 'out' : 'in';

        // 4. Gravação atômica única através do RecordPunchEventAction (REP-P -> ARP -> Projeção TimeEntry)
        $nsrFormatted = null;
        try {
            $recordPunchAction = app(\App\Domain\TimeClock\Actions\RecordPunchEventAction::class);
            $punchEvent = $recordPunchAction->execute(
                user: Auth::user(),
                direction: $type,
                latitude: $latitude !== null ? (float) $latitude : null,
                longitude: $longitude !== null ? (float) $longitude : null,
                accuracy: $accuracy !== null ? (float) $accuracy : null,
                qrLocationValid: true,
                locationValid: $locationValid,
                source: 'web_pwa',
                establishment: $sector?->establishment,
                locationDistanceMeters: $distance !== null ? (float) round($distance, 2) : null
            );
            $nsrFormatted = str_pad((string) $punchEvent->nsr, 9, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            report($e);
            $this->status = 'error';
            $this->message = "Falha ao registrar ponto oficial REP-P. Nenhuma marcação foi registrada. Tente novamente.";
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Erro no Registro REP-P',
                'message' => 'Ocorreu uma falha ao persistir a marcação oficial. Nenhuma batida foi gravada. Por favor, tente novamente.',
                'buttonText' => 'Entendido'
            ]);
            return;
        }

        $tipoStr = $type === 'in' ? 'Entrada' : 'Saída';
        $nsrInfo = $nsrFormatted ? " (NSR #{$nsrFormatted})" : '';

        // 5. Exibição de Alerta de Auditoria se fora do raio (não impede o registro nem altera horário)
        if ($locationValid === false) {
            $distRounded = round($distance);
            $radiusRounded = round($allowedRadius);
            $this->message = "Ponto registrado com aviso! ({$tipoStr} às " . now($tz)->format('H:i:s') . "{$nsrInfo} - Fora do raio permitido)";
            $this->status = 'warning';
            $this->dispatch('app-modal-alert', [
                'type' => 'warning',
                'title' => 'Ponto Registrado (Fora da Área Permitida)',
                'message' => "Sua {$tipoStr} foi confirmada com sucesso às " . now($tz)->format('H:i:s') . "{$nsrInfo}, porém o GPS indicou que você estava a {$distRounded}m {$locationName} (raio autorizado: {$radiusRounded}m). Esta evidência foi registrada para fins de auditoria.",
                'buttonText' => 'Concluir'
            ]);
        } else {
            $this->message = "Ponto registrado com sucesso! ({$tipoStr} às " . now($tz)->format('H:i:s') . "{$nsrInfo})";
            $this->status = 'success';
            $this->dispatch('app-modal-alert', [
                'type' => 'success',
                'title' => 'Ponto Registrado com Sucesso!',
                'message' => "Sua {$tipoStr} foi confirmada às " . now($tz)->format('H:i:s') . "{$nsrInfo} no Horário Oficial de Maceió (GMT-3).",
                'buttonText' => 'Concluir'
            ]);
        }
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

<style>
    /* Ajustes dinâmicos de altura para viewports compactas (mobile, netbooks, laptops 1366x768) */
    @media (max-height: 820px) {
        .time-punch-container {
            padding-top: 0.25rem !important;
            padding-bottom: 0.5rem !important;
        }
        .time-punch-clock-card {
            padding: 0.625rem 1rem !important;
            margin-bottom: 0.5rem !important;
        }
        .time-punch-clock-text {
            font-size: 2rem !important;
            line-height: 2.25rem !important;
        }
        .time-punch-main-card {
            padding: 0.875rem 1rem !important;
        }
        .time-punch-reader-box {
            max-width: 210px !important;
        }
    }
    @media (max-height: 700px) {
        .time-punch-clock-card {
            padding: 0.45rem 0.75rem !important;
            margin-bottom: 0.35rem !important;
        }
        .time-punch-clock-text {
            font-size: 1.5rem !important;
            line-height: 1.75rem !important;
        }
        .time-punch-main-card {
            padding: 0.625rem 0.875rem !important;
        }
        .time-punch-reader-box {
            max-width: 170px !important;
        }
        .time-punch-title {
            font-size: 1.125rem !important;
            margin-bottom: 0.125rem !important;
        }
        .time-punch-subtitle {
            margin-bottom: 0.25rem !important;
        }
        .time-punch-btn {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
            font-size: 0.875rem !important;
        }
    }
    @media (max-height: 640px) {
        .time-punch-clock-card {
            padding: 0.35rem 0.6rem !important;
            margin-bottom: 0.25rem !important;
        }
        .time-punch-clock-text {
            font-size: 1.35rem !important;
            line-height: 1.5rem !important;
        }
        .time-punch-clock-info {
            display: none !important;
        }
        .time-punch-main-card {
            padding: 0.5rem 0.75rem !important;
        }
        .time-punch-reader-box {
            max-width: 155px !important;
        }
        .time-punch-title {
            font-size: 1rem !important;
            margin-bottom: 0 !important;
        }
        .time-punch-subtitle {
            font-size: 0.6875rem !important;
            margin-bottom: 0.25rem !important;
        }
        .time-punch-btn {
            padding-top: 0.45rem !important;
            padding-bottom: 0.45rem !important;
            font-size: 0.8125rem !important;
        }
    }
    @media (max-height: 500px) {
        .time-punch-clock-card {
            display: none !important; /* Somente em landscape extremamente achatado */
        }
        .time-punch-reader-box {
            max-width: 130px !important;
        }
    }
</style>

<div class="time-punch-container w-full max-w-md sm:max-w-lg mx-auto py-1 sm:py-3 px-2 sm:px-4" x-data="timePunchComponent()">
    <!-- Digital Clock Card (Mobile First) -->
    <div class="time-punch-clock-card mb-2 sm:mb-3 bg-gradient-to-r from-indigo-700 via-indigo-800 to-indigo-900 rounded-2xl shadow-sm px-4 py-2.5 sm:px-6 sm:py-3.5 text-white text-center">
        <p class="text-[10px] sm:text-xs uppercase tracking-widest text-indigo-200 font-semibold mb-0.5" x-text="currentDate"></p>
        <div class="time-punch-clock-text text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight font-mono text-white leading-tight" x-text="currentTime">
            {{ now()->format('H:i:s') }}
        </div>
        <div class="time-punch-clock-info mt-1 flex items-center justify-center gap-1.5 text-[11px] sm:text-xs text-indigo-200">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            Horário Oficial de Maceió (GMT-3)
        </div>
    </div>

    <!-- Main Scanner Card -->
    <div class="time-punch-main-card bg-white rounded-2xl shadow-sm border border-gray-200/80 p-3.5 sm:p-5 md:p-6 text-center">
        <h2 class="time-punch-title text-lg sm:text-xl md:text-2xl font-bold text-gray-900 mb-0.5 sm:mb-1">Bater Ponto</h2>
        <p class="time-punch-subtitle text-[11px] sm:text-xs md:text-sm text-gray-500 mb-2 sm:mb-3">Aponte a câmera para o QR Code da empresa</p>
        
        <!-- Alerts -->
        @if($message)
            <div class="mb-3 rounded-xl p-3 flex items-center gap-2.5 text-left {{ $status === 'success' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : ($status === 'warning' ? 'bg-amber-50 text-amber-900 border border-amber-200' : 'bg-red-50 text-red-900 border border-red-200') }}">
                @if($status === 'success')
                    <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 text-emerald-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </div>
                @elseif($status === 'warning')
                    <div class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 text-amber-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    </div>
                @else
                    <div class="w-7 h-7 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                    </div>
                @endif
                <p class="text-xs sm:text-sm font-semibold">{{ $message }}</p>
            </div>
        @endif

        <div x-show="!isProcessing">
            <!-- QR Scanner Container with mobile friendly dimensions and camera viewfinder -->
            <div class="time-punch-reader-box relative w-full max-w-[190px] xs:max-w-[210px] sm:max-w-[240px] md:max-w-[250px] mx-auto aspect-square overflow-hidden rounded-2xl border-2 border-dashed border-indigo-300/80 bg-slate-50 flex items-center justify-center transition-all shadow-inner">
                <div id="qr-reader" class="w-full h-full flex items-center justify-center [&>video]:object-cover [&>video]:w-full [&>video]:h-full [&>video]:rounded-xl"></div>
                
                <div x-show="!isScanning" class="absolute inset-0 flex flex-col items-center justify-center p-3 pointer-events-none text-indigo-400 select-none">
                    <div class="relative w-12 h-12 sm:w-16 sm:h-16 flex items-center justify-center mb-1 sm:mb-1.5">
                        <div class="absolute top-0 left-0 w-3 h-3 border-t-2 border-l-2 border-indigo-500 rounded-tl"></div>
                        <div class="absolute top-0 right-0 w-3 h-3 border-t-2 border-r-2 border-indigo-500 rounded-tr"></div>
                        <div class="absolute bottom-0 left-0 w-3 h-3 border-b-2 border-l-2 border-indigo-500 rounded-bl"></div>
                        <div class="absolute bottom-0 right-0 w-3 h-3 border-b-2 border-r-2 border-indigo-500 rounded-br"></div>
                        <svg class="w-7 h-7 sm:w-9 sm:h-9 text-indigo-500/70" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.875 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 14.25h1.5v1.5h-1.5zM18.75 14.25h1.5v1.5h-1.5zM14.25 18.75h1.5v1.5h-1.5zM18.75 18.75h1.5v1.5h-1.5zM16.5 16.5h1.5v1.5h-1.5z" />
                        </svg>
                    </div>
                    <p class="text-[11px] sm:text-xs text-indigo-900/60 font-medium text-center">Câmera pronta</p>
                </div>
            </div>
            
            <div class="mt-3 sm:mt-4 flex flex-col sm:flex-row justify-center items-center gap-2">
                <button @click="startScanner()" x-show="!isScanning" type="button" class="time-punch-btn w-full sm:w-auto min-w-[220px] inline-flex items-center justify-center px-6 py-3 sm:py-3.5 border border-transparent text-sm sm:text-base font-bold rounded-xl shadow-lg shadow-indigo-600/20 text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 active:scale-[0.98] transition-all touch-manipulation cursor-pointer">
                    <svg class="w-5 h-5 mr-2 -ml-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                    </svg>
                    Escanear QR Code
                </button>
                <button @click="stopScanner()" x-show="isScanning" style="display: none;" type="button" class="time-punch-btn w-full sm:w-auto min-w-[200px] inline-flex items-center justify-center px-6 py-3 sm:py-3.5 border border-transparent text-sm sm:text-base font-bold rounded-xl shadow-lg shadow-rose-600/20 text-white bg-rose-600 hover:bg-rose-700 active:bg-rose-800 active:scale-[0.98] transition-all touch-manipulation cursor-pointer">
                    <svg class="w-5 h-5 mr-2 -ml-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Cancelar Leitura
                </button>
            </div>
        </div>

        <!-- Processing State (Clean mobile spinner) -->
        <div x-show="isProcessing" style="display: none;" class="text-center py-6 sm:py-8">
            <div class="inline-flex p-3 sm:p-4 rounded-full bg-indigo-50 mb-2 sm:mb-3 animate-pulse">
                <svg class="animate-spin h-8 w-8 sm:h-10 sm:w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <p class="text-sm sm:text-base font-semibold text-gray-900">Validando QR Code e GPS...</p>
            <p class="text-[11px] sm:text-xs text-gray-500 mt-1">Garantindo registro inviolável do ponto</p>
        </div>
    </div>

    <!-- Modal de Reativação de Permissões (Câmera & GPS) -->
    <div x-show="permissionModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto" 
         role="dialog" 
         aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" @click="permissionModal = false"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative z-10 w-full max-w-lg transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all sm:my-8 border border-gray-100">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-xs"
                         :class="permissionType === 'camera' ? 'bg-amber-100 text-amber-600' : 'bg-rose-100 text-rose-600'">
                        <template x-if="permissionType === 'camera'">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                            </svg>
                        </template>
                        <template x-if="permissionType === 'gps'">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                        </template>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-base font-bold text-gray-900" x-text="permissionType === 'camera' ? 'Permissão de Câmera Bloqueada' : 'Permissão de Localização (GPS) Bloqueada'"></h3>
                        <p class="text-xs text-gray-500 mt-1" x-text="permissionErrorMsg"></p>
                    </div>
                </div>

                <!-- Guia Passo a Passo -->
                <div class="mt-5 p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3 text-xs text-slate-700">
                    <p class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        Como reativar a permissão no seu navegador:
                    </p>
                    
                    <ol class="list-decimal pl-4 space-y-2 text-slate-600">
                        <li>
                            <strong>Ícone de Cadeado / Ajustes:</strong> No topo da tela, clique no ícone de <strong>cadeado</strong> ou <strong>ajustes de site</strong> que fica no início da barra de endereço (ao lado de <em>pontofacil...</em>).
                        </li>
                        <li>
                            <strong>Permitir Acesso:</strong> Localize as opções <span class="text-indigo-700 font-semibold" x-text="permissionType === 'camera' ? 'Câmera' : 'Localização / GPS'"></span> e mude para <strong>"Permitir"</strong> (ou toque em <em>"Redefinir permissões"</em>).
                        </li>
                        <li>
                            <strong>No iPhone / iPad (Safari):</strong> Toque no botão <strong>aA</strong> na barra de endereço &rarr; <em>Ajustes do Site</em> &rarr; <em>Câmera / Localização</em> &rarr; <strong>Permitir</strong>.
                        </li>
                        <li>
                            <strong>Tentar Novamente:</strong> Após alterar, clique no botão azul abaixo para que o navegador peça novamente ou inicie o scanner.
                        </li>
                    </ol>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-end">
                    <button type="button" @click="permissionModal = false" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition">
                        Fechar
                    </button>
                    <template x-if="permissionType === 'gps' && lastDecodedText">
                        <button type="button" @click="proceedWithoutGps()" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 transition">
                            Registrar sem GPS
                        </button>
                    </template>
                    <button type="button" @click="retryPermission()" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        Tentar Novamente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('timePunchComponent', () => ({
                html5QrcodeScanner: null,
                isScanning: false,
                isProcessing: false,
                currentTime: '',
                currentDate: '',
                permissionModal: false,
                permissionType: 'camera',
                permissionErrorMsg: '',
                lastDecodedText: null,

                init() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);
                },

                updateClock() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString('pt-BR', { timeZone: 'America/Maceio' });
                    this.currentDate = now.toLocaleDateString('pt-BR', { 
                        timeZone: 'America/Maceio',
                        weekday: 'long', 
                        day: '2-digit', 
                        month: 'long', 
                        year: 'numeric' 
                    });
                },

                startScanner() {
                    if (!this.html5QrcodeScanner) {
                        this.html5QrcodeScanner = new Html5Qrcode("qr-reader");
                    }
                    
                    const qrboxFunction = (viewfinderWidth, viewfinderHeight) => {
                        const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                        const qrboxSize = Math.floor(minEdge * 0.8);
                        return {
                            width: Math.max(120, Math.min(qrboxSize, 220)),
                            height: Math.max(120, Math.min(qrboxSize, 220))
                        };
                    };
                    const config = { fps: 15, qrbox: qrboxFunction };
                    
                    this.html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        config,
                        (decodedText) => this.onScanSuccess(decodedText),
                        () => { /* silent frame errors */ }
                    ).then(() => {
                        this.isScanning = true;
                    }).catch((err) => {
                        console.error("Scanner start error:", err);
                        this.permissionType = 'camera';
                        this.permissionErrorMsg = 'Acesso à câmera foi recusado ou não está disponível. Siga as instruções abaixo para liberar o uso da câmera.';
                        this.permissionModal = true;
                    });
                },

                stopScanner() {
                    if (this.html5QrcodeScanner && this.isScanning) {
                        this.html5QrcodeScanner.stop().then(() => {
                            this.isScanning = false;
                        }).catch(() => {
                            this.isScanning = false;
                        });
                    }
                },

                onScanSuccess(decodedText) {
                    this.stopScanner();
                    this.isProcessing = true;
                    this.lastDecodedText = decodedText;
                    
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                @this.call('registerPunch', decodedText, position.coords.latitude, position.coords.longitude, position.coords.accuracy)
                                    .then(() => {
                                        this.isProcessing = false;
                                        this.lastDecodedText = null;
                                    });
                            },
                            (error) => {
                                this.isProcessing = false;
                                this.permissionType = 'gps';
                                this.permissionErrorMsg = error.code === 1 
                                    ? 'A permissão de localização (GPS) não foi concedida no navegador. A geolocalização é utilizada como evidência de conformidade do registro de ponto.'
                                    : 'Não foi possível capturar a localização GPS (' + error.message + '). Verifique se o GPS do aparelho está ativado.';
                                this.permissionModal = true;
                            },
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                        );
                    } else {
                        // Dispositivo sem suporte a GPS: registra normalmente sem coordenadas
                        @this.call('registerPunch', decodedText, null, null, null)
                            .then(() => {
                                this.isProcessing = false;
                                this.lastDecodedText = null;
                            });
                    }
                },

                proceedWithoutGps() {
                    this.permissionModal = false;
                    this.isProcessing = true;
                    @this.call('registerPunch', this.lastDecodedText, null, null, null)
                        .then(() => {
                            this.isProcessing = false;
                            this.lastDecodedText = null;
                        });
                },

                retryPermission() {
                    this.permissionModal = false;
                    if (this.permissionType === 'camera') {
                        this.startScanner();
                    } else if (this.lastDecodedText) {
                        this.onScanSuccess(this.lastDecodedText);
                    } else {
                        this.startScanner();
                    }
                }
            }));
        });
    </script>
</div>