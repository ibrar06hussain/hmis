<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Emr;

use App\Models\User;
use App\Modules\Emr\Services\ClinicianPreferenceService;
use App\Modules\Patient\Models\Patient;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Terminology\Models\TerminologyCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClinicianPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_each_consultant_can_add_and_remove_own_clinical_assessment_chips_isolated_per_doctor(): void
    {
        $docA = User::query()->where('username', 'consultant')->firstOrFail();

        $docB = User::query()->create([
            'name' => 'Dr. Second Cardiologist',
            'username' => 'consultant_b',
            'email' => 'docb@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $docB->assignRole('consultant');

        $reception = User::query()->where('username', 'reception')->firstOrFail();

        // Non-clinical receptionist cannot customize clinical chips (403)
        $this->actingAs($reception)
            ->postJson(route('emr.preferences.chips.store'), [
                'category' => 'symptom',
                'label' => 'Unauthorized chip',
            ])
            ->assertForbidden();

        // Doctor A adds a custom symptom chip and custom exam chip
        $this->actingAs($docA)
            ->postJson(route('emr.preferences.chips.store'), [
                'category' => 'symptom',
                'label' => 'Post-prandial angina',
            ])
            ->assertCreated()
            ->assertJson(['ok' => true]);

        // Doctor A hides a default symptom chip ("Palpitations")
        $this->actingAs($docA)
            ->deleteJson(route('emr.preferences.chips.destroy'), [
                'category' => 'symptom',
                'label' => 'Palpitations',
            ])
            ->assertOk();

        $pref = app(ClinicianPreferenceService::class);
        $docASymptoms = collect($pref->chipsForUser($docA)['symptom'])->pluck('label')->all();
        $docBSymptoms = collect($pref->chipsForUser($docB)['symptom'])->pluck('label')->all();

        // Remembered for Doctor A
        $this->assertContains('Post-prandial angina', $docASymptoms);
        $this->assertNotContains('Palpitations', $docASymptoms);

        // Completely isolated: Doctor B still sees default "Palpitations" and does NOT see Doctor A's custom chip
        $this->assertContains('Palpitations', $docBSymptoms);
        $this->assertNotContains('Post-prandial angina', $docBSymptoms);
    }

    public function test_consultant_can_pin_and_remove_own_diagnosis_shortcuts(): void
    {
        $docA = User::query()->where('username', 'consultant')->firstOrFail();
        $mitralStenosis = TerminologyCode::query()->where('code', 'I05.0')->firstOrFail();
        $htn = TerminologyCode::query()->where('code', 'I10')->firstOrFail();

        // Pin Mitral Stenosis (I05.0) as a personal shortcut
        $this->actingAs($docA)
            ->postJson(route('emr.preferences.chips.store'), [
                'category' => 'diagnosis_shortcut',
                'terminology_code_id' => $mitralStenosis->id,
                'label' => 'RHD Mitral Stenosis (I05.0)',
            ])
            ->assertCreated();

        // Hide default HTN shortcut for Doctor A
        $this->actingAs($docA)
            ->deleteJson(route('emr.preferences.chips.destroy'), [
                'category' => 'diagnosis_shortcut',
                'terminology_code_id' => $htn->id,
                'label' => 'HTN (I10)',
            ])
            ->assertOk();

        $shortcuts = collect(app(ClinicianPreferenceService::class)->chipsForUser($docA)['diagnosis_shortcut']);
        $this->assertTrue($shortcuts->contains('terminology_code_id', $mitralStenosis->id));
        $this->assertFalse($shortcuts->contains('terminology_code_id', $htn->id));
    }

    public function test_doctor_can_add_own_custom_medicine_pin_formulary_meds_and_prescribe_in_rapid_consult(): void
    {
        $docA = User::query()->where('username', 'consultant')->firstOrFail();
        $docB = User::query()->create([
            'name' => 'Dr. Other Consultant',
            'username' => 'consultant_c',
            'email' => 'docc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $docB->assignRole('consultant');

        $nurse = User::query()->where('username', 'nurse_ccu')->firstOrFail();

        // Nurse lacks pharmacy.prescribe -> blocked (403)
        $this->actingAs($nurse)
            ->postJson(route('emr.preferences.medications.store'), [
                'generic_name' => 'Rosuvastatin',
                'strength' => '20 mg',
            ])
            ->assertForbidden();

        // Doctor A adds own custom medicine (Rosuvastatin 20 mg / Rovista) -> auto-pinned to Doctor A's Quick Medicines
        $createResp = $this->actingAs($docA)
            ->postJson(route('emr.preferences.medications.store'), [
                'generic_name' => 'Rosuvastatin',
                'brand_name' => 'Rovista',
                'strength' => '20 mg',
                'dosage_form' => 'tablet',
                'drug_class' => 'Statin',
                'default_frequency' => 'HS',
                'default_duration_days' => 30,
            ])
            ->assertCreated()
            ->assertJson(['ok' => true]);

        $customMedId = (int) $createResp->json('medication.id');
        $customMed = Medication::query()->findOrFail($customMedId);
        $this->assertFalse($customMed->is_hospital_formulary);
        $this->assertSame($docA->id, $customMed->added_by_user_id);

        // Isolated visibility: visible to Doctor A, NOT cluttering Doctor B's list
        $pref = app(ClinicianPreferenceService::class);
        $this->assertTrue($pref->medicationsVisibleToUser($docA)->contains('id', $customMedId));
        $this->assertFalse($pref->medicationsVisibleToUser($docB)->contains('id', $customMedId));

        // Doctor A pins a shared hospital formulary drug (Aspirin) and then unpins it
        $aspirin = Medication::query()->where('code', 'ASP-75')->firstOrFail();
        $this->actingAs($docA)
            ->postJson(route('emr.preferences.medications.pin'), [
                'medication_id' => $aspirin->id,
                'default_frequency' => 'OD',
            ])
            ->assertOk();
        $this->assertCount(2, $pref->favoriteMedicationsForUser($docA));

        $this->actingAs($docA)
            ->deleteJson(route('emr.preferences.medications.unpin', $aspirin))
            ->assertOk();
        $this->assertCount(1, $pref->favoriteMedicationsForUser($docA));

        // Doctor A prescribes their newly added custom medicine inside Rapid 60s Consult
        $patient = Patient::query()->firstOrFail();
        $encounter = $patient->encounters()->firstOrFail();

        $this->actingAs($docA)
            ->post(route('encounters.rapid.store', $encounter), [
                'chief_complaint' => 'Dyslipidemia follow-up',
                'medications' => [
                    [
                        'medication_id' => $customMedId,
                        'dose' => '1 tab',
                        'route' => 'PO',
                        'frequency' => 'HS',
                        'duration_days' => 30,
                        'quantity_prescribed' => 30,
                        'meal_timing' => 'after_meal',
                    ],
                ],
                'sign_now' => '1',
                'post_action' => 'print_rx',
            ])
            ->assertRedirect(route('encounters.prescription.print', $encounter));

        $this->actingAs($docA)
            ->get(route('encounters.prescription.print', $encounter))
            ->assertOk()
            ->assertSee('Rosuvastatin 20 mg')
            ->assertSee('Rovista');
    }
}
