<?php

declare(strict_types=1);

use App\Modules\Iam\Http\Controllers\AuthController;
use App\Modules\Iam\Http\Controllers\DashboardController;
use App\Modules\Patient\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('patients')->name('patients.')->group(function (): void {
        Route::get('/', [PatientController::class, 'index'])->name('index');
        Route::get('/create', [PatientController::class, 'create'])->name('create');
        Route::post('/', [PatientController::class, 'store'])->name('store');
        Route::get('/merge', [PatientController::class, 'showMergeForm'])->name('merge.form');
        Route::post('/merge', [PatientController::class, 'merge'])->name('merge.execute');

        Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
        Route::get('/{patient}/edit', [PatientController::class, 'edit'])->name('edit');
        Route::put('/{patient}', [PatientController::class, 'update'])->name('update');
        Route::get('/{patient}/card', [PatientController::class, 'card'])->name('card');
        Route::post('/{patient}/break-glass', [PatientController::class, 'breakGlass'])->name('break-glass');
    });
});
