<?php

declare(strict_types=1);

use App\Modules\Fhir\Http\Controllers\FhirPatientController;
use App\Modules\Patient\Http\Controllers\Api\PatientApiController;
use Illuminate\Support\Facades\Route;

// Versioned internal/external REST API
Route::prefix('v1')->middleware('auth:sanctum')->name('api.v1.')->group(function (): void {
    Route::get('/patients', [PatientApiController::class, 'index'])->name('patients.index');
    Route::post('/patients', [PatientApiController::class, 'store'])->name('patients.store');
    Route::post('/patients/check-duplicates', [PatientApiController::class, 'checkDuplicates'])->name('patients.check-duplicates');
    Route::get('/patients/{patient}', [PatientApiController::class, 'show'])->name('patients.show');
});

// Dedicated HL7 FHIR R4 interoperability surface (baseline Patient read)
Route::prefix('fhir/r4')->middleware('auth:sanctum')->name('api.fhir.r4.')->group(function (): void {
    Route::get('/Patient/{uuid}', [FhirPatientController::class, 'show'])->name('patient.show');
});
