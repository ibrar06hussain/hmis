<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\BillingDeskController;
use App\Modules\Emergency\Http\Controllers\EmergencyController;
use App\Modules\Emr\Http\Controllers\EncounterWorkspaceController;
use App\Modules\Hr\Http\Controllers\DutyRosterController;
use App\Modules\Iam\Http\Controllers\AuthController;
use App\Modules\Iam\Http\Controllers\DashboardController;
use App\Modules\Ipd\Http\Controllers\IpdController;
use App\Modules\Lis\Http\Controllers\LisWorkbenchController;
use App\Modules\Nursing\Http\Controllers\NursingStationController;
use App\Modules\Opd\Http\Controllers\OpdQueueController;
use App\Modules\Patient\Http\Controllers\PatientController;
use App\Modules\Pharmacy\Http\Controllers\PharmacyWorkbenchController;
use App\Modules\Reporting\Http\Controllers\ExecutiveReportController;
use App\Modules\Ris\Http\Controllers\RisWorkbenchController;
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
        Route::post('/{patient}/allergies', [EncounterWorkspaceController::class, 'storeAllergy'])->name('allergies.store');
    });

    Route::prefix('opd')->name('opd.')->group(function (): void {
        Route::get('/', [OpdQueueController::class, 'index'])->name('index');
        Route::post('/appointments', [OpdQueueController::class, 'store'])->name('appointments.store');
        Route::post('/appointments/{appointment}/check-in', [OpdQueueController::class, 'checkIn'])->name('appointments.check-in');
        Route::post('/appointments/{appointment}/start-consult', [OpdQueueController::class, 'startConsult'])->name('appointments.start-consult');
        Route::post('/appointments/{appointment}/cancel', [OpdQueueController::class, 'cancel'])->name('appointments.cancel');
    });

    Route::prefix('emergency')->name('emergency.')->group(function (): void {
        Route::get('/', [EmergencyController::class, 'index'])->name('index');
        Route::post('/triage', [EmergencyController::class, 'store'])->name('triage.store');
        Route::post('/triage/{triage}/disposition', [EmergencyController::class, 'updateDisposition'])->name('triage.disposition');
    });

    Route::prefix('ipd')->name('ipd.')->group(function (): void {
        Route::get('/', [IpdController::class, 'index'])->name('index');
        Route::post('/admit', [IpdController::class, 'admit'])->name('admit');
        Route::post('/admissions/{admission}/transfer', [IpdController::class, 'transferBed'])->name('admissions.transfer');
        Route::post('/admissions/{admission}/discharge', [IpdController::class, 'discharge'])->name('admissions.discharge');
        Route::post('/beds/{bed}/mark-available', [IpdController::class, 'markBedAvailable'])->name('beds.mark-available');
    });

    Route::prefix('nursing')->name('nursing.')->group(function (): void {
        Route::get('/', [NursingStationController::class, 'index'])->name('index');
        Route::post('/mar', [NursingStationController::class, 'storeMar'])->name('mar.store');
        Route::post('/notes', [NursingStationController::class, 'storeNote'])->name('notes.store');
    });

    Route::prefix('lis')->name('lis.')->group(function (): void {
        Route::get('/', [LisWorkbenchController::class, 'index'])->name('index');
        Route::post('/specimens/{specimen}/collect', [LisWorkbenchController::class, 'collectSpecimen'])->name('specimens.collect');
        Route::post('/specimens/{specimen}/receive', [LisWorkbenchController::class, 'receiveSpecimen'])->name('specimens.receive');
        Route::post('/specimens/{specimen}/reject', [LisWorkbenchController::class, 'rejectSpecimen'])->name('specimens.reject');
        Route::post('/items/{item}/result', [LisWorkbenchController::class, 'enterResult'])->name('items.result');
        Route::post('/items/{item}/tech-validate', [LisWorkbenchController::class, 'techValidate'])->name('items.tech-validate');
        Route::post('/items/{item}/verify', [LisWorkbenchController::class, 'verify'])->name('items.verify');
        Route::post('/items/{item}/acknowledge-critical', [LisWorkbenchController::class, 'acknowledgeCritical'])->name('items.acknowledge-critical');
    });

    Route::prefix('ris')->name('ris.')->group(function (): void {
        Route::get('/', [RisWorkbenchController::class, 'index'])->name('index');
        Route::post('/studies/{study}/report', [RisWorkbenchController::class, 'saveReport'])->name('studies.report');
        Route::post('/studies/{study}/sync-pacs', [RisWorkbenchController::class, 'syncPacs'])->name('studies.sync-pacs');
    });

    Route::prefix('pharmacy')->name('pharmacy.')->group(function (): void {
        Route::get('/', [PharmacyWorkbenchController::class, 'index'])->name('index');
        Route::post('/items/{item}/dispense', [PharmacyWorkbenchController::class, 'dispense'])->name('items.dispense');
    });

    Route::prefix('billing')->name('billing.')->group(function (): void {
        Route::get('/', [BillingDeskController::class, 'index'])->name('index');
        Route::post('/invoices', [BillingDeskController::class, 'storeInvoice'])->name('invoices.store');
        Route::post('/invoices/{invoice}/pay', [BillingDeskController::class, 'recordPayment'])->name('invoices.pay');
        Route::post('/invoices/{invoice}/void', [BillingDeskController::class, 'voidInvoice'])->name('invoices.void');
    });

    Route::prefix('rosters')->name('rosters.')->group(function (): void {
        Route::get('/', [DutyRosterController::class, 'index'])->name('index');
        Route::post('/', [DutyRosterController::class, 'store'])->name('store');
    });

    Route::get('/reports', [ExecutiveReportController::class, 'index'])->name('reports.index');

    Route::prefix('encounters')->name('encounters.')->group(function (): void {
        Route::get('/{encounter}', [EncounterWorkspaceController::class, 'show'])->name('show');
        Route::post('/{encounter}/vitals', [EncounterWorkspaceController::class, 'storeVitals'])->name('vitals.store');
        Route::post('/{encounter}/notes', [EncounterWorkspaceController::class, 'storeNote'])->name('notes.store');
        Route::put('/{encounter}/notes/{note}', [EncounterWorkspaceController::class, 'updateNote'])->name('notes.update');
        Route::post('/{encounter}/notes/{note}/sign', [EncounterWorkspaceController::class, 'signNote'])->name('notes.sign');
        Route::post('/{encounter}/notes/{note}/amend', [EncounterWorkspaceController::class, 'amendNote'])->name('notes.amend');
        Route::post('/{encounter}/diagnoses', [EncounterWorkspaceController::class, 'storeDiagnosis'])->name('diagnoses.store');
        Route::post('/{encounter}/lab-orders', [LisWorkbenchController::class, 'storeOrder'])->name('lab-orders.store');
        Route::post('/{encounter}/imaging-orders', [RisWorkbenchController::class, 'storeOrder'])->name('imaging-orders.store');
        Route::post('/{encounter}/prescriptions', [PharmacyWorkbenchController::class, 'storePrescription'])->name('prescriptions.store');
        Route::post('/{encounter}/complete', [EncounterWorkspaceController::class, 'complete'])->name('complete');
    });
});
