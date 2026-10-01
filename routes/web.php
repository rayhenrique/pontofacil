<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    // Tela Inicial (Employee & Admin)
    Route::livewire('/', 'time-punch')->name('home');

    // Logout
    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect('/login');
    })->name('logout');

    Route::livewire('/timesheet', 'timesheet')->name('timesheet');
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
    });
});
