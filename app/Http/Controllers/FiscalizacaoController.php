<?php

namespace App\Http\Controllers;

use App\Domain\Compliance\AEJ\AejGenerator_2026_07_31;
use App\Domain\Compliance\AFD\AfdGenerator_2026_07_31;
use App\Domain\Compliance\FiscalPackage\GenerateFiscalPackageAction;
use App\Models\Establishment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FiscalizacaoController extends Controller
{
    public function downloadPackage(Request $request, int $establishmentId, int $year, int $month): BinaryFileResponse
    {
        if (! Auth::user()?->canViewFiscalizacao()) {
            abort(403, 'Acesso não autorizado à Central de Fiscalização.');
        }

        $establishment = Establishment::with('company')->findOrFail($establishmentId);

        $action = app(GenerateFiscalPackageAction::class);
        $result = $action->execute(
            establishment: $establishment,
            year: $year,
            month: $month,
            requestedBy: Auth::user()
        );

        return response()->download($result['zip_path'], $result['filename'], [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function downloadAfd(Request $request, int $establishmentId, int $year, int $month): Response
    {
        if (! Auth::user()?->canViewFiscalizacao()) {
            abort(403, 'Acesso não autorizado à Central de Fiscalização.');
        }

        $establishment = Establishment::with('company')->findOrFail($establishmentId);
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $generator = app(AfdGenerator_2026_07_31::class);
        $result = $generator->generate($establishment, $startDate, $endDate);

        return response($result->content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $result->filename),
        ]);
    }

    public function downloadAej(Request $request, int $establishmentId, int $year, int $month): Response
    {
        if (! Auth::user()?->canViewFiscalizacao()) {
            abort(403, 'Acesso não autorizado à Central de Fiscalização.');
        }

        $establishment = Establishment::with('company')->findOrFail($establishmentId);

        $generator = app(AejGenerator_2026_07_31::class);
        $result = $generator->generate(
            establishment: $establishment,
            year: $year,
            month: $month,
            forcePreview: $request->boolean('previa', false)
        );

        return response($result->content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $result->filename),
        ]);
    }
}
