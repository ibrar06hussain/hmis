<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ipd;

use App\Models\User;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Ipd\Models\BedAllocation;
use App\Modules\Ipd\Models\IpdAdmission;
use App\Modules\Organization\Models\Bed;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IpdAdmissionAndBedConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $medicalOfficer;

    private User $nurse;

    private User $receptionist;

    private Patient $patient1;

    private Patient $patient2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $ccuDept = Department::query()->where('code', 'CCU')->firstOrFail();

        $this->consultant = User::query()->create([
            'name' => 'Dr. CCU Consultant',
            'username' => 'ipd_doc',
            'email' => 'ipddoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $ccuDept->id,
            'employee_code' => 'IPD-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->medicalOfficer = User::query()->create([
            'name' => 'Dr. Ward MO',
            'username' => 'ipd_mo',
            'email' => 'ipdmo@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->medicalOfficer->assignRole('medical_officer');
        StaffProfile::query()->create([
            'user_id' => $this->medicalOfficer->id,
            'facility_id' => $facility->id,
            'department_id' => $ccuDept->id,
            'employee_code' => 'IPD-MO-1',
            'designation' => 'Medical Officer',
        ]);

        $this->nurse = User::query()->create([
            'name' => 'CCU Charge Nurse',
            'username' => 'ipd_nurse',
            'email' => 'ipdnurse@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->nurse->assignRole('nurse');

        $this->receptionist = User::query()->create([
            'name' => 'Receptionist',
            'username' => 'ipd_rec',
            'email' => 'ipdrec@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->patient1 = Patient::query()->create([
            'mrn' => 'CHG-2026-000501',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'IPD Patient One',
            'sex' => 'male',
            'date_of_birth' => '1960-01-01',
        ]);

        $this->patient2 = Patient::query()->create([
            'mrn' => 'CHG-2026-000502',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'IPD Patient Two',
            'sex' => 'female',
            'date_of_birth' => '1964-06-15',
        ]);
    }

    public function test_bed_conflict_prevention_transfer_discharge_and_cleaning_lifecycle(): void
    {
        $beds = Bed::query()->where('status', 'available')->take(2)->get();
        $bed1 = $beds[0];
        $bed2 = $beds[1];

        // Receptionist cannot access IPD or admit patients
        $this->actingAs($this->receptionist)->get('/ipd')->assertForbidden();
        $this->actingAs($this->receptionist)->post('/ipd/admit', [
            'patient_id' => $this->patient1->id,
            'bed_id' => $bed1->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'Unauthorized admit attempt',
        ])->assertForbidden();

        // Admit Patient 1 into Bed 1
        $this->actingAs($this->medicalOfficer)->post('/ipd/admit', [
            'patient_id' => $this->patient1->id,
            'bed_id' => $bed1->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'Acute Anterior STEMI',
        ])->assertRedirect(route('ipd.index'));

        $bed1->refresh();
        $admission = IpdAdmission::query()->firstOrFail();
        $this->assertSame('occupied', $bed1->status);
        $this->assertMatchesRegularExpression('/^ADM-\d{4}-000001$/', $admission->admission_number);
        $this->assertSame('IMP', $admission->encounter->class);

        // BED ALLOCATION CONFLICT: Admitting Patient 2 into already-occupied Bed 1 MUST fail
        $this->actingAs($this->consultant)->post('/ipd/admit', [
            'patient_id' => $this->patient2->id,
            'bed_id' => $bed1->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'NSTEMI',
        ])->assertSessionHasErrors('bed_id');
        $this->assertSame(1, IpdAdmission::query()->count());

        // DOUBLE ADMISSION PREVENTION: Patient 1 cannot be admitted a second time into Bed 2 while still admitted
        $this->actingAs($this->consultant)->post('/ipd/admit', [
            'patient_id' => $this->patient1->id,
            'bed_id' => $bed2->id,
            'admission_type' => 'elective',
            'admission_diagnosis' => 'Duplicate admission attempt',
        ])->assertSessionHasErrors('bed_id');

        // BED TRANSFER: Nurse transfers Patient 1 from Bed 1 -> Bed 2; Bed 1 moves to 'cleaning'
        $this->actingAs($this->nurse)->post(route('ipd.admissions.transfer', $admission), [
            'target_bed_id' => $bed2->id,
            'reason' => 'Step-down transfer after 48h telemetry monitoring',
        ])->assertRedirect(route('ipd.index'));

        $bed1->refresh();
        $bed2->refresh();
        $admission->refresh();
        $this->assertSame('cleaning', $bed1->status);
        $this->assertSame('occupied', $bed2->status);
        $this->assertSame($bed2->id, $admission->bed_id);
        $this->assertSame(2, BedAllocation::query()->where('admission_id', $admission->id)->count());

        // Bed 1 in 'cleaning' cannot be allocated until marked clean
        $this->actingAs($this->consultant)->post('/ipd/admit', [
            'patient_id' => $this->patient2->id,
            'bed_id' => $bed1->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'Trying to use uncleaned bed',
        ])->assertSessionHasErrors('bed_id');

        // Nurse marks Bed 1 clean -> now 'available'
        $this->actingAs($this->nurse)
            ->post(route('ipd.beds.mark-available', $bed1))
            ->assertRedirect();
        $this->assertSame('available', $bed1->fresh()->status);

        // DISCHARGE AUTHORIZATION: Medical officer without ipd.discharge gets 403
        $this->actingAs($this->medicalOfficer)->post(route('ipd.admissions.discharge', $admission), [
            'discharge_status' => 'discharged',
            'discharge_condition' => 'improved',
            'discharge_summary' => 'Patient recovered well after primary PCI to LAD.',
        ])->assertForbidden();

        // Consultant discharges Patient 1 -> closes encounter & releases Bed 2 to 'cleaning'
        $this->actingAs($this->consultant)->post(route('ipd.admissions.discharge', $admission), [
            'discharge_status' => 'discharged',
            'discharge_condition' => 'improved',
            'discharge_summary' => 'Successful primary PCI to proximal LAD; hemodynamically stable on dual antiplatelet therapy.',
            'follow_up_plan' => 'Cardiology OPD in 14 days with ECG and lipid panel.',
        ])->assertRedirect(route('ipd.index'));

        $admission->refresh();
        $bed2->refresh();
        $this->assertSame(IpdAdmission::STATUS_DISCHARGED, $admission->status);
        $this->assertSame('cleaning', $bed2->status);
        $this->assertSame('finished', $admission->encounter->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ipd.discharged',
            'patient_id' => $this->patient1->id,
        ]);
    }
}
