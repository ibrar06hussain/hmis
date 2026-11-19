<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pacs;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Pacs\Models\PacsViewerSession;
use App\Modules\Pacs\Services\NullPacsGateway;
use App\Modules\Pacs\Services\OrthancPacsGateway;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrthancPacsGatewayContractTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $radiologist;

    private Patient $patient;

    private ImagingStudy $study;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $opdDept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();

        $this->consultant = User::query()->create([
            'name' => 'Dr. PACS Consultant',
            'username' => 'pacs_doc',
            'email' => 'pacsdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $opdDept->id,
            'employee_code' => 'PACS-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->radiologist = User::query()->create([
            'name' => 'Dr. PACS Radiologist',
            'username' => 'pacs_rad',
            'email' => 'pacsrad@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->radiologist->assignRole('radiologist');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000750',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'PACS Integration Patient',
            'sex' => 'male',
            'date_of_birth' => '1961-01-01',
        ]);

        $encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000750',
            'patient_id' => $this->patient->id,
            'department_id' => $opdDept->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);

        $this->study = ImagingStudy::query()->create([
            'patient_id' => $this->patient->id,
            'encounter_id' => $encounter->id,
            'accession_number' => 'ACC-2026-007777',
            'modality' => 'XA',
            'procedure_name' => 'Coronary Angiography',
            'status' => ImagingStudy::STATUS_ORDERED,
            'study_date' => Carbon::now('UTC'),
        ]);
    }

    public function test_disabled_pacs_uses_null_gateway_and_enabled_pacs_syncs_orthanc_and_launches_audited_ohif(): void
    {
        // Default disabled mode binds NullPacsGateway
        config()->set('hmis.pacs.enabled', false);
        $this->assertInstanceOf(NullPacsGateway::class, app(PacsGatewayInterface::class));

        // Enable Phase 6 Orthanc + OHIF gateway via config (zero Patient Profile code changes!)
        config()->set('hmis.pacs.enabled', true);
        config()->set('hmis.pacs.orthanc_url', 'http://orthanc.chg.local:8042');
        config()->set('hmis.pacs.ohif_viewer_url', 'https://ohif.chg.local');

        $gateway = app(PacsGatewayInterface::class);
        $this->assertInstanceOf(OrthancPacsGateway::class, $gateway);
        $this->assertTrue($gateway->isEnabled());

        // Mock Orthanc DICOMweb QIDO-RS study lookup by AccessionNumber
        Http::fake([
            'http://orthanc.chg.local:8042/dicom-web/studies*' => Http::response([[
                '0020000D' => ['vr' => 'UI', 'Value' => ['1.2.826.0.1.3680043.10.9999.7777001']],
            ]], 200),
        ]);

        // Radiologist triggers DICOMweb sync from RIS
        $this->actingAs($this->radiologist)
            ->post(route('ris.studies.sync-pacs', $this->study))
            ->assertRedirect();

        $this->study->refresh();
        $this->assertSame('1.2.826.0.1.3680043.10.9999.7777001', $this->study->study_instance_uid);
        $this->assertSame('orthanc_main', $this->study->pacs_server_key);

        // Unified Patient Profile automatically renders secure OHIF launch button & records audited viewer session!
        $this->actingAs($this->consultant)
            ->get(route('patients.show', [$this->patient, 'tab' => 'diagnostics']))
            ->assertOk()
            ->assertSee('Open in OHIF')
            ->assertSee('https://ohif.chg.local/viewer?StudyInstanceUIDs=1.2.826.0.1.3680043.10.9999.7777001');

        $this->assertSame(1, PacsViewerSession::query()->where('imaging_study_id', $this->study->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pacs.viewer_session_issued',
            'patient_id' => $this->patient->id,
        ]);
    }
}
