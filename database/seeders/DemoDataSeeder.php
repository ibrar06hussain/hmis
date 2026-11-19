<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Emr\Services\ClinicalNoteService;
use App\Modules\Emr\Services\VitalSignService;
use App\Modules\Iam\Models\StaffProfile;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\PatientRegistrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Synthetic demonstration dataset ONLY.
 * Never run with real patient information; guarded for non-production / demo deployments.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! config('hmis.demo_mode')) {
            return;
        }

        $facility = Facility::query()->where('code', config('hmis.facility_code', 'CHG'))->firstOrFail();
        $departments = Department::query()->get()->keyBy('code');

        $demoStaff = [
            ['name' => 'Dr. Salman Karim', 'username' => 'consultant', 'email' => 'consultant@chg.demo.local', 'role' => 'consultant', 'dept' => 'CARD-OPD', 'emp' => 'CHG-DOC-001', 'designation' => 'Consultant Cardiologist', 'license' => 'PMDC-GB-44102'],
            ['name' => 'Dr. Amina Baig', 'username' => 'mo_er', 'email' => 'mo.er@chg.demo.local', 'role' => 'medical_officer', 'dept' => 'CARD-ER', 'emp' => 'CHG-MO-014', 'designation' => 'Medical Officer (ER)', 'license' => 'PMDC-GB-59218'],
            ['name' => 'Zainab Ali', 'username' => 'reception', 'email' => 'reception@chg.demo.local', 'role' => 'receptionist', 'dept' => 'MRO', 'emp' => 'CHG-REC-008', 'designation' => 'Senior Registration Clerk', 'license' => null],
            ['name' => 'Nurse Rozina Shah', 'username' => 'nurse_ccu', 'email' => 'nurse.ccu@chg.demo.local', 'role' => 'nurse', 'dept' => 'CCU', 'emp' => 'CHG-NUR-021', 'designation' => 'Charge Nurse (CCU)', 'license' => 'PNC-GB-10293'],
            ['name' => 'Dr. Farman Ullah', 'username' => 'ms', 'email' => 'ms@chg.demo.local', 'role' => 'medical_superintendent', 'dept' => 'ADMIN-IT', 'emp' => 'CHG-MS-001', 'designation' => 'Medical Superintendent', 'license' => 'PMDC-GB-18201'],
            ['name' => 'Riaz Ahmed (Pharmacist)', 'username' => 'pharmacist', 'email' => 'pharmacy@chg.demo.local', 'role' => 'pharmacist', 'dept' => 'PHARM', 'emp' => 'CHG-PH-001', 'designation' => 'Chief Pharmacist', 'license' => 'PC-GB-3012'],
            ['name' => 'Saeed Khan (Billing)', 'username' => 'billing', 'email' => 'billing@chg.demo.local', 'role' => 'billing_officer', 'dept' => 'BILLING', 'emp' => 'CHG-BIL-001', 'designation' => 'Billing & Accounts Officer', 'license' => null],
            ['name' => 'Tariq Hussain', 'username' => 'admin', 'email' => 'admin@chg.demo.local', 'role' => 'administrator', 'dept' => 'ADMIN-IT', 'emp' => 'CHG-ADM-001', 'designation' => 'Hospital Administrator', 'license' => null],
            ['name' => 'Kamran Abbas', 'username' => 'it_admin', 'email' => 'it@chg.demo.local', 'role' => 'it_administrator', 'dept' => 'ADMIN-IT', 'emp' => 'CHG-IT-001', 'designation' => 'System Administrator', 'license' => null],
        ];

        $users = [];
        foreach ($demoStaff as $staff) {
            $user = User::query()->updateOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'username' => $staff['username'],
                    'password' => Hash::make('DemoHmis@2026!'),
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$staff['role']]);

            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'facility_id' => $facility->id,
                    'department_id' => $departments[$staff['dept']]->id,
                    'employee_code' => $staff['emp'],
                    'designation' => $staff['designation'],
                    'license_number' => $staff['license'],
                    'is_active' => true,
                ]
            );

            $users[$staff['username']] = $user;
        }

        if (Patient::query()->exists()) {
            return;
        }

        /** @var PatientRegistrationService $registration */
        $registration = app(PatientRegistrationService::class);
        $receptionUser = $users['reception'];
        $consultantUser = $users['consultant'];
        $erUser = $users['mo_er'];

        // Synthetic Patient 1: Standard Cardiology OPD patient with Aspirin allergy & Echo study reference
        $p1 = $registration->register([
            'registration_type' => Patient::REG_STANDARD,
            'full_name' => 'Muhammad Iqbal (Synthetic)',
            'father_or_spouse_name' => 'Ghulam Nabi',
            'date_of_birth' => '1964-05-14',
            'sex' => 'male',
            'blood_group' => 'B+',
            'marital_status' => 'married',
            'cnic' => '71501-1000001-1',
            'phone_primary' => '0345-5550101',
            'address_line' => 'Jutial Sector B, Gilgit',
            'city' => 'Gilgit',
            'district' => 'Gilgit',
            'province' => 'Gilgit-Baltistan',
            'emergency_contact_type' => 'emergency',
            'emergency_contact_name' => 'Asif Iqbal',
            'emergency_contact_relationship' => 'Son',
            'emergency_contact_phone' => '0345-5550199',
        ], $receptionUser);

        $enc1 = Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000001',
            'patient_id' => $p1->id,
            'department_id' => $departments['CARD-OPD']->id,
            'attending_staff_id' => $consultantUser->staffProfile->id,
            'class' => 'AMB',
            'status' => 'in_progress',
            'chief_complaint' => 'Exertional substernal chest discomfort for 2 weeks',
            'started_at' => Carbon::now('UTC')->subHours(2),
        ]);

        PatientAllergy::query()->create([
            'patient_id' => $p1->id,
            'substance' => 'Iodinated IV Contrast Dye',
            'category' => 'contrast_dye',
            'severity' => 'severe',
            'reaction' => 'Bronchospasm and urticaria (requires pre-medication protocol prior to angiography)',
            'clinical_status' => 'active',
            'recorded_by_user_id' => $consultantUser->id,
            'recorded_at' => Carbon::now('UTC')->subHours(2),
        ]);

        ImagingStudy::query()->create([
            'patient_id' => $p1->id,
            'encounter_id' => $enc1->id,
            'accession_number' => 'ACC-2026-000001',
            'modality' => 'ECHO',
            'procedure_name' => 'Transthoracic Echocardiogram (TTE)',
            'study_instance_uid' => '1.2.826.0.1.3680043.10.9999.20260001',
            'pacs_server_key' => 'orthanc_pending',
            'status' => 'reported',
            'report_summary' => 'Synthetic report: Mild concentric LVH, LVEF 55%, no regional wall motion abnormality.',
            'study_date' => Carbon::now('UTC')->subHour(),
        ]);

        // Sample vitals and signed cardiology note on synthetic Patient 1's OPD encounter
        app(VitalSignService::class)->record($enc1, [
            'systolic_bp' => 152,
            'diastolic_bp' => 94,
            'heart_rate' => 84,
            'respiratory_rate' => 16,
            'temperature_c' => 36.7,
            'spo2_percent' => 97,
            'weight_kg' => 78.0,
            'height_cm' => 172.0,
            'rhythm_note' => 'Regular',
        ], $consultantUser);

        $note1 = app(ClinicalNoteService::class)->createDraft($enc1, [
            'note_type' => 'cardiology_eval',
            'nyha_class' => 'II',
            'chest_pain_type' => 'typical_angina',
            'chief_complaint' => 'Exertional substernal chest discomfort for 2 weeks',
            'history_of_present_illness' => 'Synthetic history: Retrosternal tightness on walking uphill, relieved within 5 minutes of rest.',
            'physical_examination' => 'S1+S2 audible, no murmur, lungs clear, no pedal edema.',
            'assessment_and_diagnosis' => 'Suspected stable ischemic heart disease; uncontrolled stage 1 hypertension.',
            'treatment_plan' => 'Optimize antihypertensive regimen, lipid profile, HbA1c, and exercise tolerance / CT coronary evaluation (note contrast dye allergy).',
            'ecg_summary' => 'Normal sinus rhythm, LVH by voltage criteria, no acute ST elevation.',
        ], $consultantUser);
        app(ClinicalNoteService::class)->sign($note1, $consultantUser);

        // Synthetic Patient 2: Pediatric cardiac patient with guardian + today's OPD appointment
        $p2 = $registration->register([
            'registration_type' => Patient::REG_STANDARD,
            'full_name' => 'Fatima Batool (Synthetic)',
            'father_or_spouse_name' => 'Shabir Ahmed',
            'date_of_birth' => '2019-09-20',
            'sex' => 'female',
            'blood_group' => 'O+',
            'phone_primary' => '0312-5550202',
            'city' => 'Skardu',
            'district' => 'Skardu',
            'province' => 'Gilgit-Baltistan',
            'emergency_contact_type' => 'guardian',
            'emergency_contact_name' => 'Shabir Ahmed',
            'emergency_contact_relationship' => 'Father',
            'emergency_contact_phone' => '0312-5550202',
            'emergency_contact_cnic' => '71101-2000002-3',
        ], $receptionUser);

        app(AppointmentService::class)->book([
            'patient_id' => $p2->id,
            'department_id' => $departments['CARD-OPD']->id,
            'clinician_staff_id' => $consultantUser->staffProfile->id,
            'appointment_date' => Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->toDateString(),
            'visit_type' => 'follow_up',
            'chief_complaint' => 'Pediatric cardiac murmur follow-up & echo review',
        ], $receptionUser);

        // Synthetic Patient 3: Fast-track Emergency patient without CNIC (unverified identity)
        $p3 = $registration->register([
            'registration_type' => Patient::REG_EMERGENCY,
            'is_unknown_emergency' => true,
            'full_name' => 'Unknown Male ER-01 (Synthetic)',
            'estimated_age_years' => 58,
            'sex' => 'male',
            'district' => 'Gilgit',
            'registration_notes' => 'Brought by Rescue 1122 with acute chest pain; CNIC pending family arrival.',
        ], $erUser);

        Encounter::query()->create([
            'encounter_number' => 'ENC-2026-000002',
            'patient_id' => $p3->id,
            'department_id' => $departments['CARD-ER']->id,
            'attending_staff_id' => $erUser->staffProfile->id,
            'class' => 'EMER',
            'status' => 'in_progress',
            'chief_complaint' => 'Acute central chest pain, diaphoresis (Code STEMI triage)',
            'started_at' => Carbon::now('UTC')->subMinutes(35),
        ]);
    }
}
