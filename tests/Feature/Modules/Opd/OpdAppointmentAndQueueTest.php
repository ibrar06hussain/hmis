<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Opd;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\VitalSign;
use App\Modules\Emr\Services\ClinicalNoteService;
use App\Modules\Emr\Services\VitalSignService;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\PatientMergeService;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OpdAppointmentAndQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    private User $consultant;

    private User $itAdmin;

    private Department $cardOpd;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $this->cardOpd = Department::query()->where('code', 'CARD-OPD')->firstOrFail();

        $this->receptionist = User::query()->create([
            'name' => 'OPD Receptionist',
            'username' => 'opd_rec',
            'email' => 'opdrec@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->consultant = User::query()->create([
            'name' => 'Dr. OPD Cardiologist',
            'username' => 'opd_doc',
            'email' => 'opddoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $this->cardOpd->id,
            'employee_code' => 'DOC-OPD-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->itAdmin = User::query()->create([
            'name' => 'IT Admin',
            'username' => 'opd_it',
            'email' => 'opdit@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->itAdmin->assignRole('it_administrator');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000101',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'OPD Queue Synthetic Patient',
            'sex' => 'male',
            'date_of_birth' => '1968-01-10',
        ]);
    }

    public function test_full_opd_workflow_booking_tokens_checkin_consult_and_completion(): void
    {
        $date = '2026-11-20';

        // Book first appointment -> Token #001
        $this->actingAs($this->receptionist)->post('/opd/appointments', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->cardOpd->id,
            'clinician_staff_id' => $this->consultant->staffProfile->id,
            'appointment_date' => $date,
            'visit_type' => 'new',
            'chief_complaint' => 'Palpitations and exertional dyspnea',
        ])->assertRedirect();

        // Book second appointment same dept/date -> Token #002
        $patient2 = Patient::query()->create([
            'mrn' => 'CHG-2026-000102',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Second OPD Patient',
            'sex' => 'female',
            'date_of_birth' => '1975-02-02',
        ]);

        $this->actingAs($this->receptionist)->post('/opd/appointments', [
            'patient_id' => $patient2->id,
            'department_id' => $this->cardOpd->id,
            'appointment_date' => $date,
            'visit_type' => 'follow_up',
        ])->assertRedirect();

        $appointments = Appointment::query()->orderBy('token_number')->get();
        $this->assertCount(2, $appointments);
        $this->assertSame(1, $appointments[0]->token_number);
        $this->assertSame(2, $appointments[1]->token_number);

        $first = $appointments[0];

        // Receptionist checks in patient -> atomically creates AMB Encounter
        $this->actingAs($this->receptionist)
            ->post(route('opd.appointments.check-in', $first))
            ->assertRedirect();

        $first->refresh();
        $this->assertSame(Appointment::STATUS_CHECKED_IN, $first->status);
        $this->assertNotNull($first->encounter_id);
        $encounter = Encounter::query()->findOrFail($first->encounter_id);
        $this->assertSame('AMB', $encounter->class);
        $this->assertSame('arrived', $encounter->status);
        $this->assertMatchesRegularExpression('/^ENC-\d{4}-000001$/', $encounter->encounter_number);

        // Receptionist cannot start clinical consultation
        $this->actingAs($this->receptionist)
            ->post(route('opd.appointments.start-consult', $first))
            ->assertForbidden();

        // Consultant starts consultation -> redirects to encounter workspace
        $this->actingAs($this->consultant)
            ->post(route('opd.appointments.start-consult', $first))
            ->assertRedirect(route('encounters.show', $encounter));

        $first->refresh();
        $encounter->refresh();
        $this->assertSame(Appointment::STATUS_IN_CONSULT, $first->status);
        $this->assertSame('in_progress', $encounter->status);

        // Consultant completes consultation
        $this->actingAs($this->consultant)
            ->post(route('encounters.complete', $encounter))
            ->assertRedirect(route('encounters.show', $encounter));

        $first->refresh();
        $encounter->refresh();
        $this->assertSame(Appointment::STATUS_COMPLETED, $first->status);
        $this->assertSame('finished', $encounter->status);
        $this->assertNotNull($encounter->ended_at);
    }

    public function test_it_admin_cannot_access_opd_queue_and_cancelled_appointment_cannot_checkin(): void
    {
        $this->actingAs($this->itAdmin)->get('/opd')->assertForbidden();

        $appt = app(AppointmentService::class)->book([
            'patient_id' => $this->patient->id,
            'department_id' => $this->cardOpd->id,
            'appointment_date' => '2026-11-20',
            'visit_type' => 'new',
        ], $this->receptionist);

        $this->actingAs($this->receptionist)
            ->post(route('opd.appointments.cancel', $appt))
            ->assertRedirect();

        $appt->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $appt->status);

        // Checking in a cancelled appointment fails gracefully
        $this->actingAs($this->receptionist)
            ->post(route('opd.appointments.check-in', $appt))
            ->assertSessionHasErrors('appointment');
    }

    public function test_patient_merge_reassigns_appointments_vitals_and_clinical_notes(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'username' => 'merge_adm',
            'email' => 'mergeadm@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $admin->assignRole('administrator');

        $target = Patient::query()->create([
            'mrn' => 'CHG-2026-000200',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Canonical Surviving Patient',
            'sex' => 'male',
            'date_of_birth' => '1968-01-10',
        ]);

        $apptService = app(AppointmentService::class);
        $appt = $apptService->book([
            'patient_id' => $this->patient->id,
            'department_id' => $this->cardOpd->id,
            'appointment_date' => '2026-11-20',
            'visit_type' => 'urgent',
        ], $this->receptionist);
        $appt = $apptService->checkIn($appt, $this->receptionist);

        app(VitalSignService::class)->record($appt->encounter, [
            'systolic_bp' => 130,
            'diastolic_bp' => 84,
            'heart_rate' => 78,
        ], $this->consultant);

        app(ClinicalNoteService::class)->createDraft($appt->encounter, [
            'note_type' => 'cardiology_eval',
            'chief_complaint' => 'Follow-up chest discomfort',
        ], $this->consultant);

        app(PatientMergeService::class)->merge($this->patient, $target, $admin, 'Confirmed duplicate OPD record via CNIC');

        $this->assertSame(1, Appointment::query()->where('patient_id', $target->id)->count());
        $this->assertSame(1, VitalSign::query()->where('patient_id', $target->id)->count());
        $this->assertSame(1, ClinicalNote::query()->where('patient_id', $target->id)->count());
    }
}
