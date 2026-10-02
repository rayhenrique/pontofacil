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

    public function registerPunch($qrCodeHash, $latitude, $longitude)
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

        $distance = $this->calculateDistance(
            (float) $latitude,
            (float) $longitude,
            $targetLatitude,
            $targetLongitude
        );

        if ($distance > $allowedRadius) {
            $this->message = "Você está fora do raio permitido para bater o ponto {$locationName}. (Distância: " . round($distance) . "m, permitido: {$allowedRadius}m)";
            $this->status = 'error';
            $this->dispatch('app-modal-alert', [
                'type' => 'error',
                'title' => 'Fora do Raio Permitido (GPS)',
                'message' => $this->message,
                'buttonText' => 'Entendido'
            ]);
            return;
        }

        $lastPunch = TimeEntry::where('user_id', Auth::id())
            ->whereDate('timestamp', now()->toDateString())
            ->orderBy('timestamp', 'desc')
            ->first();
            
        $type = $lastPunch && $lastPunch->type === 'in' ? 'out' : 'in';

        TimeEntry::create([
            'user_id' => Auth::id(),
            'timestamp' => now(), // Servidor define o horário exato
            'type' => $type,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        $nsrFormatted = null;
        try {
            $recordPunchAction = app(\App\Domain\TimeClock\Actions\RecordPunchEventAction::class);
            $punchEvent = $recordPunchAction->execute(
                user: Auth::user(),
                direction: $type,
                latitude: $latitude,
                longitude: $longitude,
                accuracy: null,
                qrLocationValid: true,
                locationValid: true,
                source: 'web_pwa'
            );
            $nsrFormatted = str_pad((string) $punchEvent->nsr, 9, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            // Em caso de exceção de registro no ledger, registra log sem quebrar a tela
            report($e);
        }

        $tipoStr = $type === 'in' ? 'Entrada' : 'Saída';
        $nsrInfo = $nsrFormatted ? " (NSR #{$nsrFormatted})" : '';
        $this->message = "Ponto registrado com sucesso! ($tipoStr às " . now()->format('H:i:s') . "{$nsrInfo})";
        $this->status = 'success';
        $this->dispatch('app-modal-alert', [
            'type' => 'success',
            'title' => 'Ponto Registrado com Sucesso!',
            'message' => "Sua {$tipoStr} foi confirmada às " . now()->format('H:i:s') . "{$nsrInfo} no Horário Oficial de Maceió (GMT-3).",
            'buttonText' => 'Concluir'
        ]);
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

<div class="max-w-xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8" x-data="timePunchComponent()">
    <!-- Digital Clock Card (Mobile First) -->
    <div class="mb-4 bg-gradient-to-r from-indigo-700 to-indigo-900 rounded-2xl shadow-md p-5 text-white text-center">
        <p class="text-xs uppercase tracking-widest text-indigo-200 font-semibold mb-1" x-text="currentDate"></p>
        <div class="text-4xl sm:text-5xl font-extrabold tracking-tight font-mono text-white" x-text="currentTime">
            {{ now()->format('H:i:s') }}
        </div>
        <div class="mt-2 flex items-center justify-center gap-2 text-xs text-indigo-200">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            Horário Oficial de Maceió (GMT-3)
        </div>
    </div>

    <!-- Main Scanner Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6 text-center">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Bater Ponto</h2>
        <p class="text-xs sm:text-sm text-gray-500 mb-5">Aponte a câmera para o QR Code da empresa</p>
        
        <!-- Alerts -->
        @if($message)
            <div class="mb-5 rounded-xl p-4 flex items-center gap-3 text-left {{ $status === 'success' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-red-50 text-red-900 border border-red-200' }}">
                @if($status === 'success')
                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 text-emerald-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </div>
                @else
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 text-red-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                    </div>
                @endif
                <p class="text-sm font-semibold">{{ $message }}</p>
            </div>
        @endif

        <div x-show="!isProcessing">
            <!-- QR Scanner Container with mobile friendly dimensions -->
            <div id="qr-reader" class="w-full max-w-xs mx-auto overflow-hidden rounded-xl border-2 border-dashed border-indigo-300 bg-gray-50 aspect-square flex items-center justify-center"></div>
            
            <div class="mt-5 flex flex-col sm:flex-row justify-center gap-3">
                <button @click="startScanner()" x-show="!isScanning" type="button" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-base font-semibold rounded-xl shadow-md text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 transition touch-manipulation">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" /></svg>
                    Escanear QR Code
                </button>
                <button @click="stopScanner()" x-show="isScanning" style="display: none;" type="button" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-base font-semibold rounded-xl shadow-md text-white bg-red-600 hover:bg-red-700 active:bg-red-800 transition touch-manipulation">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    Cancelar Leitura
                </button>
            </div>
        </div>

        <!-- Processing State (Clean mobile spinner) -->
        <div x-show="isProcessing" style="display: none;" class="text-center py-8">
            <div class="inline-flex p-4 rounded-full bg-indigo-50 mb-3 animate-pulse">
                <svg class="animate-spin h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <p class="text-base font-medium text-gray-900">Validando QR Code e GPS...</p>
            <p class="text-xs text-gray-500 mt-1">Garantindo registro inviolável do ponto</p>
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
                    
                    const config = { fps: 10, qrbox: { width: 220, height: 220 } };
                    
                    this.html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        config,
                        (decodedText) => this.onScanSuccess(decodedText),
                        () => { /* silent frame errors */ }
                    ).then(() => {
                        this.isScanning = true;
                    }).catch((err) => {
                        console.error("Scanner start error:", err);
                        alert("Não foi possível acessar a câmera. Certifique-se de usar HTTPS e autorizar a permissão da câmera no navegador.");
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
                    
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                @this.call('registerPunch', decodedText, position.coords.latitude, position.coords.longitude)
                                    .then(() => {
                                        this.isProcessing = false;
                                    });
                            },
                            (error) => {
                                alert("Erro ao capturar localização (GPS): " + error.message + ". A localização é obrigatória para o registro.");
                                this.isProcessing = false;
                            },
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                        );
                    } else {
                        alert("Geolocalização não é suportada por este dispositivo.");
                        this.isProcessing = false;
                    }
                }
            }));
        });
    </script>
</div>