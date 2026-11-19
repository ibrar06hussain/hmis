<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Patient;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientAuthorizationAndBreakGlassTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    private User $itAdmin;

    private User $receptionist;

    private User $opdConsultant;

    private User $ccuNurse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $opdDept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();
        $ccuDept = Department::query()->where('code', 'CCU')->firstOrFail();

        $this->itAdmin = User::query()->create([
            'name' => 'IT Admin',
            'username' => 'it1',
            'email' => 'it1@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->itAdmin->assignRole('it_administrator');

        $this->receptionist = User::query()->create([
            'name' => 'Receptionist',
            'username' => 'rec2',
            'email' => 'rec2@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->opdConsultant = User::query()->create([
            'name' => 'OPD Consultant',
            'username' => 'doc_opd',
            'email' => 'doc.opd@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->opdConsultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->opdConsultant->id,
            'facility_id' => $facility->id,
            'department_id' => $opdDept->id,
            'employee_code' => 'EMP-OPD-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->ccuNurse = User::query()->create([
            'name' => 'CCU Nurse',
            'username' => 'nurse_ccu_test',
            'email' => 'nurse.ccu@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->ccuNurse->assignRole('nurse');
        StaffProfile::query()->create([
            'user_id' => $this->ccuNurse->id,
            'facility_id' => $facility->id,
            'department_id' => $ccuDept->id,
            'employee_code' => 'EMP-CCU-1',
            'designation' => 'Charge Nurse',
        ]);

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000777',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Scoped Access Patient',
            'sex' => 'male',
            'date_of_birth' => '1965-02-02',
        ]);

        // Patient currently has an encounter ONLY in CARD-OPD
        Encounter::query()->create([
            'encounter_number' => 'ENC-SCOPE-01',
            'patient_id' => $this->patient->id,
            'department_id' => $opdDept->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);

        PatientAllergy::query()->create([
            'patient_id' => $this->patient->id,
            'substance' => 'Penicillin',
            'category' => 'medication',
            'severity' => 'severe',
            'clinical_status' => 'active',
            'recorded_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_it_administrator_cannot_browse_patient_directory_or_profiles(): void
    {
        $this->actingAs($this->itAdmin)->get('/patients')->assertForbidden();
        $this->actingAs($this->itAdmin)->get(route('patients.show', $this->patient))->assertForbidden();
    }

    public function test_receptionist_sees_demographics_but_not_clinical_allergies(): void
    {
        $response = $this->actingAs($this->receptionist)->get(route('patients.show', $this->patient));

        $response->assertOk();
        $response->assertSee('Scoped Access Patient');
        $response->assertSee('Clinical Chart Restricted');
        $response->assertDontSee('Penicillin');
    }

    public function test_department_scoping_and_audited_break_glass_for_clinician(): void
    {
        // OPD consultant sees clinical allergy immediately (department matches encounter)
        $this->actingAs($this->opdConsultant)
            ->get(route('patients.show', $this->patient))
            ->assertOk()
            ->assertSee('Penicillin');

        // CCU nurse (different department) is restricted from clinical chart initially
        $this->actingAs($this->ccuNurse)
            ->get(route('patients.show', $this->patient))
            ->assertOk()
            ->assertSee('Clinical Chart Restricted')
            ->assertDontSee('Penicillin');

        // Break-glass requires a valid clinical justification
        $this->actingAs($this->ccuNurse)
            ->post(route('patients.break-glass', $this->patient), [
                'reason_code' => 'emergency_cardiac_event',
                'justification' => 'short',
            ])
            ->assertSessionHasErrors('justification');

        // Valid Break-Glass grants access and writes immutable audit records
        $this->actingAs($this->ccuNurse)
            ->post(route('patients.break-glass', $this->patient), [
                'reason_code' => 'emergency_cardiac_event',
                'justification' => 'Patient transferred urgently to CCU with acute chest pain; allergy check needed.',
            ])
            ->assertRedirect(route('patients.show', $this->patient));

        $this->assertDatabaseHas('break_glass_logs', [
            'user_id' => $this->ccuNurse->id,
            'patient_id' => $this->patient->id,
            'reason_code' => 'emergency_cardiac_event',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'security.break_glass.granted',
            'actor_user_id' => $this->ccuNurse->id,
            'patient_id' => $this->patient->id,
        ]);

        // Now CCU nurse sees clinical chart & allergies with active break-glass banner
        $this->actingAs($this->ccuNurse)
            ->get(route('patients.show', $this->patient))
            ->assertOk()
            ->assertSee('Penicillin')
            ->assertSee('BREAK-GLASS SESSION ACTIVE');
    }
}
