<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Fhir;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FhirEncounterApiTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    private User $opdConsultant;

    private User $ccuNurse;

    private Department $cardOpd;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $this->cardOpd = Department::query()->where('code', 'CARD-OPD')->firstOrFail();
        $ccuDept = Department::query()->where('code', 'CCU')->firstOrFail();

        $this->receptionist = User::query()->create([
            'name' => 'API Rec',
            'username' => 'fhir_rec',
            'email' => 'fhirrec@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->opdConsultant = User::query()->create([
            'name' => 'API OPD Consultant',
            'username' => 'fhir_doc',
            'email' => 'fhirdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->opdConsultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->opdConsultant->id,
            'facility_id' => $facility->id,
            'department_id' => $this->cardOpd->id,
            'employee_code' => 'FHIR-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->ccuNurse = User::query()->create([
            'name' => 'API CCU Nurse',
            'username' => 'fhir_nurse',
            'email' => 'fhirnurse@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->ccuNurse->assignRole('nurse');
        StaffProfile::query()->create([
            'user_id' => $this->ccuNurse->id,
            'facility_id' => $facility->id,
            'department_id' => $ccuDept->id,
            'employee_code' => 'FHIR-NUR-1',
            'designation' => 'Charge Nurse',
        ]);

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000888',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'FHIR Encounter Patient',
            'sex' => 'female',
            'date_of_birth' => '1978-11-11',
        ]);
    }

    public function test_v1_appointments_booking_checkin_and_fhir_r4_encounter_read(): void
    {
        Sanctum::actingAs($this->receptionist);

        $bookRes = $this->postJson('/api/v1/appointments', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->cardOpd->id,
            'clinician_staff_id' => $this->opdConsultant->staffProfile->id,
            'appointment_date' => '2026-11-20',
            'visit_type' => 'new',
            'chief_complaint' => 'Routine hypertension review',
        ]);

        $bookRes->assertCreated()
            ->assertJsonPath('data.token_number', 1)
            ->assertJsonPath('data.status', 'booked');

        $appointmentId = $bookRes->json('data.id');

        $checkInRes = $this->postJson("/api/v1/appointments/{$appointmentId}/check-in");
        $checkInRes->assertOk()
            ->assertJsonPath('data.status', 'checked_in');

        $encounterUuid = $checkInRes->json('data.encounter_uuid');
        $this->assertNotEmpty($encounterUuid);

        // Authorized OPD consultant reads FHIR R4 Encounter
        Sanctum::actingAs($this->opdConsultant);
        $this->getJson("/api/fhir/r4/Encounter/{$encounterUuid}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'Encounter')
            ->assertJsonPath('id', $encounterUuid)
            ->assertJsonPath('status', 'arrived')
            ->assertJsonPath('class.code', 'AMB')
            ->assertJsonPath('subject.reference', 'Patient/'.$this->patient->uuid);

        // CCU nurse (different department, no break-glass) receives FHIR 403 OperationOutcome
        Sanctum::actingAs($this->ccuNurse);
        $this->getJson("/api/fhir/r4/Encounter/{$encounterUuid}")
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'OperationOutcome');

        // Unknown encounter returns FHIR 404 OperationOutcome
        $this->getJson('/api/fhir/r4/Encounter/non-existent-encounter')
            ->assertNotFound()
            ->assertJsonPath('resourceType', 'OperationOutcome');
    }
}
