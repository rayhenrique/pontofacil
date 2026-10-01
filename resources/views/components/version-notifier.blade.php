<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

new class extends Component
{
    public $showModal = false;
    public $currentVersion = 'v1.2.0';
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
                $this->releaseNotes = Str::markdown($matches[1]);
            } else {
                $this->releaseNotes = Str::markdown($content);
            }
        }
    }

    public function close()
    {
        $user = Auth::user();
        $user->last_seen_version = $this->currentVersion;
        $user->save();

        $this->showModal = false;
    }
};
?>

<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" wire:transition>
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="close"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div>
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Bem-vindo à Versão {{ $currentVersion }}! 🎉
                            </h3>
                            <div class="mt-4 text-sm text-gray-600 text-left prose prose-sm max-h-64 overflow-y-auto bg-gray-50 p-4 rounded-md">
                                {!! $releaseNotes !!}
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6">
                        <button type="button" wire:click="close" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Entendi, continuar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>