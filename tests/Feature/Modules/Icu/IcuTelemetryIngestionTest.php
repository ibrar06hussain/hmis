<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Icu;

use App\Models\User;
use App\Modules\Icu\Models\IcuDeviceObservation;
use App\Modules\Ipd\Services\IpdAdmissionService;
use App\Modules\Organization\Models\Bed;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IcuTelemetryIngestionTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $nurse;

    private Bed $ccuBed;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $this->consultant = User::query()->create([
            'name' => 'CCU Consultant',
            'username' => 'icu_doc',
            'email' => 'icudoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');

        $this->nurse = User::query()->create([
            'name' => 'CCU Nurse',
            'username' => 'icu_nurse',
            'email' => 'icunurse@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->nurse->assignRole('nurse');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000860',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'CCU Monitored Patient',
            'sex' => 'female',
            'date_of_birth' => '1955-12-12',
        ]);

        $this->ccuBed = Bed::query()->where('has_cardiac_monitor', true)->where('status', 'available')->firstOrFail();

        app(IpdAdmissionService::class)->admit([
            'patient_id' => $this->patient->id,
            'bed_id' => $this->ccuBed->id,
            'admission_type' => 'emergency',
            'admission_diagnosis' => 'Cardiogenic shock monitoring',
        ], $this->consultant);
    }

    public function test_bedside_telemetry_stages_as_unverified_raw_and_supports_verify_and_artifact_rejection(): void
    {
        Sanctum::actingAs($this->nurse);

        // 1. Ingest critical SpO2 drop (86%) from bedside monitor
        $res1 = $this->postJson('/api/v1/icu/telemetry/ingest', [
            'bed_number' => $this->ccuBed->bed_number,
            'device_code' => 'PHILIPS-MX700-CCU01',
            'observation_type' => 'spo2',
            'value_numeric' => 86,
            'unit' => '%',
            'data_quality' => 'good',
        ]);

        $res1->assertStatus(202)
            ->assertJsonPath('verification_status', IcuDeviceObservation::STATUS_UNVERIFIED_RAW)
            ->assertJsonPath('is_abnormal', true);

        // 2. Ingest sensor disconnection / motion artifact
        $res2 = $this->postJson('/api/v1/icu/telemetry/ingest', [
            'bed_number' => $this->ccuBed->bed_number,
            'device_code' => 'PHILIPS-MX700-CCU01',
            'observation_type' => 'heart_rate',
            'value_numeric' => 240,
            'unit' => 'bpm',
            'data_quality' => 'sensor_disconnected',
        ]);

        $res2->assertStatus(202)
            ->assertJsonPath('verification_status', IcuDeviceObservation::STATUS_UNVERIFIED_RAW)
            ->assertJsonPath('is_abnormal', true);

        $obs1 = IcuDeviceObservation::query()->where('uuid', $res1->json('uuid'))->firstOrFail();
        $obs2 = IcuDeviceObservation::query()->where('uuid', $res2->json('uuid'))->firstOrFail();

        // Nurse verifies genuine hypoxia reading and rejects disconnected lead artifact
        $this->actingAs($this->nurse)->post(route('icu.observations.verify', $obs1))->assertRedirect();
        $this->actingAs($this->nurse)->post(route('icu.observations.reject', $obs2))->assertRedirect();

        $this->assertSame(IcuDeviceObservation::STATUS_CLINICIAN_VERIFIED, $obs1->fresh()->verification_status);
        $this->assertSame(IcuDeviceObservation::STATUS_REJECTED_ARTIFACT, $obs2->fresh()->verification_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'icu.device.disconnected', 'patient_id' => $this->patient->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'icu.telemetry.verified', 'patient_id' => $this->patient->id]);
    }
}
