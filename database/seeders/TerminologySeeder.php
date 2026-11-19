<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Terminology\Models\TerminologyCode;
use Illuminate\Database\Seeder;

/**
 * Curated offline-capable Cardiology & General Medicine ICD-10 / ICD-11 baseline catalogue.
 * Full WHO ICD-10/ICD-11 distributions should be imported via official licensed offline bundles.
 */
class TerminologySeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // ICD-10 (2019 baseline)
            ['ICD-10', '2019', 'I21.0', 'Acute transmural myocardial infarction of anterior wall', 'Diseases of the circulatory system', ['STEMI', 'Anterior STEMI', 'MI', 'Heart Attack', 'ACS'], true],
            ['ICD-10', '2019', 'I21.1', 'Acute transmural myocardial infarction of inferior wall', 'Diseases of the circulatory system', ['Inferior STEMI', 'MI', 'ACS'], true],
            ['ICD-10', '2019', 'I21.4', 'Acute subendocardial myocardial infarction', 'Diseases of the circulatory system', ['NSTEMI', 'Non-STEMI', 'ACS'], true],
            ['ICD-10', '2019', 'I20.0', 'Unstable angina', 'Diseases of the circulatory system', ['UA', 'ACS', 'Pre-infarction angina'], true],
            ['ICD-10', '2019', 'I20.9', 'Angina pectoris, unspecified', 'Diseases of the circulatory system', ['Stable Angina', 'Chest Pain', 'IHD'], true],
            ['ICD-10', '2019', 'I25.1', 'Atherosclerotic heart disease', 'Diseases of the circulatory system', ['CAD', 'IHD', 'Coronary Artery Disease'], true],
            ['ICD-10', '2019', 'I10', 'Essential (primary) hypertension', 'Diseases of the circulatory system', ['HTN', 'High Blood Pressure'], true],
            ['ICD-10', '2019', 'I50.9', 'Heart failure, unspecified', 'Diseases of the circulatory system', ['CHF', 'CCF', 'Heart Failure', 'LVF'], true],
            ['ICD-10', '2019', 'I48.9', 'Atrial fibrillation and atrial flutter, unspecified', 'Diseases of the circulatory system', ['AFib', 'AF', 'Atrial Fibrillation'], true],
            ['ICD-10', '2019', 'I05.0', 'Mitral stenosis', 'Diseases of the circulatory system', ['RHD', 'MS', 'Rheumatic Mitral Stenosis'], true],
            ['ICD-10', '2019', 'I42.0', 'Dilated cardiomyopathy', 'Diseases of the circulatory system', ['DCM', 'Cardiomyopathy'], true],
            ['ICD-10', '2019', 'I44.2', 'Atrioventricular block, complete', 'Diseases of the circulatory system', ['CHB', 'Complete Heart Block', '3rd Degree AV Block'], true],
            ['ICD-10', '2019', 'Q21.0', 'Ventricular septal defect', 'Congenital malformations', ['VSD', 'Pediatric CHD'], true],
            ['ICD-10', '2019', 'E11.9', 'Type 2 diabetes mellitus without complications', 'Endocrine and metabolic diseases', ['DM2', 'T2DM', 'Diabetes'], false],
            ['ICD-10', '2019', 'E78.5', 'Hyperlipidaemia, unspecified', 'Endocrine and metabolic diseases', ['Dyslipidemia', 'High Cholesterol'], false],

            // ICD-11 (2025-01 baseline)
            ['ICD-11', '2025-01', 'BA41', 'Acute myocardial infarction', 'Diseases of the circulatory system', ['MI', 'STEMI', 'NSTEMI', 'Heart Attack'], true],
            ['ICD-11', '2025-01', 'BA40.0', 'Unstable angina', 'Diseases of the circulatory system', ['UA', 'ACS'], true],
            ['ICD-11', '2025-01', 'BA00', 'Essential hypertension', 'Diseases of the circulatory system', ['HTN', 'High Blood Pressure'], true],
            ['ICD-11', '2025-01', 'BD10', 'Congestive heart failure', 'Diseases of the circulatory system', ['CHF', 'CCF', 'Heart Failure'], true],
            ['ICD-11', '2025-01', 'BC81.3', 'Atrial fibrillation', 'Diseases of the circulatory system', ['AFib', 'AF'], true],
            ['ICD-11', '2025-01', 'BA80', 'Coronary atherosclerosis', 'Diseases of the circulatory system', ['CAD', 'IHD'], true],
            ['ICD-11', '2025-01', 'BB60', 'Mitral valve stenosis', 'Diseases of the circulatory system', ['RHD', 'MS'], true],
            ['ICD-11', '2025-01', 'LA88.4', 'Ventricular septal defect', 'Developmental anomalies', ['VSD', 'Pediatric CHD'], true],
        ];

        foreach ($codes as [$system, $version, $code, $display, $chapter, $aliases, $isCore]) {
            TerminologyCode::query()->updateOrCreate(
                [
                    'coding_system' => $system,
                    'terminology_version' => $version,
                    'code' => $code,
                ],
                [
                    'display_name' => $display,
                    'normalized_display' => strtolower($display),
                    'chapter' => $chapter,
                    'aliases' => $aliases,
                    'is_cardiology_core' => $isCore,
                    'is_active' => true,
                ]
            );
        }
    }
}
