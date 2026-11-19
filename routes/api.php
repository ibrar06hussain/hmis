<?php

declare(strict_types=1);

use App\Modules\Appointments\Http\Controllers\Api\AppointmentApiController;
use App\Modules\Devices\Http\Controllers\Api\AnalyzerIngestApiController;
use App\Modules\Fhir\Http\Controllers\FhirConditionController;
use App\Modules\Fhir\Http\Controllers\FhirDiagnosticReportController;
use App\Modules\Fhir\Http\Controllers\FhirEncounterController;
use App\Modules\Fhir\Http\Controllers\FhirMedicationRequestController;
use App\Modules\Fhir\Http\Controllers\FhirPatientController;
use App\Modules\Patient\Http\Controllers\Api\PatientApiController;
use App\Modules\Terminology\Http\Controllers\Api\TerminologyApiController;
use Illuminate\Support\Facades\Route;

// Versioned internal/external REST API
Route::prefix('v1')->middleware('auth:sanctum')->name('api.v1.')->group(function (): void {
    Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
    Route::post('/patients', [PatientApiController::class, 'store'])->name('patients.store');
    Route::post('/patients/check-duplicates', [PatientApiController::class, 'checkDuplicates'])->name('patients.check-duplicates');
    Route::get('/patients/{patient}', [PatientApiController::class, 'show'])->name('patients.show');

    Route::get('/appointments', [AppointmentApiController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AppointmentApiController::class, 'store'])->name('appointments.store');
    Route::post('/appointments/{appointment}/check-in', [AppointmentApiController::class, 'checkIn'])->name('appointments.check-in');

    Route::get('/terminology/search', [TerminologyApiController::class, 'search'])->name('terminology.search');

    // LAN Analyzer Gateway Staging Endpoint (never auto-publishes)
    Route::post('/lis/analyzer/ingest', [AnalyzerIngestApiController::class, 'ingest'])->name('lis.analyzer.ingest');
});

// Dedicated HL7 FHIR R4 interoperability surface
Route::prefix('fhir/r4')->middleware('auth:sanctum')->name('api.fhir.r4.')->group(function (): void {
    Route::get('/Patient/{uuid}', [FhirPatientController::class, 'show'])->name('patient.show');
    Route::get('/Encounter/{uuid}', [FhirEncounterController::class, 'show'])->name('encounter.show');
    Route::get('/Condition/{uuid}', [FhirConditionController::class, 'show'])->name('condition.show');
    Route::get('/DiagnosticReport/{uuid}', [FhirDiagnosticReportController::class, 'show'])->name('diagnostic-report.show');
    Route::get('/MedicationRequest/{uuid}', [FhirMedicationRequestController::class, 'show'])->name('medication-request.show');
});
