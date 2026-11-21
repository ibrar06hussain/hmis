<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Emr\Models\ClinicalOrderSet;
use Illuminate\Database\Seeder;

class ClinicalOrderSetSeeder extends Seeder
{
    public function run(): void
    {
        $sets = [
            [
                'code' => 'PROTO-ACS-DAPT',
                'name' => 'Post-PCI / ACS Secondary Prevention (DAPT)',
                'description' => 'Standard dual antiplatelet + high-intensity statin + beta-blocker + SL nitrate follow-up regimen.',
                'default_note_type' => 'follow_up',
                'default_nyha_class' => 'II',
                'default_chest_pain_type' => 'typical_angina',
                'default_exam_text' => 'S1+S2 audible, no murmur, JVP not raised, chest clear bilaterally, no pedal edema.',
                'default_assessment_text' => 'Ischemic heart disease / post-ACS on dual antiplatelet secondary prevention.',
                'default_plan_text' => 'Continue strict DAPT adherence. Target LDL < 55 mg/dL, BP < 130/80 mmHg. Avoid abrupt discontinuation of antiplatelets.',
                'default_follow_up' => 'Cardiology OPD in 4 weeks with Fasting Lipid Profile, RFT, and 12-lead ECG.',
                'diagnosis_codes' => ['I25.1', 'I21.0'],
                'medications' => [
                    ['medication_code' => 'ASP-75', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 30, 'quantity' => 30, 'meal_timing' => 'after_meal', 'instructions' => 'After breakfast'],
                    ['medication_code' => 'CLOP-75', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 30, 'quantity' => 30, 'meal_timing' => 'after_meal', 'instructions' => 'Do not skip'],
                    ['medication_code' => 'ATOR-40', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'HS', 'duration_days' => 30, 'quantity' => 30, 'meal_timing' => 'after_meal', 'instructions' => 'At bedtime'],
                    ['medication_code' => 'BISO-5', 'dose' => '1/2 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 30, 'quantity' => 15, 'meal_timing' => 'before_meal', 'instructions' => 'Morning; hold if HR < 55'],
                    ['medication_code' => 'GTN-05', 'dose' => '1 tab', 'route' => 'SL', 'frequency' => 'PRN', 'duration_days' => 30, 'quantity' => 10, 'meal_timing' => 'anytime', 'instructions' => 'Under tongue for chest pain'],
                ],
                'lab_test_codes' => ['LDL', 'HBA1C', 'CREAT', 'K'],
                'imaging_codes' => ['ECG-12'],
            ],
            [
                'code' => 'PROTO-HTN',
                'name' => 'Hypertension Cardiology Regimen',
                'description' => 'ACE inhibitor + beta-blocker with baseline renal/electrolyte panel & ECG.',
                'default_note_type' => 'cardiology_eval',
                'default_nyha_class' => 'I',
                'default_chest_pain_type' => 'none',
                'default_exam_text' => 'S1+S2 audible, no S3/S4, JVP not raised, lungs clear, peripheral pulses intact.',
                'default_assessment_text' => 'Essential hypertension — optimizing cardioprotective antihypertensive therapy.',
                'default_plan_text' => 'Low-salt DASH diet (<5g/day), daily home BP log, check RFT & Serum K+ after ACEi initiation.',
                'default_follow_up' => 'Follow up in 2 weeks with BP chart and Serum Creatinine / Potassium.',
                'diagnosis_codes' => ['I10'],
                'medications' => [
                    ['medication_code' => 'RAMI-5', 'dose' => '1 cap', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 30, 'quantity' => 30, 'meal_timing' => 'before_meal', 'instructions' => 'Morning'],
                    ['medication_code' => 'BISO-5', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 30, 'quantity' => 30, 'meal_timing' => 'after_meal', 'instructions' => 'Morning'],
                ],
                'lab_test_codes' => ['CREAT', 'K', 'NA', 'LDL'],
                'imaging_codes' => ['ECG-12'],
            ],
            [
                'code' => 'PROTO-HFREF',
                'name' => 'Heart Failure (HFrEF) Guideline Regimen',
                'description' => 'ACEi + beta-blocker + loop diuretic with Echo, CXR & renal-electrolyte monitoring.',
                'default_note_type' => 'cardiology_eval',
                'default_nyha_class' => 'III',
                'default_chest_pain_type' => 'none',
                'default_exam_text' => 'Mild bilateral basal crepitations, trace pedal edema, S1+S2 regular.',
                'default_assessment_text' => 'Heart failure with reduced ejection fraction (HFrEF) — titrating guideline-directed therapy.',
                'default_plan_text' => 'Fluid restriction 1.5 L/day, low sodium diet, daily morning weight monitoring. Watch for worsening orthopnea.',
                'default_follow_up' => 'Cardiology Heart Failure Clinic in 2 weeks with RFT, Serum K+, and TTE Echo.',
                'diagnosis_codes' => ['I50.9'],
                'medications' => [
                    ['medication_code' => 'RAMI-5', 'dose' => '1 cap', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 14, 'quantity' => 14, 'meal_timing' => 'after_meal', 'instructions' => 'Morning'],
                    ['medication_code' => 'BISO-5', 'dose' => '1/2 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 14, 'quantity' => 7, 'meal_timing' => 'after_meal', 'instructions' => 'Morning'],
                    ['medication_code' => 'FURO-40', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 14, 'quantity' => 14, 'meal_timing' => 'before_meal', 'instructions' => 'Morning (diuretic)'],
                ],
                'lab_test_codes' => ['CREAT', 'K', 'NA', 'HB'],
                'imaging_codes' => ['TTE', 'CXR-PA'],
            ],
            [
                'code' => 'PROTO-AFIB',
                'name' => 'Atrial Fibrillation Rate & Anticoagulation',
                'description' => 'Rate control + Warfarin anticoagulation with mandatory INR monitoring & Echo.',
                'default_note_type' => 'cardiology_eval',
                'default_nyha_class' => 'II',
                'default_chest_pain_type' => 'none',
                'default_exam_text' => 'Irregularly irregular pulse, S1 variable intensity, chest clear, no neurological deficit.',
                'default_assessment_text' => 'Atrial fibrillation — rate control and stroke prevention anticoagulation.',
                'default_plan_text' => 'Target resting HR < 110 bpm and INR 2.0–3.0. Counseled on bleeding warning signs.',
                'default_follow_up' => 'Anticoagulation / Cardiology Clinic in 7 days with repeat PT/INR.',
                'diagnosis_codes' => ['I48.9'],
                'medications' => [
                    ['medication_code' => 'BISO-5', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 14, 'quantity' => 14, 'meal_timing' => 'after_meal', 'instructions' => 'Morning'],
                    ['medication_code' => 'WARF-5', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'HS', 'duration_days' => 14, 'quantity' => 14, 'meal_timing' => 'after_meal', 'instructions' => 'Same time every evening; check INR'],
                ],
                'lab_test_codes' => ['INR', 'K', 'CREAT'],
                'imaging_codes' => ['ECG-12', 'TTE'],
            ],
            [
                'code' => 'PROTO-ANGINA-WORKUP',
                'name' => 'New Chest Pain / Angina Rapid Workup',
                'description' => 'Initial anti-anginal protection + STAT/urgent Troponin, Lipids, HbA1c, ECG & TTE Echo.',
                'default_note_type' => 'cardiology_eval',
                'default_nyha_class' => 'II',
                'default_chest_pain_type' => 'typical_angina',
                'default_exam_text' => 'S1+S2+0, JVP not raised, chest clear bilaterally, no chest wall tenderness.',
                'default_assessment_text' => 'Exertional chest discomfort — evaluating for stable ischemic heart disease vs ACS.',
                'default_plan_text' => 'Obtain 12-lead ECG and Troponin-I immediately; risk factor profiling and transthoracic echo.',
                'default_follow_up' => 'Review immediately with ECG & Troponin results; ER immediately if pain at rest > 15 min.',
                'diagnosis_codes' => ['I20.9'],
                'medications' => [
                    ['medication_code' => 'ASP-75', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'OD', 'duration_days' => 14, 'quantity' => 14, 'meal_timing' => 'after_meal', 'instructions' => 'After breakfast'],
                    ['medication_code' => 'ATOR-40', 'dose' => '1 tab', 'route' => 'PO', 'frequency' => 'HS', 'duration_days' => 14, 'quantity' => 14, 'meal_timing' => 'after_meal', 'instructions' => 'At night'],
                    ['medication_code' => 'GTN-05', 'dose' => '1 tab', 'route' => 'SL', 'frequency' => 'PRN', 'duration_days' => 14, 'quantity' => 10, 'meal_timing' => 'anytime', 'instructions' => 'Under tongue for chest pain'],
                ],
                'lab_test_codes' => ['TROP-I', 'LDL', 'HBA1C', 'CREAT'],
                'imaging_codes' => ['ECG-12', 'TTE'],
            ],
        ];

        foreach ($sets as $set) {
            ClinicalOrderSet::query()->updateOrCreate(
                ['code' => $set['code']],
                array_merge($set, [
                    'category' => ClinicalOrderSet::CATEGORY_CARDIAC_PROTOCOL,
                    'owner_user_id' => null,
                    'is_shared' => true,
                    'is_active' => true,
                ])
            );
        }
    }
}
