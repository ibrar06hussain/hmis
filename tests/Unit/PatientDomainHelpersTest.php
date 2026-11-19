<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Fhir\Transformers\FhirPatientTransformer;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\MrnGeneratorService;
use App\Support\CnicNormalizer;
use App\Support\DateTimeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientDomainHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_cnic_normalizer_validates_formats_and_masks(): void
    {
        $this->assertTrue(CnicNormalizer::isValid('71501-1234567-1'));
        $this->assertTrue(CnicNormalizer::isValid('7150112345671'));
        $this->assertFalse(CnicNormalizer::isValid('71501-12345'));
        $this->assertSame('7150112345671', CnicNormalizer::normalize('71501-1234567-1'));
        $this->assertSame('71501-1234567-1', CnicNormalizer::format('7150112345671'));
        $this->assertSame('71501-*******-1', CnicNormalizer::mask('7150112345671'));
    }

    public function test_datetime_helper_converts_utc_to_asia_karachi(): void
    {
        $utc = Carbon::create(2026, 11, 19, 10, 0, 0, 'UTC');
        $formatted = DateTimeHelper::formatDateTime($utc, 'Y-m-d H:i');

        $this->assertSame('2026-11-19 15:00 PKT', $formatted);
    }

    public function test_mrn_generator_is_monotonic_and_non_recyclable(): void
    {
        $generator = app(MrnGeneratorService::class);

        $mrn1 = $generator->generate('CHG', 2026);
        $mrn2 = $generator->generate('CHG', 2026);
        $mrn3 = $generator->generate('CHG', 2027);

        $this->assertSame('CHG-2026-000001', $mrn1);
        $this->assertSame('CHG-2026-000002', $mrn2);
        $this->assertSame('CHG-2027-000001', $mrn3);
    }

    public function test_fhir_patient_transformer_produces_valid_r4_structure(): void
    {
        $patient = Patient::query()->create([
            'mrn' => 'CHG-2026-000099',
            'status' => Patient::STATUS_ACTIVE,
            'full_name' => 'Synthetic Cardiac Patient',
            'date_of_birth' => '1970-03-15',
            'sex' => 'male',
            'cnic_normalized' => '7150112345671',
            'phone_primary' => '0345-1112233',
            'district' => 'Gilgit',
            'province' => 'Gilgit-Baltistan',
        ]);

        $patient->identifiers()->create([
            'system' => 'cnic',
            'value' => '7150112345671',
            'issuer' => 'NADRA',
            'is_primary' => true,
            'verified' => true,
        ]);

        $fhir = app(FhirPatientTransformer::class)->toFhirR4($patient);

        $this->assertSame('Patient', $fhir['resourceType']);
        $this->assertSame($patient->uuid, $fhir['id']);
        $this->assertTrue($fhir['active']);
        $this->assertSame('male', $fhir['gender']);
        $this->assertSame('1970-03-15', $fhir['birthDate']);
        $this->assertSame('CHG-2026-000099', $fhir['identifier'][0]['value']);
        $this->assertSame('7150112345671', $fhir['identifier'][1]['value']);
    }
}
