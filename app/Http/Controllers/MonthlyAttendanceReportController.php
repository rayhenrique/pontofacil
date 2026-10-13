<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\Services\MonthlyAttendanceReportService;
use App\Models\MonthlyAttendanceReportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyAttendanceReportController extends Controller
{
    public function __construct(
        protected MonthlyAttendanceReportService $reportService,
    ) {}

    /**
     * Download do CSV UTF-8 estruturado para o setor de pagamento.
     */
    public function downloadCsv(Request $request): StreamedResponse
    {
        $user = Auth::user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, 'Acesso não autorizado ao relatório de frequência.');
        }

        ['year' => $year, 'month' => $month, 'filters' => $filters] = $this->validatedReportInput($request);

        $reportData = $this->reportService->getReportData($year, $month, $user, $filters);
        $csvContent = $this->reportService->generateCsv($reportData);

        // Registrar emissão no log
        $this->reportService->logReport($year, $month, $user, 'csv', $reportData, $filters);

        $prefix = $reportData['is_provisional'] ? 'previa_provisoria' : 'consolidado_frequencia';
        $filename = sprintf('%s_%04d_%02d.csv', $prefix, $year, $month);

        return response()->streamDownload(
            fn () => print ($csvContent),
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    /**
     * Visualização institucional para impressão direta / salvar em PDF.
     */
    public function printPdf(Request $request)
    {
        $user = Auth::user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, 'Acesso não autorizado ao relatório de frequência.');
        }

        ['year' => $year, 'month' => $month, 'filters' => $filters] = $this->validatedReportInput($request);

        $reportData = $this->reportService->getReportData($year, $month, $user, $filters);

        // Registrar emissão no log
        $this->reportService->logReport($year, $month, $user, 'pdf', $reportData, $filters);

        return view('reports.monthly-consolidated-print', [
            'reportData' => $reportData,
        ]);
    }

    /**
     * Registra ação explícita de encaminhamento do relatório ao setor de pagamento.
     */
    public function recordDispatch(Request $request, int $logId)
    {
        $user = Auth::user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, 'Acesso não autorizado.');
        }

        $log = MonthlyAttendanceReportLog::findOrFail($logId);
        $notes = $request->input('notes');

        if ($notes !== null && (! is_string($notes) || mb_strlen($notes) > 1000)) {
            abort(422, 'Observações de encaminhamento inválidas.');
        }

        $this->reportService->recordDispatchToPayroll($log, $user, $notes);

        return response()->json([
            'success' => true,
            'message' => 'Encaminhamento ao setor de pagamento registrado com sucesso.',
            'dispatched_at' => $log->dispatched_to_payroll_at->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Valida competência e filtros das exportações.
     *
     * @return array{year: int, month: int, filters: array{establishment_id: ?int, sector_id: ?int, employee_id: ?int}}
     */
    protected function validatedReportInput(Request $request): array
    {
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        if (! is_numeric($year) || ! is_numeric($month)) {
            abort(422, 'Competência inválida.');
        }

        $year = (int) $year;
        $month = (int) $month;

        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            abort(422, 'Competência inválida.');
        }

        $filters = [];
        foreach (['establishment_id', 'sector_id', 'employee_id'] as $key) {
            $value = $request->query($key);
            if ($value === null || $value === '') {
                $filters[$key] = null;

                continue;
            }

            if (! is_numeric($value) || (int) $value < 1) {
                abort(422, 'Filtros inválidos.');
            }

            $filters[$key] = (int) $value;
        }

        return ['year' => $year, 'month' => $month, 'filters' => $filters];
    }
}
