<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reporting;

use App\Models\User;
use App\Modules\Billing\Models\ServiceTariff;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Hr\Models\DutyRoster;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Ipd\Services\IpdAdmissionService;
use App\Modules\Organization\Models\Bed;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use App\Modules\Reporting\Services\HospitalReportingService;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\PharmacyAndBillingCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DutyRosterAndReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $consultant;

    private StaffProfile $consultantProfile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);
        $this->seed(PharmacyAndBillingCatalogSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $opdDept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();

        $this->director = User::query()->create([
            'name' => 'Hospital Director',
            'username' => 'hosp_dir',
            'email' => 'director@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->director->assignRole('hospital_director');

        $this->consultant = User::query()->create([
            'name' => 'Dr. Rostered Cardiologist',
            'username' => 'ros_doc',
            'email' => 'rosdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        $this->consultantProfile = StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $opdDept->id,
            'employee_code' => 'ROS-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);
    }

    public function test_duty_roster_prevents_double_shift_and_enforces_hr_permissions(): void
    {
        $opdDept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();
        $ccuDept = Department::query()->where('code', 'CCU')->firstOrFail();

        // Consultant can view rosters, but cannot assign shifts (403)
        $this->actingAs($this->consultant)->get('/rosters')->assertOk();
        $this->actingAs($this->consultant)->post('/rosters', [
            'staff_profile_id' => $this->consultantProfile->id,
            'department_id' => $opdDept->id,
            'duty_date' => '2026-11-21',
            'shift' => 'morning',
        ])->assertForbidden();

        // Hospital Director assigns morning shift cleanly
        $this->actingAs($this->director)->post('/rosters', [
            'staff_profile_id' => $this->consultantProfile->id,
            'department_id' => $opdDept->id,
            'duty_date' => '2026-11-21',
            'shift' => 'morning',
            'notes' => 'Cardiology OPD Lead',
        ])->assertRedirect();

        $this->assertSame(1, DutyRoster::query()->count());

        // Double-booking same staff on same date & shift (even in a different dept) MUST fail
        $this->actingAs($this->director)->post('/rosters', [
            'staff_profile_id' => $this->consultantProfile->id,
            'department_id' => $ccuDept->id,
            'duty_date' => '2026-11-21',
            'shift' => 'morning',
        ])->assertSessionHasErrors('shift');
        $this->assertSame(1, DutyRoster::query()->count());
    }

    public function test_executive_reporting_aggregates_occupancy_and_billing_and_blocks_unauthorized_roles(): void
    {
        // Consultant cannot access executive report dashboard (403)
        $this->actingAs($this->consultant)->get('/reports')->assertForbidden();

        // Admit 1 patient & issue 1 invoice with exemption to verify live analytics
        $patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000710',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Analytics Patient',
            'sex' => 'male',
            'date_of_birth' => '1965-05-05',
        ]);

        $bed = Bed::query()->where('status', 'available')->firstOrFail();
        app(IpdAdmissionService::class)->admit([
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'Acute Heart Failure',
        ], $this->consultant);

        $tariff = ServiceTariff::query()->where('code', 'BED-CCU')->firstOrFail(); // PKR 3000
        $billing = app(BillingService::class);
        $inv = $billing->createInvoice($patient, [['tariff_id' => $tariff->id, 'quantity' => 2]], 'sehat_card', 4000.0, 'Sehat Card coverage', $this->director);
        $billing->recordPayment($inv, 1500.0, 'cash', 'Copay', $this->director);

        $summary = app(HospitalReportingService::class)->executiveSummary();
        $this->assertSame(1, $summary['beds']['occupied']);
        $this->assertGreaterThan(0.0, $summary['beds']['occupancy_percent']);
        $this->assertEquals(6000.0, $summary['financial']['total_billed_pkr']);
        $this->assertEquals(4000.0, $summary['financial']['total_exemptions_pkr']);
        $this->assertEquals(1500.0, $summary['financial']['total_collected_pkr']);
        $this->assertEquals(500.0, $summary['financial']['total_outstanding_pkr']);

        $this->actingAs($this->director)
            ->get('/reports')
            ->assertOk()
            ->assertSee('Executive Operational &amp; Clinical Governance Report', false)
            ->assertSee('6,000.00');
    }
}
