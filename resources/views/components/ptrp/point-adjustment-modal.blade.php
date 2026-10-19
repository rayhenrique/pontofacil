<!-- MODAL UNIFICADO: CORRIGIR PONTO (PORTARIA 671 MTP / PTRP) -->
@if($showAdjustmentModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-adj-title">
        <!-- Backdrop escurecido -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" wire:click="closePointAdjustmentModal"></div>

        <div class="flex min-h-full items-end sm:items-center justify-center p-2 sm:p-4 text-center">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all w-full max-w-2xl border border-gray-200">
                <!-- Cabeçalho do Modal -->
                <div class="bg-slate-900 px-4 sm:px-6 py-4 flex items-center justify-between text-white">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                                PTRP • Auditoria
                            </span>
                            <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($adjDate)->format('d/m/Y') }}</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-white mt-0.5" id="modal-adj-title">
                            Corrigir Ponto: {{ $adjEmployeeName }}
                        </h3>
                    </div>
                    <button type="button" wire:click="closePointAdjustmentModal" class="rounded-lg p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 transition min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Formulário -->
                <form wire:submit="submitPointAdjustment" class="p-4 sm:p-6 space-y-5 max-h-[80vh] overflow-y-auto">
                    <!-- 1. Seleção da Ação de Tratamento (4 opções claras) -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                            Selecione o Tipo de Correção
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <!-- Incluir Entrada -->
                            <button type="button" wire:click="$set('adjAction', 'add_entry')"
                                class="flex flex-col items-center justify-center p-3 rounded-xl border text-center transition min-h-[56px] {{ $adjAction === 'add_entry' ? 'bg-emerald-50 border-emerald-500 text-emerald-900 ring-2 ring-emerald-500/20 font-bold' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                                <span class="text-xs">🟢 Incluir Entrada</span>
                                <span class="text-[10px] text-gray-500 font-normal mt-0.5">Batida inicial</span>
                            </button>

                            <!-- Incluir Saída -->
                            <button type="button" wire:click="$set('adjAction', 'add_exit')"
                                class="flex flex-col items-center justify-center p-3 rounded-xl border text-center transition min-h-[56px] {{ $adjAction === 'add_exit' ? 'bg-sky-50 border-sky-500 text-sky-900 ring-2 ring-sky-500/20 font-bold' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                                <span class="text-xs">🔵 Incluir Saída</span>
                                <span class="text-[10px] text-gray-500 font-normal mt-0.5">Término/Intervalo</span>
                            </button>

                            <!-- Desconsiderar Marcação -->
                            <button type="button" wire:click="$set('adjAction', 'disregard_punch')"
                                class="flex flex-col items-center justify-center p-3 rounded-xl border text-center transition min-h-[56px] {{ $adjAction === 'disregard_punch' ? 'bg-amber-50 border-amber-500 text-amber-900 ring-2 ring-amber-500/20 font-bold' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                                <span class="text-xs">🟡 Desconsiderar</span>
                                <span class="text-[10px] text-gray-500 font-normal mt-0.5">Batida indevida</span>
                            </button>

                            <!-- Justificar Ausência -->
                            <button type="button" wire:click="$set('adjAction', 'justify_absence')"
                                class="flex flex-col items-center justify-center p-3 rounded-xl border text-center transition min-h-[56px] {{ $adjAction === 'justify_absence' ? 'bg-purple-50 border-purple-500 text-purple-900 ring-2 ring-purple-500/20 font-bold' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                                <span class="text-xs">🟣 Justificar Falta</span>
                                <span class="text-[10px] text-gray-500 font-normal mt-0.5">Atestado/Legal</span>
                            </button>
                        </div>
                        @error('adjAction') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- 2. Parâmetros da Ação -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50/80 p-4 rounded-xl border border-gray-200">
                        <!-- Data do Registro -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                Data da Ocorrência
                            </label>
                            <input type="date" wire:model.live="adjDate" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white min-h-[44px]" required>
                            @error('adjDate') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Horário (para Inclusão de Entrada/Saída) -->
                        @if($adjAction === 'add_entry' || $adjAction === 'add_exit')
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                    Horário da Marcação <span class="text-red-500">*</span>
                                </label>
                                <input type="time" wire:model.live.debounce.300ms="adjTime" class="block w-full px-3 py-2 text-sm font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white min-h-[44px]" placeholder="--:--" required>
                                <span class="text-[11px] text-gray-500 mt-0.5 block">Informe a hora e minuto reais da batida</span>
                                @error('adjTime') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <!-- Seleção da Batida a Desconsiderar -->
                        @if($adjAction === 'disregard_punch')
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                    Selecione a Marcação a Desconsiderar <span class="text-red-500">*</span>
                                </label>
                                @if(empty($adjAvailablePunches))
                                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs">
                                        Não existem marcações registradas para esta data. Selecione uma data com batidas para desconsiderar.
                                    </div>
                                @else
                                    <select wire:model.live="adjRefPunchId" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white min-h-[44px]" required>
                                        <option value="">Selecione uma marcação</option>
                                        @foreach($adjAvailablePunches as $p)
                                            <option value="{{ $p['id'] }}" {{ !empty($p['is_disregarded']) ? 'disabled' : '' }}>
                                                {{ $p['label'] }} {{ !empty($p['is_disregarded']) ? '(Já desconsiderada)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('adjRefPunchId') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <!-- Categoria Legal (para Justificar Ausência) -->
                        @if($adjAction === 'justify_absence')
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                    Motivo Legal / Amparo da Ausência <span class="text-red-500">*</span>
                                </label>
                                <select wire:model.live="adjReasonCategory" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white min-h-[44px]" required>
                                    <option value="medical_certificate">Atestado Médico (Art. 473 CLT / Regime)</option>
                                    <option value="medical_appointment">Consulta / Exame Médico Acompanhado</option>
                                    <option value="bereavement">Licença Nojo / Luto Familiar</option>
                                    <option value="wedding">Licença Gala / Casamento</option>
                                    <option value="blood_donation">Doação Voluntária de Sangue</option>
                                    <option value="court_summons">Convocação Judicial / Júri</option>
                                    <option value="other">Outra Justificativa Legal / Administrativa</option>
                                </select>
                                @error('adjReasonCategory') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <!-- 3. PRÉVIA DA SEQUÊNCIA RESULTANTE (Item 9) -->
                    <div class="border border-indigo-100 bg-indigo-50/40 rounded-xl p-3.5 sm:p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Prévia da Sequência no Dia
                            </span>
                            <span class="text-[11px] text-indigo-700 font-medium">
                                {{ $adjSequencePreview['summary'] ?? 'Calculando prévia...' }}
                            </span>
                        </div>

                        <!-- Lista Cronológica de Marcações -->
                        @if(!empty($adjSequencePreview['items']))
                            <div class="space-y-1.5 mt-2">
                                @foreach($adjSequencePreview['items'] as $item)
                                    <div class="flex items-center justify-between px-3 py-1.5 rounded-lg border text-xs {{ $item['badge_class'] }}">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-sm">{{ $item['time'] }}</span>
                                            <span class="font-semibold uppercase tracking-wider text-[10px]">
                                                {{ $item['direction'] === 'in' ? 'Entrada' : 'Saída' }}
                                            </span>
                                            <span class="text-xs opacity-90">· {{ $item['description'] }}</span>
                                        </div>
                                        @if($item['type'] === 'proposed_addition' || $item['type'] === 'proposed_disregard')
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-black/20 text-white">
                                                Proposta
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-3 bg-white/70 border border-gray-200 rounded-lg text-center text-xs text-gray-500">
                                @if($adjAction === 'justify_absence')
                                    Ausência justificada para o dia inteiro (sem necessidade de horários de batida).
                                @else
                                    Nenhuma marcação na data. Digite o horário acima para visualizar a prévia da sequência.
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- 4. Justificativa Formal da Auditoria -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Justificativa Legal / Motivo Auditado <span class="text-red-500">*</span>
                        </label>
                        <textarea wire:model="adjReason" rows="2" class="block w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Esquecimento de registro na saída do almoço conforme autorizado pela chefia imediata." required></textarea>
                        <p class="text-[11px] text-gray-500 mt-1">
                            Esta justificativa ficará permanentemente registrada no PTRP sob a identidade de <strong>{{ Auth::user()->name }}</strong> (Portaria 671/2021 MTP).
                        </p>
                        @error('adjReason') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- 5. Anexo / Atestado (se aplicável) -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                            Documento / Atestado Comprobatório {{ $adjAction === 'justify_absence' && $adjReasonCategory === 'medical_certificate' ? '(Obrigatório)' : '(Opcional)' }}
                        </label>
                        <input type="file" wire:model="adjAttachment" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 min-h-[44px]">
                        <span class="text-[10px] text-gray-400 mt-0.5 block">Formatos permitidos: PDF, JPG, PNG (máx: 5MB). Armazenamento criptografado e restrito (LGPD).</span>
                        @error('adjAttachment') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- 6. Opção de Aprovação Direta com Auditoria -->
                    @if($adjCanDirectApprove)
                        <div class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-xl">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model.live="adjDirectApproval" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <span class="text-xs font-bold text-emerald-900 block">
                                        Aprovar e aplicar imediatamente na apuração PTRP
                                    </span>
                                    <span class="text-[11px] text-emerald-700 block mt-0.5">
                                        O ajuste será registrado com sua aprovação formal, atualizando instantaneamente o Espelho, a Folha e a apuração de horas.
                                    </span>
                                </div>
                            </label>
                        </div>
                    @else
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-[11px] text-slate-600">
                            {{ $adjDirectApprovalNotice }}
                        </div>
                    @endif

                    <!-- Rodapé com Ações -->
                    <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="closePointAdjustmentModal" class="w-full sm:w-auto px-4 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 text-xs font-semibold min-h-[44px] transition">
                            Cancelar
                        </button>
                        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm min-h-[44px] transition flex items-center justify-center gap-1.5" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submitPointAdjustment">
                                {{ $adjDirectApproval ? 'Aprovar e Aplicar Ajuste' : 'Enviar Solicitação de Ajuste' }}
                            </span>
                            <span wire:loading wire:target="submitPointAdjustment">
                                Gravando no PTRP...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
