<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Lis;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabOrderItem;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Lis\Models\Specimen;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\LabAndImagingCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LisWorkflowAndAnalyzerContractTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $labTech;

    private User $pathologist;

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
            'name' => 'Dr. LIS Consultant',
            'username' => 'lis_doc',
            'email' => 'lisdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $opdDept->id,
            'employee_code' => 'LIS-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->labTech = User::query()->create([
            'name' => 'Lab Technologist',
            'username' => 'lis_tech',
            'email' => 'listech@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->labTech->assignRole('lab_technician');

        $this->pathologist = User::query()->create([
            'name' => 'Dr. Pathologist Verifier',
            'username' => 'lis_path',
            'email' => 'lispath@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->pathologist->assignRole('pathologist');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000401',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'LIS Cardiac Markers Patient',
            'sex' => 'male',
            'date_of_birth' => '1963-04-04',
        ]);

        $this->encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000401',
            'patient_id' => $this->patient->id,
            'department_id' => $opdDept->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_end_to_end_lis_workflow_analyzer_staging_duplicate_rejection_and_2_step_verification(): void
    {
        $tropTest = LabTestCatalog::query()->where('code', 'TROP-I')->firstOrFail();

        // 1. Consultant places STAT Troponin I lab order from encounter
        $this->actingAs($this->consultant)
            ->post(route('encounters.lab-orders.store', $this->encounter), [
                'test_ids' => [$tropTest->id],
                'priority' => 'stat',
                'clinical_notes' => 'Rule out acute NSTEMI',
            ])
            ->assertRedirect(route('encounters.show', $this->encounter));

        $order = LabOrder::query()->with(['specimens', 'items'])->firstOrFail();
        $this->assertMatchesRegularExpression('/^LAB-\d{4}-000001$/', $order->order_number);
        $specimen = $order->specimens->firstOrFail();
        $this->assertMatchesRegularExpression('/^SMP-\d{4}-000001$/', $specimen->barcode);
        $item = $order->items->firstOrFail();

        // 2. Specimen collection, rejection (hemolyzed), and automatic recollection barcode
        $this->actingAs($this->labTech)
            ->post(route('lis.specimens.collect', $specimen))
            ->assertRedirect();

        $this->actingAs($this->labTech)
            ->post(route('lis.specimens.reject', $specimen), [
                'rejection_reason' => 'Grossly hemolyzed serum sample',
            ])
            ->assertRedirect();

        $this->assertSame(Specimen::STATUS_REJECTED, $specimen->fresh()->status);
        $replacement = Specimen::query()->where('id', '!=', $specimen->id)->firstOrFail();
        $this->assertSame(Specimen::STATUS_PENDING_COLLECTION, $replacement->status);
        $this->assertSame($replacement->id, $item->fresh()->specimen_id);

        // Receive replacement specimen in lab
        $this->actingAs($this->labTech)
            ->post(route('lis.specimens.receive', $replacement))
            ->assertRedirect();
        $this->assertSame(Specimen::STATUS_RECEIVED, $replacement->fresh()->status);

        // 3. Synthetic HL7 v2 Analyzer Frame Ingestion (Stages critical Troponin I = 145.5 ng/L)
        $hl7Frame = "MSH|^~\\&|ARCH-I1000|LIS|CHG-HMIS|CHG|20261120010000||ORU^R01|MSG-9001|P|2.5.1\r"
            ."PID|1||{$this->patient->mrn}||LIS^PATIENT\r"
            ."OBR|1|{$replacement->barcode}||TROP^Troponin I\r"
            .'OBX|1|NM|TROP^Troponin I||145.5|ng/L';

        Sanctum::actingAs($this->labTech);
        $ingestRes = $this->postJson('/api/v1/lis/analyzer/ingest', [
            'device_code' => 'ARCH-I1000',
            'raw_payload' => $hl7Frame,
        ]);

        $ingestRes->assertStatus(202)
            ->assertJsonPath('status', 'staged')
            ->assertJsonPath('staged_count', 1);
        $this->assertStringContainsString('MSA|AA|MSG-9001|', (string) $ingestRes->json('ack'));

        // Duplicate analyzer frame MUST be detected and ignored idempotently
        $dupRes = $this->postJson('/api/v1/lis/analyzer/ingest', [
            'device_code' => 'ARCH-I1000',
            'raw_payload' => $hl7Frame,
        ]);
        $dupRes->assertOk()
            ->assertJsonPath('status', 'duplicate')
            ->assertJsonPath('staged_count', 0);

        // Malformed frame rejected with 422 and HL7 AE NAK
        $this->postJson('/api/v1/lis/analyzer/ingest', [
            'device_code' => 'ARCH-I1000',
            'raw_payload' => 'GARBAGE-NON-HL7-PAYLOAD',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'failed');

        // 4. CLINICAL SAFETY GATE: Analyzer result MUST NOT be auto-verified or visible on Patient Profile / FHIR!
        $item->refresh();
        $this->assertSame(LabOrderItem::STATUS_RESULT_ENTERED, $item->status);
        $this->assertSame('analyzer', $item->result_source);
        $this->assertTrue($item->is_critical);
        $this->assertSame('critical_high', $item->abnormal_flag);

        $this->actingAs($this->consultant)
            ->get(route('patients.show', [$this->patient, 'tab' => 'diagnostics']))
            ->assertOk()
            ->assertDontSee('145.5');

        Sanctum::actingAs($this->consultant);
        $this->getJson("/api/fhir/r4/DiagnosticReport/{$order->uuid}")
            ->assertStatus(409)
            ->assertJsonPath('resourceType', 'OperationOutcome');

        // 5. 2-STEP VERIFICATION GATE: Lab Tech validates technically, but CANNOT final-verify (403)
        $this->actingAs($this->labTech)
            ->post(route('lis.items.tech-validate', $item))
            ->assertRedirect();
        $this->assertSame(LabOrderItem::STATUS_TECH_VALIDATED, $item->fresh()->status);

        $this->actingAs($this->labTech)
            ->post(route('lis.items.verify', $item))
            ->assertForbidden();

        // 6. Pathologist verifies -> order & item published to Patient Profile AND FHIR DiagnosticReport
        $this->actingAs($this->pathologist)
            ->post(route('lis.items.verify', $item))
            ->assertRedirect();

        $item->refresh();
        $order->refresh();
        $this->assertSame(LabOrderItem::STATUS_VERIFIED, $item->status);
        $this->assertSame(LabOrder::STATUS_VERIFIED, $order->status);

        $this->actingAs($this->consultant)
            ->get(route('patients.show', [$this->patient, 'tab' => 'diagnostics']))
            ->assertOk()
            ->assertSee('145.5')
            ->assertSee('CRITICAL');

        Sanctum::actingAs($this->consultant);
        $this->getJson("/api/fhir/r4/DiagnosticReport/{$order->uuid}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'DiagnosticReport')
            ->assertJsonPath('status', 'final')
            ->assertJsonPath('contained.0.valueQuantity.value', 145.5)
            ->assertJsonPath('contained.0.code.coding.0.code', '89579-7');

        // 7. Critical value acknowledgement by ordering clinician
        $this->actingAs($this->consultant)
            ->post(route('lis.items.acknowledge-critical', $item))
            ->assertRedirect();

        $this->assertNotNull($item->fresh()->critical_acknowledged_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lis.critical.acknowledged',
            'patient_id' => $this->patient->id,
        ]);
    }
}
