<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Emr;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\ClinicalOrderSet;
use App\Modules\Emr\Services\RapidConsultService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreConsultCoSignAndFavoritesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_nurse_pre_consult_populates_vitals_risk_factors_and_allergy_for_consultant(): void
    {
        $nurse = User::query()->where('username', 'nurse_ccu')->firstOrFail();
        $reception = User::query()->where('username', 'reception')->firstOrFail();

        // Receptionist lacks emr.vitals.record -> 403
        $this->actingAs($reception)->get(route('opd.pre-consult.index'))->assertForbidden();

        // Check in seeded appointment so an OPD encounter exists for today
        $appt = Appointment::query()->firstOrFail();
        $checkedIn = app(AppointmentService::class)->checkIn($appt, $reception);
        $encounter = $checkedIn->encounter;

        $this->actingAs($nurse)
            ->get(route('opd.pre-consult.index'))
            ->assertOk()
            ->assertSee($appt->patient->full_name)
            ->assertSee('Pending Pre-Assessment');

        $this->actingAs($nurse)
            ->post(route('opd.pre-consult.store', $encounter), [
                'systolic_bp' => 162,
                'diastolic_bp' => 98,
                'heart_rate' => 92,
                'spo2_percent' => 97,
                'weight_kg' => 76.5,
                'chief_complaint' => 'Exertional dyspnea for 1 week',
                'risk_factors' => ['Hypertension (HTN)', 'Diabetes Mellitus (DM)'],
                'new_allergy_substance' => 'Penicillin',
                'new_allergy_category' => 'medication',
                'new_allergy_severity' => 'severe',
            ])
            ->assertRedirect(route('opd.pre-consult.index'));

        $encounter->refresh();
        $this->assertSame(1, $encounter->vitalSigns()->count());
        $this->assertTrue($encounter->vitalSigns()->firstOrFail()->is_abnormal);
        $this->assertTrue($encounter->patient->activeAllergies()->where('substance', 'Penicillin')->exists());
        $this->assertTrue(
            $encounter->clinicalNotes()
                ->where('status', ClinicalNote::STATUS_DRAFT)
                ->where('past_medical_history', 'like', '%Hypertension (HTN)%')
                ->exists()
        );
    }

    public function test_consultant_1_click_cosign_tray_signs_mo_draft_and_blocks_mo_self_sign(): void
    {
        $mo = User::query()->where('username', 'mo_er')->firstOrFail();
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $reception = User::query()->where('username', 'reception')->firstOrFail();

        $appt = Appointment::query()->firstOrFail();
        $encounter = app(AppointmentService::class)->checkIn($appt, $reception)->encounter;

        // MO submits a Rapid Consult draft
        $result = app(RapidConsultService::class)->commit($encounter, [
            'chief_complaint' => 'Palpitations and mild chest discomfort',
            'physical_examination' => 'S1+S2 audible, HR 104 irregular',
            'assessment_and_diagnosis' => 'Suspected paroxysmal AFib',
            'sign_now' => true, // ignored for MO
        ], $mo);

        $draftNote = $result['note'];
        $this->assertSame(ClinicalNote::STATUS_DRAFT, $draftNote->status);

        // MO cannot access Co-Sign Tray or self-approve
        $this->actingAs($mo)->get(route('emr.cosign.index'))->assertForbidden();
        $this->actingAs($mo)->post(route('emr.cosign.approve', $draftNote))->assertForbidden();

        // Consultant sees the draft in Co-Sign Tray and approves in 1 click
        $this->actingAs($consultant)
            ->get(route('emr.cosign.index'))
            ->assertOk()
            ->assertSee('Suspected paroxysmal AFib');

        $this->actingAs($consultant)
            ->post(route('emr.cosign.approve', $draftNote), [
                'complete_encounter' => '1',
                'print_after' => '1',
            ])
            ->assertRedirect(route('encounters.prescription.print', $encounter));

        $draftNote->refresh();
        $encounter->refresh();
        $this->assertSame(ClinicalNote::STATUS_SIGNED, $draftNote->status);
        $this->assertSame($consultant->id, $draftNote->signed_by_user_id);
        $this->assertSame('finished', $encounter->status);
    }

    public function test_doctor_personal_favorite_order_sets_are_scoped_and_non_destructively_archived(): void
    {
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $mo = User::query()->where('username', 'mo_er')->firstOrFail();

        $this->actingAs($consultant)
            ->post(route('emr.favorites.store'), [
                'name' => 'Dr. Consultant Custom Post-CABG Set',
                'description' => 'Personal favorite for stable post-CABG OPD',
                'default_nyha_class' => 'I',
                'default_exam_text' => 'Sternotomy scar healed well, S1+S2+0, chest clear.',
                'medication_codes' => ['ASP-75', 'ATOR-40', 'BISO-5'],
                'lab_test_codes' => ['LDL', 'HBA1C'],
                'imaging_codes' => ['ECG-12'],
                'default_duration_days' => 30,
            ])
            ->assertRedirect(route('emr.favorites.index'));

        $favorite = ClinicalOrderSet::query()->where('owner_user_id', $consultant->id)->firstOrFail();
        $this->assertFalse($favorite->is_shared);
        $this->assertCount(3, (array) $favorite->medications);

        // Visible in consultant's resolved Rapid Consult bundles, NOT in MO's bundles
        $consultantSetNames = collect(app(RapidConsultService::class)->resolvedOrderSetsForUser($consultant))->pluck('name')->all();
        $moSetNames = collect(app(RapidConsultService::class)->resolvedOrderSetsForUser($mo))->pluck('name')->all();

        $this->assertContains('Dr. Consultant Custom Post-CABG Set', $consultantSetNames);
        $this->assertNotContains('Dr. Consultant Custom Post-CABG Set', $moSetNames);

        // Another clinician cannot archive consultant's favorite
        $this->actingAs($mo)
            ->delete(route('emr.favorites.destroy', $favorite))
            ->assertForbidden();

        // Consultant archives own favorite (non-destructive soft deactivation)
        $this->actingAs($consultant)
            ->delete(route('emr.favorites.destroy', $favorite))
            ->assertRedirect(route('emr.favorites.index'));

        $favorite->refresh();
        $this->assertFalse($favorite->is_active);
    }
}
