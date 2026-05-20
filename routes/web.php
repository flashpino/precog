<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AdminAuthController;

// --- ROTAS PÚBLICAS (sem autenticação) ---
Route::get('/view/{token}', [ClientDashboardController::class, 'show'])->name('client.dashboard');
Route::get('/api/telemetry', [DashboardController::class, 'telemetry'])->name('api.telemetry');

// --- AUTH ROUTES ---
Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');
Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// --- ROTAS PROTEGIDAS (requerem login admin) ---
Route::middleware(['admin.auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('clients', ClientController::class);
    Route::resource('contacts', App\Http\Controllers\ContactController::class)->except(['create', 'show', 'edit']);
    Route::resource('sensors', SensorController::class);
    Route::resource('admin-contacts', App\Http\Controllers\AdminContactController::class)->except(['show', 'create', 'edit']);
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::post('/settings/migrate', [SettingsController::class, 'runMigrations'])->name('settings.migrate');
    Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache'])->name('settings.clear-cache');
});
