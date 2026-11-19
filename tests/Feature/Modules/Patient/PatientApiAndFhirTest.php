<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Patient;

use App\Models\User;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientApiAndFhirTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionist;

    private User $itAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);

        $this->receptionist = User::query()->create([
            'name' => 'API Receptionist',
            'username' => 'api_rec',
            'email' => 'apirec@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->receptionist->assignRole('receptionist');

        $this->itAdmin = User::query()->create([
            'name' => 'API IT Admin',
            'username' => 'api_it',
            'email' => 'apiit@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->itAdmin->assignRole('it_administrator');
    }

    public function test_api_requires_sanctum_authentication(): void
    {
        $this->getJson('/api/v1/patients')->assertUnauthorized();
        $this->getJson('/api/fhir/r4/Patient/00000000-0000-0000-0000-000000000000')->assertUnauthorized();
    }

    public function test_v1_patient_registration_duplicate_conflict_and_fhir_r4_read(): void
    {
        Sanctum::actingAs($this->receptionist);

        $createResponse = $this->postJson('/api/v1/patients', [
            'registration_type' => 'standard',
            'full_name' => 'FHIR Test Patient',
            'father_or_spouse_name' => 'Mirza Khan',
            'date_of_birth' => '1975-06-20',
            'sex' => 'male',
            'cnic' => '71501-9090909-1',
            'phone_primary' => '0345-9090909',
            'district' => 'Gilgit',
        ]);

        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.uuid');
        $mrn = $createResponse->json('data.mrn');
        $this->assertNotEmpty($uuid);
        $this->assertMatchesRegularExpression('/^CHG-\d{4}-000001$/', $mrn);

        // Duplicate CNIC returns HTTP 409 Conflict with candidate metadata
        $this->postJson('/api/v1/patients', [
            'registration_type' => 'standard',
            'full_name' => 'FHIR Test Patient Duplicate',
            'father_or_spouse_name' => 'Mirza Khan',
            'date_of_birth' => '1975-06-20',
            'sex' => 'male',
            'cnic' => '71501-9090909-1',
            'phone_primary' => '0345-9090909',
        ])->assertStatus(409)
            ->assertJsonPath('has_hard_match', true);

        // FHIR R4 Patient read
        $fhirResponse = $this->getJson("/api/fhir/r4/Patient/{$uuid}");
        $fhirResponse->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'Patient')
            ->assertJsonPath('id', $uuid)
            ->assertJsonPath('active', true)
            ->assertJsonPath('identifier.0.value', $mrn);

        // FHIR 404 returns OperationOutcome
        $this->getJson('/api/fhir/r4/Patient/non-existent-uuid')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'OperationOutcome');

        // IT Admin receives FHIR 403 OperationOutcome
        Sanctum::actingAs($this->itAdmin);
        $this->getJson("/api/fhir/r4/Patient/{$uuid}")
            ->assertForbidden()
            ->assertJsonPath('resourceType', 'OperationOutcome');
    }
}
