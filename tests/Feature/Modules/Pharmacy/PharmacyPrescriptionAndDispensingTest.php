<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pharmacy;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Patient\Models\Patient;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Pharmacy\Models\Prescription;
use App\Modules\Pharmacy\Models\PrescriptionItem;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\PharmacyAndBillingCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PharmacyPrescriptionAndDispensingTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;

    private User $pharmacist;

    private Patient $patient;

    private Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);
        $this->seed(PharmacyAndBillingCatalogSeeder::class);

        $facility = Facility::query()->firstOrFail();
        $opdDept = Department::query()->where('code', 'CARD-OPD')->firstOrFail();

        $this->consultant = User::query()->create([
            'name' => 'Dr. Prescriber',
            'username' => 'rx_doc',
            'email' => 'rxdoc@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->consultant->assignRole('consultant');
        StaffProfile::query()->create([
            'user_id' => $this->consultant->id,
            'facility_id' => $facility->id,
            'department_id' => $opdDept->id,
            'employee_code' => 'RX-DOC-1',
            'designation' => 'Consultant Cardiologist',
        ]);

        $this->pharmacist = User::query()->create([
            'name' => 'Chief Pharmacist',
            'username' => 'rx_pharm',
            'email' => 'rxpharm@chg.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->pharmacist->assignRole('pharmacist');

        $this->patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000950',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Pharmacy Allergy Safety Patient',
            'sex' => 'male',
            'date_of_birth' => '1960-02-02',
        ]);

        PatientAllergy::query()->create([
            'patient_id' => $this->patient->id,
            'substance' => 'Aspirin',
            'category' => 'medication',
            'severity' => 'severe',
            'reaction' => 'Bronchospasm',
            'clinical_status' => 'active',
            'recorded_at' => Carbon::now('UTC'),
        ]);

        $this->encounter = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000950',
            'patient_id' => $this->patient->id,
            'department_id' => $opdDept->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'started_at' => Carbon::now('UTC'),
        ]);
    }

    public function test_drug_allergy_blocks_prescribing_until_audited_override_and_pharmacist_dispenses_atomically(): void
    {
        $aspirin = Medication::query()->where('code', 'ASP-75')->firstOrFail();
        $clopidogrel = Medication::query()->where('code', 'CLOP-75')->firstOrFail();
        $initialStock = $clopidogrel->stock_quantity;

        // 1. Pharmacist cannot prescribe (separation of clinical duties)
        $this->actingAs($this->pharmacist)
            ->post(route('encounters.prescriptions.store', $this->encounter), [
                'medication_id' => $clopidogrel->id,
                'dose' => '75 mg',
                'route' => 'PO',
                'frequency' => 'OD',
                'duration_days' => 14,
                'quantity_prescribed' => 14,
            ])
            ->assertForbidden();

        // 2. Prescribing Aspirin to Aspirin-allergic patient is BLOCKED without explicit override justification
        $this->actingAs($this->consultant)
            ->post(route('encounters.prescriptions.store', $this->encounter), [
                'medication_id' => $aspirin->id,
                'dose' => '75 mg',
                'route' => 'PO',
                'frequency' => 'OD',
                'duration_days' => 14,
                'quantity_prescribed' => 14,
            ])
            ->assertSessionHasErrors('medication_id');
        $this->assertSame(0, Prescription::query()->count());

        // 3. Prescribing Clopidogrel (safe alternative) succeeds and exposes FHIR R4 MedicationRequest
        $this->actingAs($this->consultant)
            ->post(route('encounters.prescriptions.store', $this->encounter), [
                'medication_id' => $clopidogrel->id,
                'dose' => '75 mg',
                'route' => 'PO',
                'frequency' => 'OD',
                'duration_days' => 14,
                'quantity_prescribed' => 14,
                'instructions' => 'Take after breakfast',
            ])
            ->assertRedirect(route('encounters.show', $this->encounter));

        $rx = Prescription::query()->with('items')->firstOrFail();
        $item = $rx->items->firstOrFail();
        $this->assertMatchesRegularExpression('/^RX-\d{4}-000001$/', $rx->prescription_number);

        Sanctum::actingAs($this->consultant);
        $this->getJson("/api/fhir/r4/MedicationRequest/{$item->uuid}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/fhir+json')
            ->assertJsonPath('resourceType', 'MedicationRequest')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('medicationCodeableConcept.coding.0.code', 'CLOP-75');

        // 4. Consultant cannot dispense (403); Pharmacist dispenses & stock deducts atomically
        $this->actingAs($this->consultant)
            ->post(route('pharmacy.items.dispense', $item))
            ->assertForbidden();

        $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.items.dispense', $item))
            ->assertRedirect();

        $this->assertSame(PrescriptionItem::STATUS_DISPENSED, $item->fresh()->status);
        $this->assertSame(Prescription::STATUS_DISPENSED, $rx->fresh()->status);
        $this->assertSame($initialStock - 14, $clopidogrel->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'medication_id' => $clopidogrel->id,
            'movement_type' => 'dispense',
            'quantity_delta' => -14,
            'balance_after' => $initialStock - 14,
        ]);

        // 5. Insufficient stock guard blocks over-dispensing
        $clopidogrel->update(['stock_quantity' => 5]);
        $this->actingAs($this->consultant)
            ->post(route('encounters.prescriptions.store', $this->encounter), [
                'medication_id' => $clopidogrel->id,
                'dose' => '75 mg',
                'route' => 'PO',
                'frequency' => 'OD',
                'duration_days' => 30,
                'quantity_prescribed' => 30,
            ]);
        $item2 = PrescriptionItem::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->pharmacist)
            ->post(route('pharmacy.items.dispense', $item2))
            ->assertSessionHasErrors('dispense');
        $this->assertSame(5, $clopidogrel->fresh()->stock_quantity);
    }
}
