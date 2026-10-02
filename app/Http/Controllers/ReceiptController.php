<?php

namespace App\Http\Controllers;

use App\Domain\Company\Services\CurrentCompany;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\Compliance\Receipt\ReceiptPdfGenerator;
use App\Enums\UserRole;
use App\Models\Establishment;
use App\Models\PunchReceipt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    /**
     * Download do Comprovante do Trabalhador em PDF.
     */
    public function downloadPdf(string $code, ReceiptPdfGenerator $generator): StreamedResponse
    {
        $receipt = PunchReceipt::with([
            'punchEvent.establishment.company',
            'punchEvent.user',
            'punchEvent.employee',
        ])->where('verification_code', $code)->firstOrFail();

        // Se autenticado e for colaborador, restringe aos próprios comprovantes
        if (Auth::check() && Auth::user()->role === UserRole::Employee) {
            abort_if($receipt->punchEvent->user_id !== Auth::id(), 403, 'Acesso não autorizado ao comprovante.');
        }

        $pdfBinary = $generator->generate($receipt);

        return response()->streamDownload(
            fn () => print ($pdfBinary),
            "comprovante_{$receipt->verification_code}.pdf",
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"comprovante_{$receipt->verification_code}.pdf\"",
            ]
        );
    }

    /**
     * Exibição do Comprovante em HTML estilizado para impressão direta A4/cupom no navegador.
     */
    public function printHtml(string $code)
    {
        $receipt = PunchReceipt::with([
            'punchEvent.establishment.company',
            'punchEvent.user',
            'punchEvent.employee',
        ])->where('verification_code', $code)->firstOrFail();

        if (Auth::check() && Auth::user()->role === UserRole::Employee) {
            abort_if($receipt->punchEvent->user_id !== Auth::id(), 403, 'Acesso não autorizado ao comprovante.');
        }

        return view('receipts.print', compact('receipt'));
    }

    /**
     * Exportação do Arquivo Fonte de Dados (AFD) posicional para Administradores/Auditores.
     */
    public function exportAfd(Request $request): Response
    {
        $user = Auth::user();
        abort_unless($user && $user->role === UserRole::Admin, 403, 'Acesso restrito ao Administrador do Sistema.');

        $establishmentId = $request->query('establishment_id');
        $establishment = $establishmentId
            ? Establishment::where('id', $establishmentId)->firstOrFail()
            : CurrentCompany::defaultEstablishment();

        $startStr = $request->query('start', now()->startOfMonth()->toDateString());
        $endStr = $request->query('end', now()->endOfMonth()->toDateString());

        $startDate = Carbon::parse($startStr);
        $endDate = Carbon::parse($endStr);

        $generator = new AfdGenerator_2026_07_31;
        $result = $generator->generate($establishment, $startDate, $endDate);

        return response($result->content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$result->filename}\"",
            'X-AFD-CRC' => $result->crcChecksum,
            'X-AFD-Records' => $result->totalRecords,
        ]);
    }
}
