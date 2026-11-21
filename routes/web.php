<?php

declare(strict_types=1);

use App\Modules\AiAssistant\Http\Controllers\AiGovernanceController;
use App\Modules\Audit\Http\Controllers\AuditOversightController;
use App\Modules\Billing\Http\Controllers\BillingDeskController;
use App\Modules\Cardiology\Http\Controllers\CardiacProcedureController;
use App\Modules\Core\Http\Controllers\LocaleController;
use App\Modules\Emergency\Http\Controllers\EmergencyController;
use App\Modules\Emr\Http\Controllers\ClinicianPreferenceController;
use App\Modules\Emr\Http\Controllers\CoSignTrayController;
use App\Modules\Emr\Http\Controllers\EncounterWorkspaceController;
use App\Modules\Emr\Http\Controllers\OrderSetController;
use App\Modules\Emr\Http\Controllers\RapidConsultController;
use App\Modules\Hr\Http\Controllers\DutyRosterController;
use App\Modules\Iam\Http\Controllers\AuthController;
use App\Modules\Iam\Http\Controllers\DashboardController;
use App\Modules\Iam\Http\Controllers\TwoFactorController;
use App\Modules\Icu\Http\Controllers\IcuTelemetryController;
use App\Modules\Ipd\Http\Controllers\IpdController;
use App\Modules\Lis\Http\Controllers\LisWorkbenchController;
use App\Modules\Nursing\Http\Controllers\NursingStationController;
use App\Modules\Opd\Http\Controllers\OpdQueueController;
use App\Modules\Opd\Http\Controllers\PreConsultController;
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

Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])
        ->middleware('throttle:10,1')
        ->name('two-factor.verify');

    Route::prefix('security/two-factor')->name('security.two-factor.')->group(function (): void {
        Route::get('/', [TwoFactorController::class, 'showSetup'])->name('show');
        Route::post('/confirm', [TwoFactorController::class, 'confirmSetup'])->name('confirm');
        Route::delete('/', [TwoFactorController::class, 'disable'])->name('disable');
    });

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
        Route::get('/pre-consult', [PreConsultController::class, 'index'])->name('pre-consult.index');
        Route::post('/pre-consult/{encounter}', [PreConsultController::class, 'store'])->name('pre-consult.store');
        Route::post('/appointments', [OpdQueueController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}/slip', [OpdQueueController::class, 'visitSlip'])->name('appointments.slip');
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
        Route::get('/admissions/{admission}/discharge-summary', [IpdController::class, 'dischargeSummary'])->name('admissions.discharge-summary');
        Route::post('/admissions/{admission}/transfer', [IpdController::class, 'transferBed'])->name('admissions.transfer');
        Route::post('/admissions/{admission}/discharge', [IpdController::class, 'discharge'])->name('admissions.discharge');
        Route::post('/beds/{bed}/mark-available', [IpdController::class, 'markBedAvailable'])->name('beds.mark-available');
    });

    Route::prefix('nursing')->name('nursing.')->group(function (): void {
        Route::get('/', [NursingStationController::class, 'index'])->name('index');
        Route::post('/mar', [NursingStationController::class, 'storeMar'])->name('mar.store');
        Route::post('/notes', [NursingStationController::class, 'storeNote'])->name('notes.store');
    });

    Route::prefix('cardiology/procedures')->name('cardiology.procedures.')->group(function (): void {
        Route::get('/', [CardiacProcedureController::class, 'index'])->name('index');
        Route::post('/', [CardiacProcedureController::class, 'store'])->name('store');
        Route::post('/{procedure}/complete', [CardiacProcedureController::class, 'complete'])->name('complete');
    });

    Route::prefix('icu')->name('icu.')->group(function (): void {
        Route::get('/', [IcuTelemetryController::class, 'index'])->name('index');
        Route::post('/observations/{observation}/verify', [IcuTelemetryController::class, 'verify'])->name('observations.verify');
        Route::post('/observations/{observation}/reject', [IcuTelemetryController::class, 'rejectArtifact'])->name('observations.reject');
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
        Route::get('/invoices/{invoice}/receipt', [BillingDeskController::class, 'receipt'])->name('invoices.receipt');
        Route::post('/invoices/{invoice}/pay', [BillingDeskController::class, 'recordPayment'])->name('invoices.pay');
        Route::post('/invoices/{invoice}/void', [BillingDeskController::class, 'voidInvoice'])->name('invoices.void');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/audit-logs', [AuditOversightController::class, 'index'])->name('audit-logs.index');
        Route::post('/break-glass/{breakGlassLog}/revoke', [AuditOversightController::class, 'revokeBreakGlass'])->name('break-glass.revoke');
    });

    Route::prefix('rosters')->name('rosters.')->group(function (): void {
        Route::get('/', [DutyRosterController::class, 'index'])->name('index');
        Route::post('/', [DutyRosterController::class, 'store'])->name('store');
    });

    Route::get('/reports', [ExecutiveReportController::class, 'index'])->name('reports.index');

    Route::prefix('ai-governance')->name('ai.')->group(function (): void {
        Route::get('/', [AiGovernanceController::class, 'index'])->name('index');
        Route::post('/draft', [AiGovernanceController::class, 'invokeDraft'])->name('draft');
    });

    Route::prefix('emr')->name('emr.')->group(function (): void {
        Route::get('/cosign', [CoSignTrayController::class, 'index'])->name('cosign.index');
        Route::post('/cosign/{note}/approve', [CoSignTrayController::class, 'approve'])->name('cosign.approve');
        Route::get('/favorites', [OrderSetController::class, 'index'])->name('favorites.index');
        Route::post('/favorites', [OrderSetController::class, 'store'])->name('favorites.store');
        Route::delete('/favorites/{orderSet}', [OrderSetController::class, 'destroy'])->name('favorites.destroy');

        Route::prefix('preferences')->name('preferences.')->group(function (): void {
            Route::post('/chips', [ClinicianPreferenceController::class, 'storeChip'])->name('chips.store');
            Route::delete('/chips', [ClinicianPreferenceController::class, 'destroyChip'])->name('chips.destroy');
            Route::post('/medications', [ClinicianPreferenceController::class, 'storeMedication'])->name('medications.store');
            Route::post('/medications/pin', [ClinicianPreferenceController::class, 'pinMedication'])->name('medications.pin');
            Route::delete('/medications/{medication}/pin', [ClinicianPreferenceController::class, 'unpinMedication'])->name('medications.unpin');
        });
    });

    Route::prefix('encounters')->name('encounters.')->group(function (): void {
        Route::get('/{encounter}', [EncounterWorkspaceController::class, 'show'])->name('show');
        Route::get('/{encounter}/rapid', [RapidConsultController::class, 'show'])->name('rapid.show');
        Route::post('/{encounter}/rapid', [RapidConsultController::class, 'store'])->name('rapid.store');
        Route::post('/{encounter}/rapid/dictate-draft', [RapidConsultController::class, 'dictateDraft'])->name('rapid.dictate-draft');
        Route::get('/{encounter}/prescription-print', [RapidConsultController::class, 'printPrescription'])->name('prescription.print');
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
