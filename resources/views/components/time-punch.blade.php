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
        $settings = SystemSetting::whereIn('key', [
            'qr_code_hash', 'company_latitude', 'company_longitude', 'allowed_radius_meters'
        ])->pluck('value', 'key');

        if ($qrCodeHash !== $settings['qr_code_hash']) {
            $this->message = "QR Code inválido ou expirado.";
            $this->status = 'error';
            return;
        }

        $distance = $this->calculateDistance(
            (float) $latitude,
            (float) $longitude,
            (float) $settings['company_latitude'],
            (float) $settings['company_longitude']
        );

        if ($distance > (float) $settings['allowed_radius_meters']) {
            $this->message = "Você está fora do raio permitido para bater o ponto. (Distância: " . round($distance) . "m)";
            $this->status = 'error';
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

        $tipoStr = $type === 'in' ? 'Entrada' : 'Saída';
        $this->message = "Ponto registrado com sucesso! ($tipoStr às " . now()->format('H:i:s') . ")";
        $this->status = 'success';
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

<div class="max-w-2xl mx-auto py-10 px-4 sm:px-6 lg:px-8" x-data="timePunchComponent()">
    <div class="bg-white rounded-lg shadow px-5 py-6 sm:px-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Registro de Ponto Inteligente</h2>
        
        <!-- Alerts -->
        @if($message)
            <div class="mb-4 rounded-md p-4 {{ $status === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                <p class="text-sm font-medium">{{ $message }}</p>
            </div>
        @endif

        <div x-show="!isProcessing">
            <!-- QR Scanner Container -->
            <div id="qr-reader" class="w-full max-w-sm mx-auto overflow-hidden rounded-lg border-2 border-indigo-200"></div>
            
            <div class="mt-4 flex justify-center space-x-4">
                <button @click="startScanner()" x-show="!isScanning" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                    Iniciar Câmera
                </button>
                <button @click="stopScanner()" x-show="isScanning" style="display: none;" type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700">
                    Parar Câmera
                </button>
            </div>
        </div>

        <!-- Processing State -->
        <div x-show="isProcessing" style="display: none;" class="text-center py-10">
            <svg class="animate-spin mx-auto h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-4 text-gray-600">Obtendo localização e processando...</p>
        </div>
    </div>

    <!-- html5-qrcode library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('timePunchComponent', () => ({
                html5QrcodeScanner: null,
                isScanning: false,
                isProcessing: false,

                startScanner() {
                    if (!this.html5QrcodeScanner) {
                        this.html5QrcodeScanner = new Html5Qrcode("qr-reader");
                    }
                    
                    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                    
                    this.html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        config,
                        (decodedText, decodedResult) => this.onScanSuccess(decodedText),
                        (errorMessage) => { /* ignore */ }
                    ).then(() => {
                        this.isScanning = true;
                    }).catch((err) => {
                        console.error("Failed to start scanner:", err);
                        alert("Não foi possível acessar a câmera. Certifique-se de usar HTTPS e conceder as permissões necessárias.");
                    });
                },

                stopScanner() {
                    if (this.html5QrcodeScanner && this.isScanning) {
                        this.html5QrcodeScanner.stop().then(() => {
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
                                alert("Erro ao obter localização: " + error.message);
                                this.isProcessing = false;
                            },
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                        );
                    } else {
                        alert("Geolocalização não é suportada por este navegador.");
                        this.isProcessing = false;
                    }
                }
            }));
        });
    </script>
</div>