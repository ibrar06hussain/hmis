<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Devices\Models\AnalyzerDevice;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Organization\Models\Department;
use App\Modules\Ris\Models\ImagingCatalog;
use Illuminate\Database\Seeder;

class LabAndImagingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $tests = [
            // Cardiac Markers & Biochemistry
            ['TROP-I', 'High-Sensitivity Troponin I', '89579-7', 'cardiac_markers', 'serum', 'ng/L', 0.0, 19.8, 0.0, 11.6, null, 50.0, 30],
            ['CK-MB', 'Creatine Kinase-MB (CK-MB)', '13969-1', 'cardiac_markers', 'serum', 'U/L', 0.0, 25.0, 0.0, 25.0, null, 80.0, 45],
            ['K', 'Serum Potassium (K+)', '2823-3', 'biochemistry', 'serum', 'mmol/L', 3.5, 5.1, 3.5, 5.1, 2.8, 6.2, 45],
            ['NA', 'Serum Sodium (Na+)', '2951-2', 'biochemistry', 'serum', 'mmol/L', 136.0, 145.0, 136.0, 145.0, 120.0, 160.0, 45],
            ['CREAT', 'Serum Creatinine', '2160-0', 'biochemistry', 'serum', 'mg/dL', 0.7, 1.3, 0.6, 1.1, null, 5.0, 60],
            ['LDL', 'LDL Cholesterol', '2089-1', 'biochemistry', 'serum', 'mg/dL', 0.0, 100.0, 0.0, 100.0, null, null, 120],
            ['HBA1C', 'Hemoglobin A1c (HbA1c)', '4548-4', 'biochemistry', 'edta_blood', '%', 4.0, 5.6, 4.0, 5.6, null, null, 120],
            // Hematology & Coagulation
            ['HB', 'Hemoglobin (Hb)', '718-7', 'hematology', 'edta_blood', 'g/dL', 13.5, 17.5, 12.0, 15.5, 6.5, 20.0, 45],
            ['PLT', 'Platelet Count', '777-3', 'hematology', 'edta_blood', '10^3/uL', 150.0, 450.0, 150.0, 450.0, 30.0, 1000.0, 45],
            ['INR', 'Prothrombin Time / INR', '6301-6', 'coagulation', 'citrate_plasma', 'ratio', 0.8, 1.2, 0.8, 1.2, null, 5.0, 45],
        ];

        foreach ($tests as [$code, $name, $loinc, $section, $specimen, $unit, $lm, $hm, $lf, $hf, $cl, $ch, $tat]) {
            LabTestCatalog::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'loinc_code' => $loinc,
                    'section' => $section,
                    'specimen_type' => $specimen,
                    'unit' => $unit,
                    'ref_low_male' => $lm,
                    'ref_high_male' => $hm,
                    'ref_low_female' => $lf,
                    'ref_high_female' => $hf,
                    'critical_low' => $cl,
                    'critical_high' => $ch,
                    'turnaround_minutes' => $tat,
                    'is_active' => true,
                ]
            );
        }

        $imaging = [
            ['ECG-12', '12-Lead Electrocardiogram (ECG)', 'ECG', false],
            ['TTE', 'Transthoracic Echocardiogram (TTE)', 'ECHO', false],
            ['CXR-PA', 'Chest X-Ray (PA View)', 'CR', false],
            ['CT-CORO', 'CT Coronary Angiography (Contrast)', 'CT', true],
            ['CARD-MRI', 'Cardiac MRI with Gadolinium Contrast', 'MR', true],
            ['CATH-ANGIO', 'Diagnostic Coronary Angiography (Cath Lab)', 'XA', true],
        ];

        foreach ($imaging as [$code, $name, $modality, $contrast]) {
            ImagingCatalog::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'modality' => $modality,
                    'requires_contrast' => $contrast,
                    'is_active' => true,
                ]
            );
        }

        // Seed synthetic analyzer device in Device Registry (offline LAN ready)
        $lisDept = Department::query()->where('code', 'LIS')->first();
        AnalyzerDevice::query()->updateOrCreate(
            ['device_code' => 'ARCH-I1000'],
            [
                'manufacturer' => 'Abbott Diagnostics (Synthetic Profile)',
                'model' => 'ARCHITECT i1000SR',
                'serial_number' => 'SYN-CHG-2026-01',
                'firmware_version' => '9.40',
                'protocol' => 'hl7_v2',
                'host_or_port' => '10.10.20.41:2575',
                'department_id' => $lisDept?->id,
                'interface_version' => 'HL7v2.5.1',
                'connection_status' => 'offline',
                'test_code_map' => [
                    'TROP' => 'TROP-I',
                    'TROP-I' => 'TROP-I',
                    'CKMB' => 'CK-MB',
                    'K' => 'K',
                    'CREAT' => 'CREAT',
                ],
                'is_active' => true,
            ]
        );
    }
}
