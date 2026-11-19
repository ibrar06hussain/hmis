<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cardiology;

use App\Models\User;
use App\Modules\Cardiology\Models\CardiacProcedure;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CardiacProcedureAndFhirTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $medicalOfficer;

    private Patient $patient;

    private Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $cathDept = Department::query()->where('code', 'CATH-LAB')->firstOrFail();

        $this->consultant = User::query()->create([
            'name' => 'Dr. Interventional Cardiologist',
            'username' => 'cath_doc',
            'email' => 'cathdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $cathDept->id,
            'employee_code' => 'CATH-DOC-1',
            'designation' => 'Interventional Cardiologist',
        ]);

        $this->medicalOfficer = User::query()->create([
            'name' => 'Cath Lab MO',
            'username' => 'cath_mo',
            'email' => 'cathmo@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->medicalOfficer->assignRole('medical_officer');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000840',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Cath Lab PCI Patient',
            'sex' => 'male',
            'date_of_birth' => '1957-03-14',
        ]);

        $this->encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000840',
            'patient_id' => $this->patient->id,
            'department_id' => $cathDept->id,
            'class' => 'CARD_PROC',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_cath_lab_pci_requires_who_checklist_and_consultant_signoff_and_maps_to_fhir_procedure(): void
    {
        $this->actingAs($this->medicalOfficer)->post('/cardiology/procedures', [
            'encounter_id' => $this->encounter->id,
            'category' => 'cath_lab_pci',
            'procedure_name' => 'Primary PCI to Proximal LAD',
            'theatre_or_lab' => 'Cath Lab 1',
            'lead_operator_staff_id' => $this->consultant->staffProfile->id,
            'access_site' => 'radial_right',
        ])->assertRedirect(route('cardiology.procedures.index'));

        $proc = CardiacProcedure::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^PROC-\d{4}-000001$/', $proc->procedure_number);

        // MO cannot sign operative record (403)
        $this->actingAs($this->medicalOfficer)->post(route('cardiology.procedures.complete', $proc), [
            'who_checklist_completed' => true,
            'operative_findings' => 'Successful DES deployment in proximal LAD with TIMI 3 flow.',
        ])->assertForbidden();

        // Completing WITHOUT WHO checklist confirmation MUST fail
        $this->actingAs($this->consultant)->post(route('cardiology.procedures.complete', $proc), [
            'who_checklist_completed' => false,
            'operative_findings' => 'Successful DES deployment in proximal LAD with TIMI 3 flow.',
        ])->assertSessionHasErrors('who_checklist_completed');

        // Completing WITH WHO checklist succeeds & locks record
        $this->actingAs($this->consultant)->post(route('cardiology.procedures.complete', $proc), [
            'who_checklist_completed' => true,
            'access_site' => 'radial_right',
            'contrast_volume_ml' => 110,
            'fluoroscopy_minutes' => 12.5,
            'stent_summary' => 'Proximal LAD — DES 3.0x24mm — Lot#ST-2026-88',
            'operative_findings' => 'Single-vessel critical 95% proximal LAD lesion; successful primary PCI with TIMI 3 flow.',
            'post_op_orders' => 'CCU telemetry x24h, Aspirin + Ticagrelor, hydration protocol.',
        ])->assertRedirect(route('cardiology.procedures.index'));

        $proc->refresh();
        $this->assertSame(CardiacProcedure::STATUS_COMPLETED, $proc->status);
        $this->assertTrue($proc->who_checklist_completed);
        $this->assertSame($this->consultant->id, $proc->signed_by_user_id);
        $this->assertCount(1, $proc->stents_implanted);

        // FHIR R4 Procedure endpoint
        Sanctum::actingAs($this->consultant);
        $this->getJson("/api/fhir/r4/Procedure/{$proc->uuid}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'Procedure')
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('subject.reference', 'Patient/'.$this->patient->uuid);
    }
}
