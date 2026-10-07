<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

new class extends Component
{
    public $showModal = false;
    public $currentVersion = '';
    public $releaseNotes = '';

    public function mount()
    {
        $this->resolveCurrentVersion();

        if (Auth::check() && !empty($this->currentVersion)) {
            $user = Auth::user();
            if ($user->last_seen_version !== $this->currentVersion) {
                $this->showModal = true;
                $this->loadReleaseNotes();
            }
        }
    }

    public function resolveCurrentVersion(): void
    {
        $path = base_path('versoes.md');
        if (!File::exists($path)) {
            $this->currentVersion = 'v2.4.0';
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '## ')) {
                $rawTitle = trim(substr($trimmed, 3));
                // Remove "(Atual)" ou variações para extrair a tag pura: "v2.4.0"
                $this->currentVersion = trim(str_replace('(Atual)', '', $rawTitle));
                break;
            }
        }

        if (empty($this->currentVersion)) {
            $this->currentVersion = 'v2.4.0';
        }
    }

    public function loadReleaseNotes(): void
    {
        $path = base_path('versoes.md');
        if (!File::exists($path)) {
            $this->releaseNotes = '<p>Nova versão disponível.</p>';
            return;
        }

        $content = File::get($path);

        // Regex para capturar estritamente as notas da versão atual até o próximo cabeçalho '## '
        $escaped = preg_quote($this->currentVersion, '/');
        $pattern = '/##\s+' . $escaped . '(?:\s+\(Atual\))?\s*(.*?)(?=\n##\s+v\d|\Z)/s';

        if (preg_match($pattern, $content, $matches)) {
            $markdownText = trim($matches[1]);
            $this->releaseNotes = Str::markdown($markdownText);
        } else {
            // Fallback: extrai do primeiro '## ' até o segundo '## '
            $lines = explode("\n", $content);
            $extracted = [];
            $foundCurrent = false;

            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '## ')) {
                    if ($foundCurrent) {
                        break;
                    }
                    $foundCurrent = true;
                    continue;
                }

                if ($foundCurrent) {
                    $extracted[] = $line;
                }
            }

            $this->releaseNotes = Str::markdown(trim(implode("\n", $extracted)));
        }
    }

    public function close()
    {
        $user = Auth::user();
        if ($user && !empty($this->currentVersion)) {
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
            <!-- Backdrop overlay com blur -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" wire:click="close"></div>

            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div class="relative z-10 w-full max-w-xl transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
                    <!-- Top Gradient Header -->
                    <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-indigo-950 p-6 sm:p-7 text-white relative overflow-hidden">
                        <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-indigo-500/25 blur-2xl pointer-events-none"></div>
                        <div class="absolute -left-8 -bottom-8 w-28 h-28 rounded-full bg-purple-500/20 blur-xl pointer-events-none"></div>
                        
                        <!-- Botão Fechar no canto superior -->
                        <button type="button" wire:click="close" class="absolute top-4 right-4 p-1.5 rounded-full text-indigo-300 hover:text-white hover:bg-white/10 transition cursor-pointer" title="Fechar">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <div class="relative z-10 pr-6">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 mb-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Novidades da Atualização
                            </div>
                            <h3 class="text-xl sm:text-2xl font-black tracking-tight flex items-center gap-2" id="version-modal-title">
                                PontoFácil {{ $currentVersion }} 🎉
                            </h3>
                            <p class="text-xs sm:text-sm text-indigo-200 mt-1">
                                Confira as novidades e melhorias aplicadas no sistema nesta versão.
                            </p>
                        </div>
                    </div>

                    <!-- Corpo com as Notas da Versão -->
                    <div class="p-5 sm:p-6 space-y-4">
                        <div class="text-xs sm:text-sm text-slate-700 max-h-80 overflow-y-auto space-y-2 bg-slate-50/90 p-4 sm:p-5 rounded-2xl border border-slate-200/90 prose-sm [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5 [&_ul_ul]:mt-1 [&_ul_ul]:pl-4 [&_li]:leading-relaxed [&_strong]:text-slate-900 [&_strong]:font-bold [&_code]:bg-indigo-50 [&_code]:text-indigo-700 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded-md [&_code]:font-mono [&_code]:text-[11px] [&_code]:border [&_code]:border-indigo-100/80">
                            {!! $releaseNotes !!}
                        </div>

                        <!-- Rodapé com Ações -->
                        <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100">
                            <a href="{{ route('help') }}" @click="$wire.close()" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition order-2 sm:order-1">
                                Ver manual e histórico completo &rarr;
                            </a>

                            <button type="button" 
                                    wire:click="close" 
                                    class="w-full sm:w-auto inline-flex justify-center items-center rounded-xl px-6 py-2.5 bg-indigo-600 text-xs sm:text-sm font-bold text-white hover:bg-indigo-700 active:bg-indigo-800 shadow-sm shadow-indigo-600/20 transition cursor-pointer order-1 sm:order-2">
                                Entendido, começar a usar 🚀
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>