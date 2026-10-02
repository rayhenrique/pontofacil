<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

new class extends Component
{
    public $showModal = false;
    public $currentVersion = 'v1.6.0';
    public $releaseNotes = '';

    public function mount()
    {
        if (Auth::check() && Auth::user()->last_seen_version !== $this->currentVersion) {
            $this->showModal = true;
            $this->loadReleaseNotes();
        }
    }

    public function loadReleaseNotes()
    {
        $path = base_path('versoes.md');
        if (File::exists($path)) {
            $content = File::get($path);
            
            // Extract just the current version notes if possible
            $pattern = '/## ' . preg_quote($this->currentVersion) . '(.*?)(?=\n## |$)/s';
            if (preg_match($pattern, $content, $matches)) {
                $this->releaseNotes = Str::markdown(trim($matches[1]));
            } else {
                $this->releaseNotes = Str::markdown($content);
            }
        }
    }

    public function close()
    {
        $user = Auth::user();
        if ($user) {
            $user->last_seen_version = $this->currentVersion;
            $user->save();
        }

        $this->showModal = false;
    }
};
?>

<div>
    @if($showModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto" aria-labelledby="version-modal-title" role="dialog" aria-modal="true" wire:transition>
            <!-- Backdrop overlay -->
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="close"></div>

            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div class="relative z-10 w-full max-w-lg transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 border border-gray-100">
                    <!-- Top Gradient Header -->
                    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-indigo-950 p-6 text-white text-center relative overflow-hidden">
                        <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-indigo-500/20 blur-xl"></div>
                        <div class="relative z-10">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/30 text-emerald-200 border border-emerald-400/30 mb-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Nova Versão Disponível
                            </span>
                            <h3 class="text-xl sm:text-2xl font-black tracking-tight" id="version-modal-title">
                                PontoFácil {{ $currentVersion }} 🎉
                            </h3>
                            <p class="text-xs text-indigo-200 mt-1">
                                Estrutura Híbrida Inteligente de Ponto & Popups Modais
                            </p>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="text-xs sm:text-sm text-gray-700 max-h-72 overflow-y-auto space-y-3 bg-gray-50/80 p-4 rounded-2xl border border-gray-100 prose prose-sm max-w-none">
                            {!! $releaseNotes !!}
                        </div>

                        <div class="mt-6 flex flex-col sm:flex-row gap-2 justify-end">
                            <button type="button" 
                                    wire:click="close" 
                                    class="w-full inline-flex justify-center items-center rounded-xl px-5 py-3 bg-indigo-600 text-sm font-bold text-white hover:bg-indigo-700 active:bg-indigo-800 shadow-md shadow-indigo-600/20 transition touch-manipulation">
                                Entendido, começar a usar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>