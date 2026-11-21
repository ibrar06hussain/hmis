<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Emr;

use App\Models\User;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\ClinicalOrderSet;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Emr\Services\EncounterNumberGeneratorService;
use App\Modules\Emr\Services\RapidConsultService;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Organization\Models\Department;
use App\Modules\Patient\Models\Patient;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Pharmacy\Models\Prescription;
use App\Modules\Ris\Models\ImagingCatalog;
use App\Modules\Terminology\Models\TerminologyCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RapidConsultAndBilingualRxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_rapid_60_second_consult_commits_atomically_and_renders_bilingual_urdu_prescription(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $patient = Patient::query()->firstOrFail();
        $encounter = $patient->encounters()->firstOrFail();

        // Seeded hospital cardiac protocol order sets exist
        $this->assertGreaterThanOrEqual(5, ClinicalOrderSet::query()->count());

        $this->actingAs($consultant)
            ->get(route('encounters.rapid.show', $encounter))
            ->assertOk()
            ->assertSee('RAPID 60s CONSULT')
            ->assertSee('Post-PCI / ACS Secondary Prevention (DAPT)');

        $aspirin = Medication::query()->where('code', 'ASP-75')->firstOrFail();
        $atorva = Medication::query()->where('code', 'ATOR-40')->firstOrFail();
        $dxCode = TerminologyCode::query()->where('code', 'I10')->firstOrFail();
        $labTest = LabTestCatalog::query()->where('code', 'LDL')->firstOrFail();
        $echo = ImagingCatalog::query()->where('code', 'TTE')->firstOrFail();

        $this->actingAs($consultant)
            ->post(route('encounters.rapid.store', $encounter), [
                'note_type' => 'cardiology_eval',
                'chief_complaint' => 'Exertional chest tightness; HTN follow-up',
                'nyha_class' => 'II',
                'chest_pain_type' => 'typical_angina',
                'physical_examination' => 'S1+S2 audible, no murmur, chest clear bilaterally.',
                'assessment_and_diagnosis' => 'Stable angina and essential hypertension',
                'treatment_plan' => 'Optimize medical therapy and repeat Echo.',
                'follow_up_instructions' => 'Cardiology OPD in 4 weeks with Fasting Lipid Profile',
                'systolic_bp' => 144,
                'diastolic_bp' => 88,
                'heart_rate' => 74,
                'spo2_percent' => 98,
                'diagnosis_ids' => [$dxCode->id],
                'medications' => [
                    [
                        'medication_id' => $aspirin->id,
                        'dose' => '1 tab',
                        'route' => 'PO',
                        'frequency' => 'OD',
                        'duration_days' => 30,
                        'quantity_prescribed' => 30,
                        'meal_timing' => 'after_meal',
                        'instructions' => 'After breakfast',
                    ],
                    [
                        'medication_id' => $atorva->id,
                        'dose' => '1 tab',
                        'route' => 'PO',
                        'frequency' => 'HS',
                        'duration_days' => 30,
                        'quantity_prescribed' => 30,
                        'meal_timing' => 'after_meal',
                        'instructions' => 'At night',
                    ],
                ],
                'lab_test_ids' => [$labTest->id],
                'lab_priority' => 'routine',
                'imaging_catalog_ids' => [$echo->id],
                'imaging_priority' => 'routine',
                'sign_now' => '1',
                'complete_encounter' => '1',
                'post_action' => 'print_rx',
            ])
            ->assertRedirect(route('encounters.prescription.print', $encounter));

        // Verify atomic consolidation: 1 Prescription with 2 items, signed note, diagnosis, lab, imaging, encounter finished
        $encounter->refresh();
        $this->assertSame('finished', $encounter->status);
        $this->assertTrue($encounter->diagnoses()->where('code', 'I10')->exists());
        $this->assertTrue($encounter->labOrders()->exists());
        $this->assertTrue($encounter->imagingStudies()->where('modality', 'ECHO')->exists());

        $latestNote = $encounter->clinicalNotes()->orderByDesc('id')->firstOrFail();
        $this->assertSame(ClinicalNote::STATUS_SIGNED, $latestNote->status);
        $this->assertSame($consultant->id, $latestNote->signed_by_user_id);

        $rx = Prescription::query()->where('encounter_id', $encounter->id)->firstOrFail();
        $this->assertSame(2, $rx->items()->count());

        // Printable bilingual Urdu/English prescription with visual Morning/Noon/Night dosing grid
        $this->actingAs($consultant)
            ->get(route('encounters.prescription.print', $encounter))
            ->assertOk()
            ->assertSee('کارڈیک ہسپتال گلگت')
            ->assertSee('صبح')
            ->assertSee('دوپہر')
            ->assertSee('رات')
            ->assertSee('کھانے کے بعد')
            ->assertSee('Aspirin')
            ->assertSee('Atorvastatin')
            ->assertSee('<svg', false);
    }

    public function test_batch_allergy_conflict_rolls_back_entire_rapid_consult_until_audited_override(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $patient = Patient::query()->firstOrFail();
        $encounter = $patient->encounters()->firstOrFail();

        PatientAllergy::query()->create([
            'patient_id' => $patient->id,
            'substance' => 'Aspirin',
            'category' => 'medication',
            'severity' => 'severe',
            'reaction' => 'Bronchospasm',
            'clinical_status' => 'active',
            'recorded_by_user_id' => $consultant->id,
            'recorded_at' => Carbon::now('UTC'),
        ]);

        $aspirin = Medication::query()->where('code', 'ASP-75')->firstOrFail();
        $initialNoteCount = $encounter->clinicalNotes()->count();

        // Without override -> rejected & entire DB transaction rolled back
        $this->actingAs($consultant)
            ->post(route('encounters.rapid.store', $encounter), [
                'chief_complaint' => 'Follow-up chest pain',
                'medications' => [
                    ['medication_id' => $aspirin->id, 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 14, 'quantity_prescribed' => 14],
                ],
                'sign_now' => '1',
            ])
            ->assertSessionHas('allergy_conflict')
            ->assertSessionHasErrors('medications');

        $this->assertSame($initialNoteCount, $encounter->clinicalNotes()->count());
        $this->assertSame(0, Prescription::query()->where('encounter_id', $encounter->id)->count());

        // With explicit audited override -> succeeds
        $this->actingAs($consultant)
            ->post(route('encounters.rapid.store', $encounter), [
                'chief_complaint' => 'Post-stent urgent DAPT desensitization',
                'medications' => [
                    ['medication_id' => $aspirin->id, 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 14, 'quantity_prescribed' => 14],
                ],
                'confirm_allergy_override' => '1',
                'allergy_override_reason' => 'Mandatory post-PCI stent protection under desensitization protocol',
                'sign_now' => '1',
                'post_action' => 'workspace',
            ])
            ->assertRedirect(route('encounters.show', $encounter));

        $rx = Prescription::query()->where('encounter_id', $encounter->id)->firstOrFail();
        $this->assertTrue($rx->allergy_override_used);
    }

    public function test_copy_forward_snapshot_and_mo_draft_sign_guard(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $mo = User::query()->where('username', 'mo_er')->firstOrFail();
        $patient = Patient::query()->firstOrFail();
        $firstEncounter = $patient->encounters()->firstOrFail();

        $aspirin = Medication::query()->where('code', 'ASP-75')->firstOrFail();
        $dxCode = TerminologyCode::query()->where('code', 'I25.1')->firstOrFail();

        app(RapidConsultService::class)->commit($firstEncounter, [
            'chief_complaint' => 'Post-PCI visit 1',
            'past_medical_history' => 'HTN x 10y, LAD PCI 2025',
            'diagnosis_ids' => [$dxCode->id],
            'medications' => [
                ['medication_id' => $aspirin->id, 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 30, 'quantity_prescribed' => 30],
            ],
            'sign_now' => true,
            'complete_encounter' => true,
        ], $consultant);

        // Create a second follow-up encounter in ER/OPD for the same patient
        $dept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();
        $followUpEncounter = Encounter::query()->create([
            'encounter_number' => app(EncounterNumberGeneratorService::class)->generate(),
            'patient_id' => $patient->id,
            'department_id' => $dept->id,
            'attending_staff_id' => $consultant->staffProfile->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'chief_complaint' => 'Routine follow-up',
            'started_at' => Carbon::now('UTC'),
        ]);

        $snapshot = app(RapidConsultService::class)->copyForwardSnapshot($followUpEncounter);
        $this->assertNotNull($snapshot);
        $this->assertStringContainsString('LAD PCI 2025', (string) $snapshot['past_medical_history']);
        $this->assertContains($dxCode->id, $snapshot['diagnosis_ids']);
        $this->assertCount(1, $snapshot['medications']);

        // MO without emr.note.sign cannot sign even if sign_now=1 is sent -> stays cleanly in DRAFT
        $moResult = app(RapidConsultService::class)->commit($followUpEncounter, [
            'chief_complaint' => 'MO scribed draft for consultant review',
            'sign_now' => true,
        ], $mo);

        $this->assertSame(ClinicalNote::STATUS_DRAFT, $moResult['note']->status);
        $this->assertNull($moResult['note']->signed_by_user_id);
    }
}
