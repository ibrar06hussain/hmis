<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Terminology;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\EncounterDiagnosis;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use App\Modules\Terminology\Models\TerminologyCode;
use App\Modules\Terminology\Services\TerminologySearchService;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\TerminologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TerminologyAndDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $ccuNurse;

    private Encounter $encounter;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);
        $this->seed(TerminologySeeder::class);

        $facility = Facility::query()->firstOrFail();
        $opdDept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();
        $ccuDept = Department::query()->where('code', 'CCU')->firstOrFail();

        $this->consultant = User::query()->create([
            'name' => 'Dr. Terminology Consultant',
            'username' => 'term_doc',
            'email' => 'termdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $opdDept->id,
            'employee_code' => 'TERM-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->ccuNurse = User::query()->create([
            'name' => 'CCU Nurse',
            'username' => 'term_nurse',
            'email' => 'termnurse@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->ccuNurse->assignRole('nurse');
        StaffProfile::query()->create([
            'user_id' => $this->ccuNurse->id,
            'facility_id' => $facility->id,
            'department_id' => $ccuDept->id,
            'employee_code' => 'TERM-NUR-1',
            'designation' => 'Nurse',
        ]);

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000901',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Terminology Test Patient',
            'sex' => 'male',
            'date_of_birth' => '1959-07-07',
        ]);

        $this->encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000901',
            'patient_id' => $this->patient->id,
            'department_id' => $opdDept->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_offline_terminology_search_supports_icd10_icd11_and_clinical_aliases(): void
    {
        $search = app(TerminologySearchService::class);

        $stemiResults = $search->search('STEMI');
        $this->assertTrue($stemiResults->contains(fn (TerminologyCode $c): bool => $c->code === 'I21.0' && $c->coding_system === 'ICD-10'));
        $this->assertTrue($stemiResults->contains(fn (TerminologyCode $c): bool => $c->code === 'BA41' && $c->coding_system === 'ICD-11'));

        $icd11Only = $search->search('CHF', 'ICD-11');
        $this->assertCount(1, $icd11Only);
        $this->assertSame('BD10', $icd11Only->first()->code);

        Sanctum::actingAs($this->consultant);
        $this->getJson('/api/v1/terminology/search?q=AFib&system=ICD-10')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'I48.9');
    }

    public function test_encounter_diagnosis_preserves_historical_version_and_maps_to_fhir_condition(): void
    {
        $icd10 = TerminologyCode::query()->where('coding_system', 'ICD-10')->where('code', 'I21.0')->firstOrFail();

        $this->actingAs($this->consultant)
            ->post(route('encounters.diagnoses.store', $this->encounter), [
                'terminology_code_id' => $icd10->id,
                'diagnosis_rank' => 'primary',
                'clinical_status' => 'confirmed',
                'clinical_context' => 'Anterior wall STEMI, Killip class I',
            ])
            ->assertRedirect(route('encounters.show', $this->encounter));

        $diagnosis = EncounterDiagnosis::query()->firstOrFail();
        $this->assertSame('ICD-10', $diagnosis->coding_system);
        $this->assertSame('2019', $diagnosis->terminology_version);
        $this->assertSame('I21.0', $diagnosis->code);
        $originalDisplay = $diagnosis->display_name;

        // Updating terminology catalogue later MUST NOT mutate the historical recorded diagnosis
        $icd10->display_name = 'Updated Catalogue Display Name (Future Revision)';
        $icd10->save();

        $diagnosis->refresh();
        $this->assertSame($originalDisplay, $diagnosis->display_name);
        $this->assertSame('2019', $diagnosis->terminology_version);

        // FHIR R4 Condition endpoint returns valid mapped Condition
        Sanctum::actingAs($this->consultant);
        $this->getJson("/api/fhir/r4/Condition/{$diagnosis->uuid}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'Condition')
            ->assertJsonPath('code.coding.0.code', 'I21.0')
            ->assertJsonPath('code.coding.0.version', '2019')
            ->assertJsonPath('subject.reference', 'Patient/'.$this->patient->uuid);

        // Out-of-department nurse receives FHIR 403 OperationOutcome
        Sanctum::actingAs($this->ccuNurse);
        $this->getJson("/api/fhir/r4/Condition/{$diagnosis->uuid}")
            ->assertForbidden()
            ->assertJsonPath('resourceType', 'OperationOutcome');
    }
}
