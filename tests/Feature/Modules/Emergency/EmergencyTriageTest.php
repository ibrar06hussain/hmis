<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Emergency;

use App\Models\User;
use App\Modules\Emergency\Models\EmergencyTriage;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmergencyTriageTest extends TestCase
{
    use RefreshDatabase;

    private User $erOfficer;

    private User $itAdmin;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $erDept = Department::query()->where('code', 'CARD-ER')->firstOrFail();

        $this->erOfficer = User::query()->create([
            'name' => 'ER Doctor',
            'username' => 'er_doc_triage',
            'email' => 'erdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->erOfficer->assignRole('medical_officer');
        StaffProfile::query()->create([
            'user_id' => $this->erOfficer->id,
            'facility_id' => $facility->id,
            'department_id' => $erDept->id,
            'employee_code' => 'ER-DOC-99',
            'designation' => 'ER Medical Officer',
        ]);

        $this->itAdmin = User::query()->create([
            'name' => 'IT Admin',
            'username' => 'er_it',
            'email' => 'erit@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->itAdmin->assignRole('it_administrator');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000601',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Acute Chest Pain Synthetic',
            'sex' => 'male',
            'date_of_birth' => '1965-03-03',
        ]);
    }

    public function test_stemi_triage_creates_emer_encounter_enforces_priority_and_audits(): void
    {
        $this->actingAs($this->itAdmin)->get('/emergency')->assertForbidden();

        // Even if user submits level 4, stemi_alert elevates priority to <= 2 and sets chest_pain_flag
        $this->actingAs($this->erOfficer)
            ->post('/emergency/triage', [
                'patient_id' => $this->patient->id,
                'triage_level' => 4,
                'arrival_mode' => 'ambulance_1122',
                'stemi_alert' => true,
                'presenting_complaint' => 'Crushing substernal chest pain with ST elevation in V1-V4',
                'triage_notes' => 'Aspirin 300mg given in ambulance',
            ])
            ->assertRedirect(route('emergency.index'));

        $triage = EmergencyTriage::query()->with('encounter')->firstOrFail();
        $this->assertTrue($triage->stemi_alert);
        $this->assertTrue($triage->chest_pain_flag);
        $this->assertLessThanOrEqual(2, $triage->triage_level);
        $this->assertSame('EMER', $triage->encounter->class);
        $this->assertSame('in_progress', $triage->encounter->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'emergency.code_stemi_triaged',
            'patient_id' => $this->patient->id,
        ]);

        // Disposition update closes ER encounter when discharged
        $this->actingAs($this->erOfficer)
            ->post(route('emergency.triage.disposition', $triage), [
                'disposition' => 'discharged',
            ])
            ->assertRedirect();

        $triage->refresh();
        $this->assertSame('discharged', $triage->disposition);
        $this->assertSame('finished', $triage->encounter->status);
    }
}
