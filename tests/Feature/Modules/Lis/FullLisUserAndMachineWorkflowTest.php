<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Lis;

use App\Models\User;
use App\Modules\Devices\Models\AnalyzerDevice;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabOrderItem;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Lis\Models\Specimen;
use App\Modules\Patient\Models\Patient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullLisUserAndMachineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_lis_users_standard_parameters_reporting_options_and_analyzers_exist(): void
    {
        $labTech = User::query()->where('username', 'lab_tech')->first();
        $pathologist = User::query()->where('username', 'pathologist')->first();

        $this->assertNotNull($labTech);
        $this->assertNotNull($pathologist);
        $this->assertTrue($labTech->hasRole('lab_technician'));
        $this->assertTrue($pathologist->hasRole('pathologist'));

        // All 55+ standard hospital parameters & reporting options seeded across 11 panels
        $this->assertGreaterThanOrEqual(55, LabTestCatalog::query()->count());
        $hbsag = LabTestCatalog::query()->where('code', 'HBSAG')->firstOrFail();
        $this->assertSame('positive_negative', $hbsag->result_type);
        $this->assertContains('Non-Reactive (Negative)', (array) $hbsag->reporting_options);
        $this->assertContains('Reactive (Positive)', (array) $hbsag->reporting_options);

        $abo = LabTestCatalog::query()->where('code', 'ABO-RH')->firstOrFail();
        $this->assertSame('select', $abo->result_type);
        $this->assertContains('B Positive (B+)', (array) $abo->reporting_options);

        // 4 standard LAN analyzers registered
        $this->assertGreaterThanOrEqual(4, AnalyzerDevice::query()->count());
    }

    public function test_lis_end_to_end_booking_barcode_receiving_manual_options_automatic_hl7_and_pathologist_release(): void
    {
        $labTech = User::query()->where('username', 'lab_tech')->firstOrFail();
        $pathologist = User::query()->where('username', 'pathologist')->firstOrFail();
        $patient = Patient::query()->firstOrFail();

        $tropTest = LabTestCatalog::query()->where('code', 'TROP-I')->firstOrFail();
        $hbTest = LabTestCatalog::query()->where('code', 'HB')->firstOrFail();
        $hbsagTest = LabTestCatalog::query()->where('code', 'HBSAG')->firstOrFail();
        $aboTest = LabTestCatalog::query()->where('code', 'ABO-RH')->firstOrFail();

        // 1. LIS User books a multi-specimen panel directly at the LIS desk
        $this->actingAs($labTech)
            ->post(route('lis.orders.store'), [
                'patient_id' => $patient->id,
                'test_ids' => [$tropTest->id, $hbTest->id, $hbsagTest->id, $aboTest->id],
                'priority' => 'stat',
                'clinical_notes' => 'Pre-PCI STAT baseline panel',
            ])
            ->assertRedirect(route('lis.index', ['tab' => 'orders', 'status' => 'open']));

        $order = LabOrder::query()->with(['specimens', 'items.test'])->orderByDesc('id')->firstOrFail();
        $this->assertSame(4, $order->items->count());
        $this->assertSame(2, $order->specimens->count()); // serum + edta_blood

        $serumTube = $order->specimens->firstWhere('specimen_type', 'serum');
        $edtaTube = $order->specimens->firstWhere('specimen_type', 'edta_blood');
        $this->assertNotNull($serumTube);
        $this->assertNotNull($edtaTube);

        // 2. Barcode Scanner Sample Receiving
        $this->actingAs($labTech)
            ->post(route('lis.specimens.scan-receive'), ['barcode' => $serumTube->barcode])
            ->assertRedirect();
        $this->actingAs($labTech)
            ->post(route('lis.specimens.scan-receive'), ['barcode' => $edtaTube->barcode])
            ->assertRedirect();

        $this->assertSame(Specimen::STATUS_RECEIVED, $serumTube->fresh()->status);
        $this->assertSame(Specimen::STATUS_RECEIVED, $edtaTube->fresh()->status);

        // 3. Manual Bulk Result Entry (numeric + qualitative reporting options + remarks)
        $hbItem = $order->items->firstWhere('lab_test_catalog_id', $hbTest->id);
        $hbsagItem = $order->items->firstWhere('lab_test_catalog_id', $hbsagTest->id);
        $aboItem = $order->items->firstWhere('lab_test_catalog_id', $aboTest->id);

        $this->actingAs($labTech)
            ->post(route('lis.orders.results-bulk', $order), [
                'results' => [
                    $hbItem->id => ['numeric' => '14.2', 'remarks' => 'Normal Hb'],
                    $hbsagItem->id => ['text' => 'Reactive (Positive)', 'remarks' => 'Repeat confirmed on second cassette'],
                    $aboItem->id => ['text' => 'B Positive (B+)', 'remarks' => 'Forward & reverse matched'],
                ],
            ])
            ->assertRedirect();

        $hbItem->refresh();
        $hbsagItem->refresh();
        $aboItem->refresh();

        $this->assertSame('manual', $hbItem->result_source);
        $this->assertSame('normal', $hbItem->abnormal_flag);
        $this->assertSame('Reactive (Positive)', $hbsagItem->result_value_text);
        $this->assertSame('abnormal', $hbsagItem->abnormal_flag); // deviated from Non-Reactive normal_text!
        $this->assertSame('B Positive (B+)', $aboItem->result_value_text);

        // 4. Automatic Machine Integration (HL7 v2 ORU^R01 from Abbott ARCHITECT i1000SR)
        $hl7Frame = implode("\n", [
            'MSH|^~\&|ARCH-I1000|LIS|CHG-HMIS|CHG|20261122043500||ORU^R01|CTRL-99001|P|2.5.1',
            "OBR|1|{$serumTube->barcode}||CARDIAC",
            'OBX|1|NM|TROP-I^Troponin I||142.8|ng/L',
        ]);

        $this->actingAs($labTech)
            ->post(route('lis.analyzers.ingest-ui'), [
                'device_code' => 'ARCH-I1000',
                'raw_payload' => $hl7Frame,
            ])
            ->assertRedirect(route('lis.index', ['tab' => 'machines']))
            ->assertSessionHas('analyzer_ack');

        $tropItem = $order->items->firstWhere('lab_test_catalog_id', $tropTest->id)->fresh();
        $this->assertSame('analyzer', $tropItem->result_source);
        $this->assertSame('ARCH-I1000', $tropItem->analyzer_device_code);
        $this->assertTrue($tropItem->is_critical);
        $this->assertSame('critical_high', $tropItem->abnormal_flag);
        // Critical Safety Invariant: machine-staged results NEVER auto-verify!
        $this->assertSame(LabOrderItem::STATUS_RESULT_ENTERED, $tropItem->status);

        // 5. Lab Technician cannot verify (403); Pathologist verifies entire order in 1 click
        $this->actingAs($labTech)
            ->post(route('lis.orders.verify-all', $order))
            ->assertForbidden();

        $this->actingAs($pathologist)
            ->post(route('lis.orders.verify-all', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(LabOrder::STATUS_VERIFIED, $order->status);
        $this->assertSame($pathologist->id, $order->verified_by_user_id);

        // 6. Official Bilingual Printable Laboratory Report renders verified parameters, source tags, and QR SVG
        $this->actingAs($pathologist)
            ->get(route('lis.orders.report', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('کارڈیک ہسپتال گلگت')
            ->assertSee('142.8')
            ->assertSee('ARCH-I1000')
            ->assertSee('Reactive (Positive)')
            ->assertSee('B Positive (B+)')
            ->assertSee('<svg', false);
    }

    public function test_lis_user_can_add_new_test_parameter_with_reporting_options(): void
    {
        $labTech = User::query()->where('username', 'lab_tech')->firstOrFail();

        $this->actingAs($labTech)
            ->post(route('lis.catalog.store'), [
                'code' => 'MP-ICT',
                'name' => 'Malarial Parasite (ICT Antigen)',
                'loinc_code' => '70569-9',
                'section' => 'serology',
                'panel_code' => 'SEROLOGY',
                'panel_name' => 'Pre-Procedure Viral Serology',
                'specimen_type' => 'edta_blood',
                'result_type' => 'select',
                'reporting_options_raw' => 'Not Detected, P. falciparum Positive, P. vivax Positive, Mixed Infection',
                'normal_text' => 'Not Detected',
                'method_name' => 'Immunochromatography',
                'turnaround_minutes' => 30,
                'price_pkr' => 450,
            ])
            ->assertRedirect(route('lis.index', ['tab' => 'catalog']));

        $param = LabTestCatalog::query()->where('code', 'MP-ICT')->firstOrFail();
        $this->assertSame('select', $param->result_type);
        $this->assertCount(4, (array) $param->reporting_options);
        $this->assertContains('P. vivax Positive', (array) $param->reporting_options);
    }
}
