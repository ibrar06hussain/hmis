<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Nursing;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Nursing\Models\NursingMarRecord;
use App\Modules\Nursing\Models\NursingNote;
use App\Modules\Organization\Models\Department;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NursingMarAndNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $nurse;

    private User $receptionist;

    private Patient $patient;

    private Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $ccuDept = Department::query()->where('code', 'CCU')->firstOrFail();

        $this->nurse = User::query()->create([
            'name' => 'CCU Charge Nurse',
            'username' => 'mar_nurse',
            'email' => 'marnurse@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->nurse->assignRole('nurse');

        $this->receptionist = User::query()->create([
            'name' => 'Receptionist',
            'username' => 'mar_rec',
            'email' => 'marrec@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000620',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'CCU Nursing Patient',
            'sex' => 'male',
            'date_of_birth' => '1958-08-08',
        ]);

        $this->encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000620',
            'patient_id' => $this->patient->id,
            'department_id' => $ccuDept->id,
            'class' => 'IMP',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_nursing_mar_held_requires_reason_and_fluid_balance_computes_net_io(): void
    {
        // Receptionist denied Nursing Station
        $this->actingAs($this->receptionist)->get('/nursing')->assertForbidden();

        // Held dose WITHOUT clinical_reason must fail validation
        $this->actingAs($this->nurse)->post('/nursing/mar', [
            'encounter_id' => $this->encounter->id,
            'medication_name' => 'Bisoprolol',
            'dose_given' => '5 mg',
            'route' => 'PO',
            'status' => 'held',
        ])->assertSessionHasErrors('clinical_reason');

        // Held dose WITH clinical reason succeeds & writes audit log
        $this->actingAs($this->nurse)->post('/nursing/mar', [
            'encounter_id' => $this->encounter->id,
            'medication_name' => 'Bisoprolol',
            'dose_given' => '5 mg',
            'route' => 'PO',
            'status' => 'held',
            'clinical_reason' => 'Held due to symptomatic bradycardia (HR 48 bpm); consultant informed.',
        ])->assertRedirect(route('nursing.index'));

        $mar = NursingMarRecord::query()->firstOrFail();
        $this->assertSame('held', $mar->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'nursing.mar.recorded',
            'patient_id' => $this->patient->id,
        ]);

        // Fluid balance nursing note computes net I/O accurately
        $this->actingAs($this->nurse)->post('/nursing/notes', [
            'encounter_id' => $this->encounter->id,
            'shift' => 'night',
            'note_category' => 'fluid_balance',
            'intake_ml' => 1100,
            'output_ml' => 1450,
            'content' => 'Good diuretic response after IV Furosemide 40mg; lungs clearer.',
        ])->assertRedirect(route('nursing.index'));

        $note = NursingNote::query()->firstOrFail();
        $this->assertSame(-350, $note->netFluidBalanceMl());
    }
}
