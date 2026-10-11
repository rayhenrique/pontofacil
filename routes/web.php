<?php

use App\Domain\Company\Services\CurrentCompany;
use App\Http\Controllers\FiscalizacaoController;
use App\Http\Controllers\ReceiptController;
use App\Models\Sector;
use App\Models\TreatmentEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Página Inicial (Landing Page)
Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/landing', function () {
    return view('landing');
});

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
});

// Public Verification & Receipt Routes
Route::livewire('/verificar-comprovante', 'receipt-verification')->name('receipts.verify');
Route::get('/comprovante/{code}/pdf', [ReceiptController::class, 'downloadPdf'])->name('receipts.pdf');
Route::get('/comprovante/{code}/imprimir', [ReceiptController::class, 'printHtml'])->name('receipts.print');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    // Bater Ponto (Tela Inicial do Sistema Autenticado)
    Route::livewire('/ponto', 'time-punch')->name('home');

    // Logout
    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect('/login');
    })->name('logout');

    Route::livewire('/timesheet', 'timesheet')->name('timesheet');
    Route::livewire('/folha-ponto', 'folha-ponto')->name('folha-ponto');
    Route::livewire('/comprovantes', 'receipts-center')->name('receipts.center');
    Route::livewire('/ajuda', 'help')->name('help');

    // Management Routes (Admin & Gestor)
    Route::middleware('can:manageEmployees,App\Models\User')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/employees', 'admin.employees')->name('employees');
        Route::livewire('/shifts', 'admin.shifts-management')->name('shifts');
    });

    // PTRP Treatment Requests (Admin & Gestor - Segregação Operacional / RH)
    Route::middleware('can:manageTreatments,App\Models\User')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/treatment-requests', 'admin.treatment-requests')->name('treatment-requests');
    });

    // Visualização Segura de Atestados / Comprovantes de Tratamento (Admin, Gestor do Setor ou Próprio Colaborador)
    Route::get('/treatment-attachment/{id}', function (string $id) {
        $event = TreatmentEvent::with('employee.sector')->findOrFail($id);
        $user = Auth::user();

        $canView = false;
        if ($user->isAdmin()) {
            $canView = true;
        } elseif ($user->isManager()) {
            $managedSectorIds = Sector::where('manager_id', $user->id)->pluck('id');
            if ($event->employee && $managedSectorIds->contains($event->employee->sector_id)) {
                $canView = true;
            }
        } elseif ($event->requested_by === $user->id || ($event->employee && $event->employee->user_id === $user->id)) {
            $canView = true;
        }

        if (! $canView || ! $event->attachment_path) {
            abort(403, 'Acesso não autorizado ao comprovante/atestado médico.');
        }

        // Armazenamento privado (local) para novos documentos; fallback para public para registros legados
        if (Storage::disk('local')->exists($event->attachment_path)) {
            return Storage::disk('local')->response($event->attachment_path);
        }

        if (Storage::disk('public')->exists($event->attachment_path)) {
            return Storage::disk('public')->response($event->attachment_path);
        }

        abort(404, 'Comprovante/atestado médico não encontrado no armazenamento.');
    })->name('treatment.attachment');

    // Admin Exclusive Routes (RH Total)
    Route::middleware('can:manageTimeEntries,App\Models\User')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/adjustment', 'admin.manual-adjustment')->name('adjustment');
        Route::livewire('/sectors', 'admin.sectors')->name('sectors');
        Route::livewire('/users', 'admin.users')->name('users');
        Route::livewire('/audit', 'admin.audit')->name('audit');
        Route::livewire('/reports', 'admin.reports')->name('reports');
        Route::livewire('/time-bank', 'admin.time-bank')->name('time-bank');
        Route::livewire('/settlement-policies', 'admin.settlement-policies')->name('settlement-policies');
        Route::livewire('/settings', 'admin.settings')->name('settings');
        Route::livewire('/calendar', 'admin.calendar')->name('calendar');
        Route::get('/exportar-afd', [ReceiptController::class, 'exportAfd'])->name('export-afd');
    });

    // Fiscalização MTE (Admin & Auditor)
    Route::middleware('can:viewFiscalizacao,App\Models\User')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/fiscalizacao', 'admin.fiscalizacao')->name('fiscalizacao');
        Route::get('/fiscalizacao/pacote/{establishmentId}/{year}/{month}', [FiscalizacaoController::class, 'downloadPackage'])->name('fiscalizacao.package');
        Route::get('/fiscalizacao/afd/{establishmentId}/{year}/{month}', [FiscalizacaoController::class, 'downloadAfd'])->name('fiscalizacao.afd');
        Route::get('/fiscalizacao/aej/{establishmentId}/{year}/{month}', [FiscalizacaoController::class, 'downloadAej'])->name('fiscalizacao.aej');
    });
});

// Endpoint dedicado para streaming seguro do logotipo da empresa
Route::get('/company-logo', function () {
    $company = CurrentCompany::get();
    if ($company?->logo_path && Storage::disk('public')->exists($company->logo_path)) {
        return Storage::disk('public')->response($company->logo_path);
    }
    abort(404);
})->name('company.logo');
