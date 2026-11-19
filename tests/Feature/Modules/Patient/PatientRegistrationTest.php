<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Patient;

use App\Models\User;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    private User $erOfficer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $this->receptionist = User::query()->create([
            'name' => 'Front Desk Clerk',
            'username' => 'rec_clerk',
            'email' => 'rec@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->erOfficer = User::query()->create([
            'name' => 'ER Medical Officer',
            'username' => 'er_mo',
            'email' => 'er@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->erOfficer->assignRole('medical_officer');
    }

    public function test_standard_patient_registration_generates_permanent_mrn_and_audit_log(): void
    {
        $response = $this->actingAs($this->receptionist)->post('/patients', [
            'registration_type' => 'standard',
            'full_name' => 'Karim Ullah Synthetic',
            'father_or_spouse_name' => 'Rehmat Shah',
            'date_of_birth' => '1972-08-10',
            'sex' => 'male',
            'blood_group' => 'A+',
            'cnic' => '71501-4455667-1',
            'phone_primary' => '0345-7778899',
            'district' => 'Gilgit',
            'province' => 'Gilgit-Baltistan',
            'emergency_contact_type' => 'emergency',
            'emergency_contact_name' => 'Sajid Ullah',
            'emergency_contact_relationship' => 'Son',
            'emergency_contact_phone' => '0345-7778800',
        ]);

        $patient = Patient::query()->first();
        $this->assertNotNull($patient);
        $response->assertRedirect(route('patients.show', $patient));

        $this->assertMatchesRegularExpression('/^CHG-\d{4}-000001$/', $patient->mrn);
        $this->assertSame('7150144556671', $patient->cnic_normalized);
        $this->assertSame('verified', $patient->identity_verification_status);
        $this->assertDatabaseHas('patient_identifiers', [
            'patient_id' => $patient->id,
            'system' => 'cnic',
            'value' => '7150144556671',
        ]);
        $this->assertDatabaseHas('patient_contacts', [
            'patient_id' => $patient->id,
            'full_name' => 'Sajid Ullah',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'patient.registered',
            'patient_id' => $patient->id,
            'actor_user_id' => $this->receptionist->id,
        ]);
    }

    public function test_emergency_registration_succeeds_without_cnic_and_flags_unverified(): void
    {
        $response = $this->actingAs($this->erOfficer)->post('/patients', [
            'registration_type' => 'emergency',
            'is_unknown_emergency' => true,
            'full_name' => 'Unknown Male ER-99',
            'estimated_age_years' => 62,
            'sex' => 'male',
            'registration_notes' => 'Acute MI presentation via Rescue 1122',
        ]);

        $patient = Patient::query()->first();
        $this->assertNotNull($patient);
        $response->assertRedirect(route('patients.show', $patient));

        $this->assertNull($patient->cnic_normalized);
        $this->assertTrue($patient->is_unknown_emergency);
        $this->assertTrue($patient->dob_estimated);
        $this->assertSame('unverified', $patient->identity_verification_status);
    }

    public function test_standard_registration_requires_guardian_for_minors_and_valid_cnic(): void
    {
        $response = $this->actingAs($this->receptionist)->post('/patients', [
            'registration_type' => 'standard',
            'full_name' => 'Child Synthetic',
            'father_or_spouse_name' => 'Father Name',
            'date_of_birth' => now()->subYears(8)->toDateString(),
            'sex' => 'female',
            'cnic' => '12345', // invalid length
            'phone_primary' => '0312-9990001',
        ]);

        $response->assertSessionHasErrors(['cnic', 'emergency_contact_name']);
        $this->assertSame(0, Patient::query()->count());
    }

    public function test_mrn_cannot_be_mutated_after_creation(): void
    {
        $patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000500',
            'full_name' => 'Immutable MRN Test',
            'sex' => 'male',
        ]);

        $this->expectException(\LogicException::class);
        $patient->mrn = 'CHG-2026-999999';
        $patient->save();
    }
}
