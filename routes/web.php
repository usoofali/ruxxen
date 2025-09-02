<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Dashboard route using Livewire component
Volt::route('dashboard', 'dashboard')->middleware(['auth', 'verified'])->name('dashboard');

// LPG System Routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Cashier Routes
    Route::middleware(['auth.role:cashier'])->group(function () {
        Volt::route('sales', 'sales.create')->name('sales.create');
        Volt::route('sales/history', 'sales.history')->name('sales.history');
    });

    // Admin Routes
    Route::middleware(['auth.role:admin'])->group(function () {
        Volt::route('admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
        Volt::route('admin/inventory', 'admin.inventory')->name('admin.inventory');
        Volt::route('admin/transactions', 'admin.transactions')->name('admin.transactions');
        Volt::route('admin/reports', 'admin.reports')->name('admin.reports');
        Volt::route('admin/users', 'admin.users')->name('admin.users');
    });

               // Shared Routes (both admin and cashier)
           Route::middleware(['auth.role:admin,cashier'])->group(function () {
               Volt::route('profile', 'profile.show')->name('profile.show');
           });

           // Admin-only Settings Routes
           Route::middleware(['auth.role:admin'])->group(function () {
               Volt::route('settings/company', 'settings.company')->name('settings.company');
           });

           // Company Settings View Route (for all authenticated users)
           Route::middleware(['auth.role:admin,cashier'])->group(function () {
               Volt::route('settings/company/view', 'settings.company-view')->name('settings.company.view');
           });

           // Sync Monitor Route (cashier only)
           Route::middleware(['auth.role:cashier'])->group(function () {
               Volt::route('cashier/sync', 'sync-monitor')->name('cashier.sync');
           });
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
