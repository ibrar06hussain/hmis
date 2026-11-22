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
        // Standard Hospital & Cardiac Laboratory Test Catalog with Parameters & Reporting Options
        // Format: [code, name, loinc, section, panel_code, panel_name, specimen, result_type, options, normal_text, method, unit, lm, hm, lf, hf, cl, ch, tat, price]
        $tests = [
            // 1. Cardiac Biomarkers (CARDIAC)
            ['TROP-I', 'High-Sensitivity Troponin I', '89579-7', 'cardiac_markers', 'CARDIAC', 'Cardiac Biomarkers', 'serum', 'numeric', null, null, 'CMIA (Abbott Architect)', 'ng/L', 0.0, 19.8, 0.0, 11.6, null, 50.0, 30, 1500],
            ['CK-MB', 'Creatine Kinase-MB (CK-MB)', '13969-1', 'cardiac_markers', 'CARDIAC', 'Cardiac Biomarkers', 'serum', 'numeric', null, null, 'Immunoinhibition', 'U/L', 0.0, 25.0, 0.0, 25.0, null, 80.0, 45, 900],
            ['NT-PROBNP', 'NT-proBNP (Heart Failure Marker)', '33762-6', 'cardiac_markers', 'CARDIAC', 'Cardiac Biomarkers', 'serum', 'numeric', null, null, 'ECLIA / CMIA', 'pg/mL', 0.0, 125.0, 0.0, 125.0, null, 1800.0, 45, 3200],
            ['LDH', 'Lactate Dehydrogenase (LDH)', '2532-0', 'cardiac_markers', 'CARDIAC', 'Cardiac Biomarkers', 'serum', 'numeric', null, null, 'UV Enzymatic', 'U/L', 140.0, 280.0, 140.0, 280.0, null, null, 60, 500],
            ['HS-CRP', 'High-Sensitivity C-Reactive Protein', '30522-7', 'cardiac_markers', 'CARDIAC', 'Cardiac Biomarkers', 'serum', 'numeric', null, null, 'Immunoturbidimetry', 'mg/L', 0.0, 3.0, 0.0, 3.0, null, null, 60, 1100],
            ['D-DIMER', 'D-Dimer (Quantitative)', '48065-7', 'coagulation', 'CARDIAC', 'Cardiac Biomarkers', 'citrate_plasma', 'numeric', null, null, 'Latex Immunoturbidimetry', 'ng/mL', 0.0, 500.0, 0.0, 500.0, null, 2000.0, 45, 2200],

            // 2. Complete Blood Count (CBC)
            ['HB', 'Hemoglobin (Hb)', '718-7', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'SLS Photometry (Sysmex)', 'g/dL', 13.5, 17.5, 12.0, 15.5, 6.5, 20.0, 45, 150],
            ['RBC', 'Red Blood Cell Count (RBC)', '789-8', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'DC Impedance', '10^6/uL', 4.5, 5.9, 4.1, 5.1, null, null, 45, 50],
            ['HCT', 'Hematocrit (PCV / HCT)', '4544-3', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'Cumulative Pulse Height', '%', 41.0, 53.0, 36.0, 46.0, 20.0, 62.0, 45, 50],
            ['MCV', 'Mean Corpuscular Volume (MCV)', '787-2', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'Calculated', 'fL', 80.0, 100.0, 80.0, 100.0, null, null, 45, 50],
            ['MCH', 'Mean Corpuscular Hemoglobin (MCH)', '785-6', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'Calculated', 'pg', 27.0, 33.0, 27.0, 33.0, null, null, 45, 50],
            ['MCHC', 'MCHC', '786-4', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'Calculated', 'g/dL', 32.0, 36.0, 32.0, 36.0, null, null, 45, 50],
            ['WBC', 'Total Leukocyte Count (WBC / TLC)', '6690-2', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'Flow Cytometry', '10^3/uL', 4.0, 11.0, 4.0, 11.0, 1.5, 30.0, 45, 100],
            ['NEUT', 'Neutrophils (%)', '770-8', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, '5-Part Differential', '%', 40.0, 75.0, 40.0, 75.0, null, null, 45, 50],
            ['LYMPH', 'Lymphocytes (%)', '736-9', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, '5-Part Differential', '%', 20.0, 45.0, 20.0, 45.0, null, null, 45, 50],
            ['MONO', 'Monocytes (%)', '5905-5', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, '5-Part Differential', '%', 2.0, 10.0, 2.0, 10.0, null, null, 45, 50],
            ['EOS', 'Eosinophils (%)', '713-8', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, '5-Part Differential', '%', 1.0, 6.0, 1.0, 6.0, null, null, 45, 50],
            ['PLT', 'Platelet Count', '777-3', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'DC Impedance', '10^3/uL', 150.0, 450.0, 150.0, 450.0, 30.0, 1000.0, 45, 100],
            ['ESR', 'ESR (1st Hour)', '4537-7', 'hematology', 'CBC', 'Complete Blood Count (CBC)', 'edta_blood', 'numeric', null, null, 'Westergren', 'mm/hr', 0.0, 15.0, 0.0, 20.0, null, null, 60, 250],

            // 3. Renal Function Tests & Electrolytes (RFT)
            ['UREA', 'Blood Urea', '3094-0', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'Urease UV', 'mg/dL', 15.0, 45.0, 15.0, 45.0, null, 150.0, 60, 250],
            ['CREAT', 'Serum Creatinine', '2160-0', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'Jaffe Kinetic', 'mg/dL', 0.7, 1.3, 0.6, 1.1, null, 5.0, 60, 250],
            ['URIC', 'Serum Uric Acid', '3084-1', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'Uricase PAP', 'mg/dL', 3.5, 7.2, 2.6, 6.0, null, null, 60, 250],
            ['NA', 'Serum Sodium (Na+)', '2951-2', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'Indirect ISE', 'mmol/L', 136.0, 145.0, 136.0, 145.0, 120.0, 160.0, 45, 300],
            ['K', 'Serum Potassium (K+)', '2823-3', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'Indirect ISE', 'mmol/L', 3.5, 5.1, 3.5, 5.1, 2.8, 6.2, 45, 300],
            ['CL', 'Serum Chloride (Cl-)', '2075-0', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'Indirect ISE', 'mmol/L', 98.0, 107.0, 98.0, 107.0, null, null, 45, 250],
            ['CA', 'Serum Calcium (Total)', '17861-6', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'NM-BAPTA Photometry', 'mg/dL', 8.6, 10.2, 8.6, 10.2, 6.5, 13.0, 60, 300],
            ['MG', 'Serum Magnesium', '19123-9', 'biochemistry', 'RFT', 'Renal Function Tests & Electrolytes (RFT)', 'serum', 'numeric', null, null, 'Xylidyl Blue', 'mg/dL', 1.7, 2.4, 1.7, 2.4, 1.0, 4.5, 60, 350],

            // 4. Liver Function Tests (LFT)
            ['TBIL', 'Total Bilirubin', '1975-2', 'biochemistry', 'LFT', 'Liver Function Tests (LFT)', 'serum', 'numeric', null, null, 'Diazo Colorimetric', 'mg/dL', 0.2, 1.2, 0.2, 1.2, null, 15.0, 60, 250],
            ['DBIL', 'Direct Bilirubin', '1968-7', 'biochemistry', 'LFT', 'Liver Function Tests (LFT)', 'serum', 'numeric', null, null, 'Diazo Direct', 'mg/dL', 0.0, 0.3, 0.0, 0.3, null, null, 60, 200],
            ['ALT', 'ALT (SGPT)', '1742-6', 'biochemistry', 'LFT', 'Liver Function Tests (LFT)', 'serum', 'numeric', null, null, 'IFCC UV Kinetic', 'U/L', 0.0, 41.0, 0.0, 33.0, null, 500.0, 60, 250],
            ['AST', 'AST (SGOT)', '1920-8', 'biochemistry', 'LFT', 'Liver Function Tests (LFT)', 'serum', 'numeric', null, null, 'IFCC UV Kinetic', 'U/L', 0.0, 40.0, 0.0, 32.0, null, 500.0, 60, 250],
            ['ALP', 'Alkaline Phosphatase (ALP)', '6768-6', 'biochemistry', 'LFT', 'Liver Function Tests (LFT)', 'serum', 'numeric', null, null, 'IFCC p-NPP', 'U/L', 40.0, 130.0, 35.0, 105.0, null, null, 60, 250],
            ['ALB', 'Serum Albumin', '1751-7', 'biochemistry', 'LFT', 'Liver Function Tests (LFT)', 'serum', 'numeric', null, null, 'BCG Colorimetric', 'g/dL', 3.5, 5.2, 3.5, 5.2, null, null, 60, 250],
            ['TPROT', 'Total Serum Protein', '2885-2', 'biochemistry', 'LFT', 'Liver Function Tests (LFT)', 'serum', 'numeric', null, null, 'Biuret', 'g/dL', 6.4, 8.3, 6.4, 8.3, null, null, 60, 250],

            // 5. Fasting Lipid Profile (LIPID)
            ['TCHOL', 'Total Cholesterol', '2093-3', 'biochemistry', 'LIPID', 'Fasting Lipid Profile', 'serum', 'numeric', null, null, 'CHOD-PAP', 'mg/dL', 0.0, 200.0, 0.0, 200.0, null, null, 90, 250],
            ['TG', 'Triglycerides', '2571-8', 'biochemistry', 'LIPID', 'Fasting Lipid Profile', 'serum', 'numeric', null, null, 'GPO-PAP', 'mg/dL', 0.0, 150.0, 0.0, 150.0, null, 1000.0, 90, 250],
            ['HDL', 'HDL Cholesterol', '2085-9', 'biochemistry', 'LIPID', 'Fasting Lipid Profile', 'serum', 'numeric', null, null, 'Homogeneous Enzymatic', 'mg/dL', 40.0, 80.0, 50.0, 90.0, null, null, 90, 250],
            ['LDL', 'LDL Cholesterol', '2089-1', 'biochemistry', 'LIPID', 'Fasting Lipid Profile', 'serum', 'numeric', null, null, 'Direct / Friedewald', 'mg/dL', 0.0, 100.0, 0.0, 100.0, null, null, 90, 250],
            ['VLDL', 'VLDL Cholesterol', '13458-5', 'biochemistry', 'LIPID', 'Fasting Lipid Profile', 'serum', 'numeric', null, null, 'Calculated', 'mg/dL', 5.0, 30.0, 5.0, 30.0, null, null, 90, 100],

            // 6. Glycemic / Diabetic Profile (DIAB)
            ['FBS', 'Fasting Blood Glucose (FBS)', '1558-6', 'biochemistry', 'DIAB', 'Diabetic / Glycemic Profile', 'serum', 'numeric', null, null, 'Hexokinase', 'mg/dL', 70.0, 99.0, 70.0, 99.0, 45.0, 450.0, 30, 150],
            ['RBS', 'Random Blood Glucose (RBS)', '2345-7', 'biochemistry', 'DIAB', 'Diabetic / Glycemic Profile', 'serum', 'numeric', null, null, 'Hexokinase', 'mg/dL', 70.0, 140.0, 70.0, 140.0, 45.0, 450.0, 30, 150],
            ['HBA1C', 'Hemoglobin A1c (HbA1c)', '4548-4', 'biochemistry', 'DIAB', 'Diabetic / Glycemic Profile', 'edta_blood', 'numeric', null, null, 'TINIA / HPLC', '%', 4.0, 5.6, 4.0, 5.6, null, null, 120, 1200],

            // 7. Coagulation Profile (COAG)
            ['PT', 'Prothrombin Time (PT)', '5902-2', 'coagulation', 'COAG', 'Coagulation Profile', 'citrate_plasma', 'numeric', null, null, 'Clotting Photo-optical', 'sec', 11.0, 13.5, 11.0, 13.5, null, 35.0, 45, 350],
            ['INR', 'Prothrombin Time / INR', '6301-6', 'coagulation', 'COAG', 'Coagulation Profile', 'citrate_plasma', 'numeric', null, null, 'ISI Normalized', 'ratio', 0.8, 1.2, 0.8, 1.2, null, 5.0, 45, 350],
            ['APTT', 'Activated Partial Thromboplastin Time (APTT)', '3173-2', 'coagulation', 'COAG', 'Coagulation Profile', 'citrate_plasma', 'numeric', null, null, 'Clotting Photo-optical', 'sec', 25.0, 36.0, 25.0, 36.0, null, 100.0, 45, 450],

            // 8. Thyroid Profile (TFT)
            ['TSH', 'Thyroid Stimulating Hormone (TSH)', '3016-3', 'biochemistry', 'TFT', 'Thyroid Function Tests (TFT)', 'serum', 'numeric', null, null, 'CMIA', 'uIU/mL', 0.4, 4.2, 0.4, 4.2, null, null, 120, 900],
            ['FT4', 'Free Thyroxine (FT4)', '3024-7', 'biochemistry', 'TFT', 'Thyroid Function Tests (TFT)', 'serum', 'numeric', null, null, 'CMIA', 'ng/dL', 0.8, 1.8, 0.8, 1.8, null, null, 120, 900],
            ['FT3', 'Free Triiodothyronine (FT3)', '3051-0', 'biochemistry', 'TFT', 'Thyroid Function Tests (TFT)', 'serum', 'numeric', null, null, 'CMIA', 'pg/mL', 2.3, 4.2, 2.3, 4.2, null, null, 120, 900],

            // 9. Blood Grouping (BG — Select Reporting Options)
            ['ABO-RH', 'Blood Group & Rh Factor (ABO/Rh)', '882-1', 'blood_bank', 'BG', 'Blood Grouping & Typing', 'edta_blood', 'select', ['A Positive (A+)', 'A Negative (A-)', 'B Positive (B+)', 'B Negative (B-)', 'AB Positive (AB+)', 'AB Negative (AB-)', 'O Positive (O+)', 'O Negative (O-)'], null, 'Tube / Gel Agglutination', null, null, null, null, null, null, null, 30, 300],

            // 10. Pre-Cath / Pre-Op Viral Serology Screening (SEROLOGY — Qualitative Reporting Options)
            ['HBSAG', 'Hepatitis B Surface Antigen (HBsAg)', '5195-3', 'serology', 'SEROLOGY', 'Pre-Procedure Viral Serology', 'serum', 'positive_negative', ['Non-Reactive (Negative)', 'Reactive (Positive)', 'Borderline / Repeat'], 'Non-Reactive (Negative)', 'CMIA / ICT', null, null, null, null, null, null, null, 45, 600],
            ['ANTI-HCV', 'Hepatitis C Virus Antibody (Anti-HCV)', '16128-1', 'serology', 'SEROLOGY', 'Pre-Procedure Viral Serology', 'serum', 'positive_negative', ['Non-Reactive (Negative)', 'Reactive (Positive)', 'Borderline / Repeat'], 'Non-Reactive (Negative)', 'CMIA / ICT', null, null, null, null, null, null, null, 45, 650],
            ['HIV-12', 'HIV 1 & 2 Ag/Ab Combo Screening', '56888-1', 'serology', 'SEROLOGY', 'Pre-Procedure Viral Serology', 'serum', 'positive_negative', ['Non-Reactive (Negative)', 'Reactive (Positive)', 'Indeterminate / Confirm'], 'Non-Reactive (Negative)', 'CMIA 4th Gen', null, null, null, null, null, null, null, 45, 800],

            // 11. Urine Routine Examination (URINE — Mixed Reporting Options + Numeric)
            ['UR-COLOR', 'Urine Color', '5778-6', 'urinalysis', 'URINE', 'Urine Routine Examination (R/E)', 'urine', 'select', ['Pale Yellow', 'Yellow', 'Amber', 'Colorless', 'Reddish / Smoky'], 'Pale Yellow', 'Visual Inspection', null, null, null, null, null, null, null, 30, 50],
            ['UR-APP', 'Urine Appearance', '5767-9', 'urinalysis', 'URINE', 'Urine Routine Examination (R/E)', 'urine', 'select', ['Clear', 'Slightly Turbid', 'Turbid', 'Cloudy'], 'Clear', 'Visual Inspection', null, null, null, null, null, null, null, 30, 50],
            ['UR-PH', 'Urine pH', '5803-2', 'urinalysis', 'URINE', 'Urine Routine Examination (R/E)', 'urine', 'numeric', null, null, 'Dipstick Strip', 'pH', 4.5, 8.0, 4.5, 8.0, null, null, 30, 50],
            ['UR-PROT', 'Urine Protein (Albumin)', '5804-0', 'urinalysis', 'URINE', 'Urine Routine Examination (R/E)', 'urine', 'select', ['Nil', 'Trace', '+', '++', '+++', '++++'], 'Nil', 'Dipstick Strip', null, null, null, null, null, null, null, 30, 50],
            ['UR-GLU', 'Urine Glucose', '5792-7', 'urinalysis', 'URINE', 'Urine Routine Examination (R/E)', 'urine', 'select', ['Nil', 'Trace', '+', '++', '+++', '++++'], 'Nil', 'Dipstick Strip', null, null, null, null, null, null, null, 30, 50],
            ['UR-PUS', 'Urine Pus Cells (WBC / HPF)', '5821-4', 'urinalysis', 'URINE', 'Urine Routine Examination (R/E)', 'urine', 'select', ['0-2 /HPF', '2-5 /HPF', '5-10 /HPF', '10-20 /HPF', 'Numerous /HPF'], '0-2 /HPF', 'Microscopy 40x', '/HPF', null, null, null, null, null, null, 30, 100],
        ];

        $order = 1;
        foreach ($tests as [$code, $name, $loinc, $section, $panelCode, $panelName, $specimen, $resultType, $options, $normalText, $method, $unit, $lm, $hm, $lf, $hf, $cl, $ch, $tat, $price]) {
            LabTestCatalog::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'loinc_code' => $loinc,
                    'section' => $section,
                    'panel_code' => $panelCode,
                    'panel_name' => $panelName,
                    'specimen_type' => $specimen,
                    'result_type' => $resultType,
                    'reporting_options' => $options,
                    'normal_text' => $normalText,
                    'method_name' => $method,
                    'unit' => $unit,
                    'ref_low_male' => $lm,
                    'ref_high_male' => $hm,
                    'ref_low_female' => $lf,
                    'ref_high_female' => $hf,
                    'critical_low' => $cl,
                    'critical_high' => $ch,
                    'turnaround_minutes' => $tat,
                    'price_pkr' => $price,
                    'display_order' => $order++,
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

        // Seed 4 Hospital Laboratory Analyzers in Device Registry (Offline LAN HL7 v2 / ASTM ready)
        $lisDept = Department::query()->where('code', 'LIS')->first();

        $devices = [
            [
                'device_code' => 'ARCH-I1000',
                'manufacturer' => 'Abbott Diagnostics',
                'model' => 'ARCHITECT i1000SR (Immunoassay / Cardiac)',
                'serial_number' => 'SYN-CHG-2026-01',
                'firmware_version' => '9.40',
                'protocol' => 'hl7_v2',
                'host_or_port' => '10.10.20.41:2575',
                'interface_version' => 'HL7v2.5.1',
                'test_code_map' => [
                    'TROP' => 'TROP-I',
                    'TROP-I' => 'TROP-I',
                    'CKMB' => 'CK-MB',
                    'CK-MB' => 'CK-MB',
                    'NTPROBNP' => 'NT-PROBNP',
                    'NT-PROBNP' => 'NT-PROBNP',
                    'TSH' => 'TSH',
                    'FT4' => 'FT4',
                    'FT3' => 'FT3',
                    'HBSAG' => 'HBSAG',
                    'HCV' => 'ANTI-HCV',
                    'ANTI-HCV' => 'ANTI-HCV',
                    'HIV' => 'HIV-12',
                    'K' => 'K',
                    'CREAT' => 'CREAT',
                ],
            ],
            [
                'device_code' => 'COBAS-C311',
                'manufacturer' => 'Roche Diagnostics',
                'model' => 'Cobas c311 (Clinical Chemistry)',
                'serial_number' => 'SYN-CHG-2026-02',
                'firmware_version' => '05-08',
                'protocol' => 'hl7_v2',
                'host_or_port' => '10.10.20.42:2575',
                'interface_version' => 'HL7v2.5.1',
                'test_code_map' => [
                    'UREA' => 'UREA',
                    'CREAT' => 'CREAT',
                    'NA' => 'NA',
                    'K' => 'K',
                    'CL' => 'CL',
                    'TBIL' => 'TBIL',
                    'DBIL' => 'DBIL',
                    'ALT' => 'ALT',
                    'SGPT' => 'ALT',
                    'AST' => 'AST',
                    'SGOT' => 'AST',
                    'ALP' => 'ALP',
                    'ALB' => 'ALB',
                    'TCHOL' => 'TCHOL',
                    'TG' => 'TG',
                    'HDL' => 'HDL',
                    'LDL' => 'LDL',
                    'FBS' => 'FBS',
                    'RBS' => 'RBS',
                    'HBA1C' => 'HBA1C',
                    'HS-CRP' => 'HS-CRP',
                ],
            ],
            [
                'device_code' => 'SYSMEX-XN550',
                'manufacturer' => 'Sysmex Corporation',
                'model' => 'XN-550 (5-Part Hematology)',
                'serial_number' => 'SYN-CHG-2026-03',
                'firmware_version' => '00-24',
                'protocol' => 'hl7_v2',
                'host_or_port' => '10.10.20.43:2575',
                'interface_version' => 'HL7v2.5.1',
                'test_code_map' => [
                    'HGB' => 'HB',
                    'HB' => 'HB',
                    'RBC' => 'RBC',
                    'HCT' => 'HCT',
                    'MCV' => 'MCV',
                    'MCH' => 'MCH',
                    'MCHC' => 'MCHC',
                    'WBC' => 'WBC',
                    'NEUT' => 'NEUT',
                    'LYMPH' => 'LYMPH',
                    'MONO' => 'MONO',
                    'EOS' => 'EOS',
                    'PLT' => 'PLT',
                ],
            ],
            [
                'device_code' => 'STAGO-COMPACT',
                'manufacturer' => 'Diagnostica Stago',
                'model' => 'STA Compact Max (Coagulation)',
                'serial_number' => 'SYN-CHG-2026-04',
                'firmware_version' => '4.12',
                'protocol' => 'hl7_v2',
                'host_or_port' => '10.10.20.44:2575',
                'interface_version' => 'HL7v2.5.1',
                'test_code_map' => [
                    'PT' => 'PT',
                    'INR' => 'INR',
                    'APTT' => 'APTT',
                    'DDIMER' => 'D-DIMER',
                    'D-DIMER' => 'D-DIMER',
                ],
            ],
        ];

        foreach ($devices as $dev) {
            AnalyzerDevice::query()->updateOrCreate(
                ['device_code' => $dev['device_code']],
                array_merge($dev, [
                    'department_id' => $lisDept?->id,
                    'connection_status' => 'online',
                    'is_active' => true,
                ])
            );
        }
    }
}
