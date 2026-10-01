<div x-data="modalFeedbackComponent()" class="relative">
    <!-- ========================================== -->
    <!-- 1. MODAL DE ALERTA / SUCESSO / ERRO / INFO -->
    <!-- ========================================== -->
    <div x-show="alertOpen" 
         x-cloak 
         class="fixed inset-0 z-[60] overflow-y-auto" 
         aria-labelledby="modal-alert-title" 
         role="dialog" 
         aria-modal="true" 
         style="display: none;">
        
        <!-- Backdrop com transição suave -->
        <div x-show="alertOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
             @click="closeAlert()"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div x-show="alertOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative z-10 w-full max-w-sm sm:max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-center shadow-2xl transition-all sm:my-8 border border-gray-100">
                
                <!-- Ícone animado conforme o tipo -->
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full mb-4"
                     :class="{
                        'bg-emerald-100 ring-8 ring-emerald-50 text-emerald-600': alertType === 'success',
                        'bg-red-100 ring-8 ring-red-50 text-red-600': alertType === 'error',
                        'bg-amber-100 ring-8 ring-amber-50 text-amber-600': alertType === 'warning',
                        'bg-indigo-100 ring-8 ring-indigo-50 text-indigo-600': alertType === 'info'
                     }">
                    
                    <!-- Ícone Sucesso (Check) -->
                    <template x-if="alertType === 'success'">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </template>

                    <!-- Ícone Erro (X) -->
                    <template x-if="alertType === 'error'">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </template>

                    <!-- Ícone Alerta (Exclamação) -->
                    <template x-if="alertType === 'warning'">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </template>

                    <!-- Ícone Info (Info) -->
                    <template x-if="alertType === 'info'">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                    </template>
                </div>

                <!-- Título -->
                <h3 class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight" id="modal-alert-title" x-text="alertTitle"></h3>

                <!-- Mensagem -->
                <div class="mt-2 text-sm text-gray-600 leading-relaxed max-h-60 overflow-y-auto px-2" x-text="alertMessage"></div>

                <!-- Botão de Ação -->
                <div class="mt-6">
                    <button type="button" 
                            @click="closeAlert()"
                            class="w-full inline-flex justify-center items-center px-5 py-3 rounded-xl text-sm font-bold text-white shadow-md transition touch-manipulation focus:outline-hidden"
                            :class="{
                                'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 shadow-emerald-600/20': alertType === 'success',
                                'bg-red-600 hover:bg-red-700 active:bg-red-800 shadow-red-600/20': alertType === 'error',
                                'bg-amber-600 hover:bg-amber-700 active:bg-amber-800 shadow-amber-600/20': alertType === 'warning',
                                'bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 shadow-indigo-600/20': alertType === 'info'
                            }">
                        <span x-text="alertButtonText"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. MODAL DE CONFIRMAÇÃO (ACTIONS/EXCLUSÃO) -->
    <!-- ========================================== -->
    <div x-show="confirmOpen" 
         x-cloak 
         class="fixed inset-0 z-[60] overflow-y-auto" 
         aria-labelledby="modal-confirm-title" 
         role="dialog" 
         aria-modal="true" 
         style="display: none;">
        
        <!-- Backdrop -->
        <div x-show="confirmOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
             @click="closeConfirm()"></div>

        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div x-show="confirmOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative z-10 w-full max-w-sm sm:max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-center shadow-2xl transition-all sm:my-8 border border-gray-100">
                
                <!-- Ícone -->
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full mb-4"
                     :class="confirmIsDanger ? 'bg-red-100 ring-8 ring-red-50 text-red-600' : 'bg-indigo-100 ring-8 ring-indigo-50 text-indigo-600'">
                    
                    <template x-if="confirmIsDanger">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </template>

                    <template x-if="!confirmIsDanger">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                    </template>
                </div>

                <!-- Título -->
                <h3 class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight" id="modal-confirm-title" x-text="confirmTitle"></h3>

                <!-- Mensagem -->
                <p class="mt-2 text-sm text-gray-600 leading-relaxed px-2" x-text="confirmMessage"></p>

                <!-- Botões de Ação -->
                <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
                    <button type="button" 
                            @click="closeConfirm()" 
                            class="w-full sm:flex-1 inline-flex justify-center items-center px-4 py-3 rounded-xl border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50 transition touch-manipulation focus:outline-hidden">
                        <span x-text="cancelText"></span>
                    </button>
                    <button type="button" 
                            @click="handleConfirm()" 
                            class="w-full sm:flex-1 inline-flex justify-center items-center px-4 py-3 rounded-xl text-sm font-bold text-white shadow-md transition touch-manipulation focus:outline-hidden"
                            :class="confirmIsDanger ? 'bg-red-600 hover:bg-red-700 active:bg-red-800 shadow-red-600/20' : 'bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 shadow-indigo-600/20'">
                        <span x-text="confirmText"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('modalFeedbackComponent', () => ({
            // Alert Modal state
            alertOpen: false,
            alertType: 'success',
            alertTitle: '',
            alertMessage: '',
            alertButtonText: 'Entendi',

            // Confirm Modal state
            confirmOpen: false,
            confirmTitle: '',
            confirmMessage: '',
            confirmText: 'Confirmar',
            cancelText: 'Cancelar',
            confirmIsDanger: true,
            confirmCallback: null,

            init() {
                // Flash Session Handler
                @if(session()->has('message') || session()->has('success'))
                    this.openAlert({
                        type: 'success',
                        title: 'Operação Realizada com Sucesso!',
                        message: @js(session('message') ?? session('success')),
                        buttonText: 'OK, Entendi'
                    });
                @elseif(session()->has('error'))
                    this.openAlert({
                        type: 'error',
                        title: 'Atenção!',
                        message: @js(session('error')),
                        buttonText: 'OK, Entendi'
                    });
                @endif

                // Listen to Livewire & Window Events
                window.addEventListener('app-modal-alert', (e) => {
                    const detail = e.detail && e.detail[0] ? e.detail[0] : e.detail;
                    this.openAlert(detail);
                });

                window.addEventListener('app-modal-confirm', (e) => {
                    const detail = e.detail && e.detail[0] ? e.detail[0] : e.detail;
                    this.openConfirm(detail);
                });
            },

            openAlert(data) {
                if (!data) return;
                this.alertType = data.type || 'info';
                this.alertTitle = data.title || (data.type === 'error' ? 'Atenção' : (data.type === 'success' ? 'Sucesso!' : 'Informação'));
                this.alertMessage = data.message || '';
                this.alertButtonText = data.buttonText || 'Entendi';
                this.alertOpen = true;
            },

            closeAlert() {
                this.alertOpen = false;
            },

            openConfirm(data) {
                if (!data) return;
                this.confirmTitle = data.title || 'Confirmar Ação';
                this.confirmMessage = data.message || 'Tem certeza que deseja realizar esta operação?';
                this.confirmText = data.confirmText || 'Confirmar';
                this.cancelText = data.cancelText || 'Cancelar';
                this.confirmIsDanger = data.isDanger !== undefined ? Boolean(data.isDanger) : true;
                this.confirmCallback = data.onConfirm || null;
                this.confirmOpen = true;
            },

            handleConfirm() {
                const cb = this.confirmCallback;
                this.confirmOpen = false;
                this.confirmCallback = null;
                if (typeof cb === 'function') {
                    cb();
                }
            },

            closeConfirm() {
                this.confirmOpen = false;
                this.confirmCallback = null;
            }
        }));
    });

    // Helper functions on window
    window.showModalAlert = function(type, title, message, buttonText) {
        window.dispatchEvent(new CustomEvent('app-modal-alert', {
            detail: { type, title, message, buttonText }
        }));
    };

    window.showModalConfirm = function(options) {
        window.dispatchEvent(new CustomEvent('app-modal-confirm', {
            detail: options
        }));
    };
</script>
