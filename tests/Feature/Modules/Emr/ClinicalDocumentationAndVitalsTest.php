<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Emr;

use App\Models\User;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\VitalSign;
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

class ClinicalDocumentationAndVitalsTest extends TestCase
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
        $dept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();

        $this->consultant = User::query()->create([
            'name' => 'Dr. Senior Cardiologist',
            'username' => 'emr_doc',
            'email' => 'emrdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $dept->id,
            'employee_code' => 'EMR-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->medicalOfficer = User::query()->create([
            'name' => 'Dr. Junior MO',
            'username' => 'emr_mo',
            'email' => 'emrmo@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->medicalOfficer->assignRole('medical_officer');
        StaffProfile::query()->create([
            'user_id' => $this->medicalOfficer->id,
            'facility_id' => $facility->id,
            'department_id' => $dept->id,
            'employee_code' => 'EMR-MO-1',
            'designation' => 'Medical Officer',
        ]);

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000301',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Cardiology EMR Synthetic Patient',
            'sex' => 'male',
            'date_of_birth' => '1962-05-05',
        ]);

        $this->encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000301',
            'patient_id' => $this->patient->id,
            'department_id' => $dept->id,
            'attending_staff_id' => $this->consultant->staffProfile->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_vitals_compute_bmi_and_flag_cardiac_abnormalities(): void
    {
        // Empty payload rejected
        $this->actingAs($this->medicalOfficer)
            ->post(route('encounters.vitals.store', $this->encounter), [])
            ->assertSessionHasErrors('systolic_bp');

        // Hypertensive crisis + tachycardia + hypoxia flagged automatically
        $this->actingAs($this->medicalOfficer)
            ->post(route('encounters.vitals.store', $this->encounter), [
                'systolic_bp' => 192,
                'diastolic_bp' => 122,
                'heart_rate' => 118,
                'spo2_percent' => 89,
                'respiratory_rate' => 24,
                'temperature_c' => 38.4,
                'weight_kg' => 80,
                'height_cm' => 160,
                'rhythm_note' => 'Irregularly irregular',
            ])
            ->assertRedirect(route('encounters.show', $this->encounter));

        $vital = VitalSign::query()->firstOrFail();
        $this->assertTrue($vital->is_abnormal);
        $this->assertEqualsWithDelta(31.25, $vital->bmi, 0.1);
        $this->assertContains('HYPERTENSIVE_CRISIS', $vital->abnormal_flags);
        $this->assertContains('TACHYCARDIA', $vital->abnormal_flags);
        $this->assertContains('HYPOXIA', $vital->abnormal_flags);
        $this->assertContains('FEVER', $vital->abnormal_flags);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'emr.vitals.recorded',
            'patient_id' => $this->patient->id,
        ]);
    }

    public function test_clinical_note_draft_sign_immutability_and_audited_amendment(): void
    {
        // MO creates draft cardiology evaluation note
        $this->actingAs($this->medicalOfficer)
            ->post(route('encounters.notes.store', $this->encounter), [
                'note_type' => 'cardiology_eval',
                'nyha_class' => 'III',
                'chest_pain_type' => 'typical_angina',
                'chief_complaint' => 'Substernal chest pain on minimal exertion',
                'assessment_and_diagnosis' => 'Unstable angina (provisional)',
                'treatment_plan' => 'Serial Troponin I, ECG, Aspirin + Clopidogrel loading',
                'ecg_summary' => 'ST depression V4-V6',
            ])
            ->assertRedirect(route('encounters.show', $this->encounter));

        $draft = ClinicalNote::query()->firstOrFail();
        $this->assertSame(ClinicalNote::STATUS_DRAFT, $draft->status);
        $this->assertSame('III', $draft->structured_payload['nyha_class']);

        // MO without emr.note.sign permission cannot sign the note
        $this->actingAs($this->medicalOfficer)
            ->post(route('encounters.notes.sign', [$this->encounter, $draft]))
            ->assertForbidden();

        // Consultant signs and locks the note
        $this->actingAs($this->consultant)
            ->post(route('encounters.notes.sign', [$this->encounter, $draft]))
            ->assertRedirect(route('encounters.show', $this->encounter));

        $draft->refresh();
        $this->assertSame(ClinicalNote::STATUS_SIGNED, $draft->status);
        $this->assertSame($this->consultant->id, $draft->signed_by_user_id);
        $this->assertNotNull($draft->signed_at);

        // Signed note cannot be modified directly via draft update route
        $this->actingAs($this->consultant)
            ->put(route('encounters.notes.update', [$this->encounter, $draft]), [
                'note_type' => 'cardiology_eval',
                'chief_complaint' => 'Attempting to overwrite signed note',
            ])
            ->assertSessionHasErrors('note');

        // Defense-in-depth: Eloquent model itself refuses direct mutation of signed content
        try {
            $draft->assessment_and_diagnosis = 'Tampered text';
            $draft->save();
            $this->fail('Expected LogicException when mutating signed clinical note.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        // Audited amendment creates Revision 2 while keeping Revision 1 content intact
        $this->actingAs($this->consultant)
            ->post(route('encounters.notes.amend', [$this->encounter, $draft]), [
                'amendment_reason' => 'Updated diagnosis after elevated high-sensitivity Troponin I returned.',
                'chief_complaint' => 'Substernal chest pain on minimal exertion',
                'assessment_and_diagnosis' => 'NSTEMI confirmed on elevated Troponin I',
                'treatment_plan' => 'Admit to CCU for early invasive angiography strategy',
            ])
            ->assertRedirect(route('encounters.show', $this->encounter));

        $original = ClinicalNote::query()->findOrFail($draft->id);
        $revision2 = ClinicalNote::query()->where('supersedes_note_id', $draft->id)->firstOrFail();

        $this->assertSame(ClinicalNote::STATUS_AMENDED, $original->status);
        $this->assertSame('Unstable angina (provisional)', $original->assessment_and_diagnosis); // original preserved!

        $this->assertSame(ClinicalNote::STATUS_SIGNED, $revision2->status);
        $this->assertSame(2, $revision2->revision_number);
        $this->assertSame('NSTEMI confirmed on elevated Troponin I', $revision2->assessment_and_diagnosis);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'emr.note.amended',
            'patient_id' => $this->patient->id,
        ]);
    }

    public function test_clinician_can_record_patient_allergy_and_it_renders_on_profile(): void
    {
        $this->actingAs($this->consultant)
            ->post(route('patients.allergies.store', $this->patient), [
                'substance' => 'Clopidogrel',
                'category' => 'medication',
                'severity' => 'severe',
                'reaction' => 'Generalized rash and angioedema',
            ])
            ->assertRedirect();

        $this->actingAs($this->consultant)
            ->get(route('patients.show', $this->patient))
            ->assertOk()
            ->assertSee('CRITICAL ALLERGY ALERT')
            ->assertSee('Clopidogrel');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'emr.allergy.recorded',
            'patient_id' => $this->patient->id,
        ]);
    }
}
