<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ris;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Patient\Models\Patient;
use App\Modules\Ris\Models\ImagingCatalog;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\LabAndImagingCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RisAndContrastSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $radiologist;

    private User $receptionist;

    private Patient $patient;

    private Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);
        $this->seed(LabAndImagingCatalogSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $opdDept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();

        $this->consultant = User::query()->create([
            'name' => 'Dr. RIS Consultant',
            'username' => 'ris_doc',
            'email' => 'risdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $opdDept->id,
            'employee_code' => 'RIS-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->radiologist = User::query()->create([
            'name' => 'Dr. Radiologist',
            'username' => 'ris_rad',
            'email' => 'risrad@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->radiologist->assignRole('radiologist');

        $this->receptionist = User::query()->create([
            'name' => 'Receptionist',
            'username' => 'ris_rec',
            'email' => 'risrec@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000801',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Contrast Allergy RIS Patient',
            'sex' => 'female',
            'date_of_birth' => '1966-09-09',
        ]);

        PatientAllergy::query()->create([
            'patient_id' => $this->patient->id,
            'substance' => 'IV Iodinated Contrast',
            'category' => 'contrast_dye',
            'severity' => 'severe',
            'clinical_status' => 'active',
            'recorded_at' => Carbon::now('UTC'),
        ]);

        $this->encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000801',
            'patient_id' => $this->patient->id,
            'department_id' => $opdDept->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_contrast_imaging_order_triggers_allergy_warning_and_radiologist_verifies_pacs_study(): void
    {
        $ctCoro = ImagingCatalog::query()->where('code', 'CT-CORO')->firstOrFail();

        // Receptionist cannot access RIS or order imaging
        $this->actingAs($this->receptionist)->get('/ris')->assertForbidden();
        $this->actingAs($this->receptionist)
            ->post(route('encounters.imaging-orders.store', $this->encounter), [
                'imaging_catalog_id' => $ctCoro->id,
                'priority' => 'urgent',
            ])
            ->assertForbidden();

        // Consultant orders CT Coronary Angiography -> triggers contrast allergy warning + Accession #
        $this->actingAs($this->consultant)
            ->post(route('encounters.imaging-orders.store', $this->encounter), [
                'imaging_catalog_id' => $ctCoro->id,
                'priority' => 'urgent',
                'clinical_indication' => 'Suspected LAD stenosis; pre-medicate for contrast allergy',
            ])
            ->assertRedirect(route('encounters.show', $this->encounter))
            ->assertSessionHas('warning');

        $study = ImagingStudy::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^ACC-\d{4}-000001$/', $study->accession_number);
        $this->assertSame('CT', $study->modality);

        // Radiologist enters report with DICOM StudyInstanceUID and verifies
        $this->actingAs($this->radiologist)
            ->post(route('ris.studies.report', $study), [
                'study_instance_uid' => '1.2.826.0.1.3680043.10.9999.20268888',
                'report_summary' => '70% calcified stenosis in proximal LAD; RCA and LCx patent.',
                'verify_now' => true,
            ])
            ->assertRedirect();

        $study->refresh();
        $this->assertSame(ImagingStudy::STATUS_VERIFIED, $study->status);
        $this->assertSame('1.2.826.0.1.3680043.10.9999.20268888', $study->study_instance_uid);
        $this->assertSame($this->radiologist->id, $study->verified_by_user_id);

        // Verified radiology report cannot be overwritten directly
        $this->actingAs($this->radiologist)
            ->post(route('ris.studies.report', $study), [
                'report_summary' => 'Attempted direct overwrite of verified radiology report',
                'verify_now' => true,
            ])
            ->assertSessionHasErrors('report');
    }
}
