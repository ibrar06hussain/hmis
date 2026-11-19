<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Patient;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Organization\Models\Department;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\PatientRegistrationService;
use App\Modules\Patient\Services\PatientSearchService;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DuplicateDetectionAndMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $this->receptionist = User::query()->create([
            'name' => 'Receptionist',
            'username' => 'rec1',
            'email' => 'rec1@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->admin = User::query()->create([
            'name' => 'Hospital Admin',
            'username' => 'adm1',
            'email' => 'adm1@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('administrator');
    }

    public function test_exact_cnic_duplicate_is_blocked_for_standard_receptionist(): void
    {
        $service = app(PatientRegistrationService::class);
        $service->register([
            'registration_type' => 'standard',
            'full_name' => 'Ali Hassan Synthetic',
            'father_or_spouse_name' => 'Ghulam Ali',
            'date_of_birth' => '1980-01-01',
            'sex' => 'male',
            'cnic' => '71501-8888888-1',
            'phone_primary' => '0345-1111111',
        ], $this->receptionist);

        $response = $this->actingAs($this->receptionist)->post('/patients', [
            'registration_type' => 'standard',
            'full_name' => 'Ali Hassan Duplicate',
            'father_or_spouse_name' => 'Ghulam Ali',
            'date_of_birth' => '1980-01-01',
            'sex' => 'male',
            'cnic' => '7150188888881',
            'phone_primary' => '0345-2222222',
            'confirm_distinct' => true, // receptionist lacks override permission
        ]);

        $response->assertSessionHas('duplicate_candidates');
        $this->assertSame(1, Patient::query()->count());
    }

    public function test_authorized_merge_preserves_all_clinical_records_and_resolves_old_mrn(): void
    {
        $service = app(PatientRegistrationService::class);
        $erPatient = $service->register([
            'registration_type' => 'emergency',
            'is_unknown_emergency' => true,
            'full_name' => 'Unknown Male ER',
            'estimated_age_years' => 55,
            'sex' => 'male',
        ], $this->receptionist);

        $canonical = $service->register([
            'registration_type' => 'standard',
            'full_name' => 'Javed Akhtar Synthetic',
            'father_or_spouse_name' => 'Noor Muhammad',
            'date_of_birth' => '1971-04-02',
            'sex' => 'male',
            'cnic' => '71501-3333333-5',
            'phone_primary' => '0345-3334444',
        ], $this->receptionist);

        $dept = Department::query()->where('code', 'CARD-ER')->firstOrFail();

        Encounter::query()->create([
            'encounter_number' => 'ENC-TEST-001',
            'patient_id' => $erPatient->id,
            'department_id' => $dept->id,
            'class' => 'EMER',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);

        PatientAllergy::query()->create([
            'patient_id' => $erPatient->id,
            'substance' => 'Aspirin',
            'category' => 'medication',
            'severity' => 'severe',
            'clinical_status' => 'active',
            'recorded_at' => Carbon::now('UTC'),
        ]);

        ImagingStudy::query()->create([
            'patient_id' => $erPatient->id,
            'accession_number' => 'ACC-TEST-001',
            'modality' => 'ECG',
            'procedure_name' => '12-Lead ECG',
            'status' => 'reported',
            'study_date' => Carbon::now('UTC'),
        ]);

        // Receptionist must NOT be allowed to merge
        $this->actingAs($this->receptionist)->post('/patients/merge', [
            'source_patient_id' => $erPatient->id,
            'target_patient_id' => $canonical->id,
            'reason' => 'Unauthorized merge attempt by front desk',
        ])->assertForbidden();

        // Administrator executes audited merge
        $this->actingAs($this->admin)->post('/patients/merge', [
            'source_patient_id' => $erPatient->id,
            'target_patient_id' => $canonical->id,
            'reason' => 'Confirmed emergency patient identity via family NADRA CNIC.',
        ])->assertRedirect(route('patients.show', $canonical));

        $erPatient->refresh();
        $canonical->refresh();

        $this->assertSame(Patient::STATUS_MERGED, $erPatient->status);
        $this->assertSame($canonical->id, $erPatient->merged_into_patient_id);
        $this->assertSame(1, $canonical->encounters()->count());
        $this->assertSame(1, $canonical->allergies()->count());
        $this->assertSame(1, $canonical->imagingStudies()->count());

        // Old MRN resolves seamlessly to surviving canonical patient
        $resolved = app(PatientSearchService::class)->resolveByMrn($erPatient->mrn);
        $this->assertNotNull($resolved);
        $this->assertSame($canonical->id, $resolved->id);

        // Visiting old patient URL redirects to canonical profile
        $this->actingAs($this->admin)
            ->get(route('patients.show', $erPatient))
            ->assertRedirect(route('patients.show', $canonical));

        $this->assertDatabaseHas('patient_merges', [
            'source_patient_id' => $erPatient->id,
            'target_patient_id' => $canonical->id,
            'merged_by_user_id' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'patient.merged',
            'patient_id' => $canonical->id,
        ]);
    }
}
