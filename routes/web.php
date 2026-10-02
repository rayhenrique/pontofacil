<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    Route::livewire('/ajuda', 'help')->name('help');

    // Management Routes (Admin & Gestor)
    Route::middleware('can:manageEmployees,App\Models\User')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/employees', 'admin.employees')->name('employees');
    });

    // Admin Exclusive Routes (RH Total)
    Route::middleware('can:manageTimeEntries,App\Models\User')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/adjustment', 'admin.manual-adjustment')->name('adjustment');
        Route::livewire('/sectors', 'admin.sectors')->name('sectors');
        Route::livewire('/users', 'admin.users')->name('users');
        Route::livewire('/audit', 'admin.audit')->name('audit');
        Route::livewire('/reports', 'admin.reports')->name('reports');
        Route::livewire('/settings', 'admin.settings')->name('settings');
    });
});
