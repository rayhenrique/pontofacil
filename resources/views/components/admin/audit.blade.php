<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TimeAdjustment;

new #[Layout('layouts.app')] #[Title('Trilha de Auditoria')] class extends Component
{
    public function with()
    {
        return [
            'adjustments' => TimeAdjustment::with(['timeEntry.user', 'admin'])->orderBy('created_at', 'desc')->get()
        ];
    }
};
?>
<div class="max-w-7xl mx-auto py-2 sm:py-6 px-1 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4 sm:p-6">
        <div class="mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Trilha de Auditoria</h2>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Histórico imutável de ajustes manuais efetuados pelo RH</p>
        </div>

        <div class="overflow-x-auto -mx-4 sm:mx-0 border border-gray-200 rounded-xl shadow-xs mt-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Data do Ajuste</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Colaborador</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">RH Responsável</th>
                        <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Justificativa</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($adjustments as $adj)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">{{ $adj->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ optional(optional($adj->timeEntry)->user)->name ?? 'N/A' }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-indigo-700 font-medium">{{ optional($adj->admin)->name ?? 'N/A' }}</td>
                            <td class="px-4 sm:px-6 py-4 text-sm text-gray-600 min-w-[200px]">{{ $adj->justification }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 sm:px-6 py-12 text-center text-sm text-gray-500">Nenhum ajuste manual registrado na auditoria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>