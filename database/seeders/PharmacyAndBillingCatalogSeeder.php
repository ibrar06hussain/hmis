<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Billing\Models\ServiceTariff;
use App\Modules\Pharmacy\Models\Medication;
use Illuminate\Database\Seeder;

class PharmacyAndBillingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $medications = [
            ['ASP-75', 'Aspirin', 'Loprin', 'Antiplatelet / Salicylate', 'tablet', '75 mg', 5.00, 1000, 100, false],
            ['CLOP-75', 'Clopidogrel', 'Lowplat', 'Antiplatelet / P2Y12 Inhibitor', 'tablet', '75 mg', 25.00, 800, 100, false],
            ['ATOR-40', 'Atorvastatin', 'Lipiget', 'Statin', 'tablet', '40 mg', 35.00, 600, 80, false],
            ['BISO-5', 'Bisoprolol', 'Concor', 'Beta-Blocker', 'tablet', '5 mg', 20.00, 500, 80, false],
            ['GTN-05', 'Glyceryl Trinitrate', 'Angised', 'Nitrate', 'sublingual', '0.5 mg', 10.00, 400, 50, false],
            ['RAMI-5', 'Ramipril', 'Tritace', 'ACE Inhibitor', 'capsule', '5 mg', 22.00, 500, 60, false],
            ['FURO-40', 'Furosemide', 'Lasix', 'Loop Diuretic', 'tablet', '40 mg', 8.00, 700, 100, false],
            ['HEP-5K', 'Unfractionated Heparin', 'Heparin', 'Anticoagulant', 'injection', '5000 IU/mL', 250.00, 200, 40, true],
            ['WARF-5', 'Warfarin', 'Coumadin', 'Vitamin K Antagonist Anticoagulant', 'tablet', '5 mg', 15.00, 300, 50, true],
            ['AMIO-200', 'Amiodarone', 'Cordarone', 'Class III Antiarrhythmic', 'tablet', '200 mg', 45.00, 250, 40, true],
        ];

        foreach ($medications as [$code, $generic, $brand, $class, $form, $strength, $price, $stock, $reorder, $highAlert]) {
            Medication::query()->firstOrCreate(
                ['code' => $code],
                [
                    'generic_name' => $generic,
                    'brand_name' => $brand,
                    'drug_class' => $class,
                    'dosage_form' => $form,
                    'strength' => $strength,
                    'unit_price_pkr' => $price,
                    'stock_quantity' => $stock,
                    'reorder_level' => $reorder,
                    'is_high_alert' => $highAlert,
                    'is_active' => true,
                ]
            );
        }

        $tariffs = [
            ['OPD-CARD', 'Cardiology OPD Consultation', 'consultation', 200.00],
            ['ER-TRIAGE', 'Cardiac Emergency Triage & Stabilization', 'consultation', 0.00],
            ['LAB-TROP', 'High-Sensitivity Troponin I', 'lab', 1500.00],
            ['LAB-LIPID', 'Fasting Lipid Profile', 'lab', 900.00],
            ['IMG-ECG', '12-Lead Electrocardiogram (ECG)', 'imaging', 300.00],
            ['IMG-TTE', 'Transthoracic Echocardiogram (TTE)', 'imaging', 2500.00],
            ['IMG-CTCA', 'CT Coronary Angiography', 'imaging', 18000.00],
            ['BED-CCU', 'Coronary Care Unit (CCU) Bed — Per Day', 'bed_day', 3000.00],
            ['BED-GEN', 'Cardiology Ward Bed — Per Day', 'bed_day', 800.00],
            ['PROC-CATH', 'Diagnostic Coronary Angiography', 'procedure', 25000.00],
        ];

        foreach ($tariffs as [$code, $name, $category, $price]) {
            ServiceTariff::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'unit_price_pkr' => $price,
                    'is_active' => true,
                ]
            );
        }
    }
}
