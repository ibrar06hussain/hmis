<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Organization\Models\Bed;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Facility;
use App\Modules\Organization\Models\Ward;
use Illuminate\Database\Seeder;

class HospitalOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->updateOrCreate(
            ['code' => (string) config('hmis.facility_code', 'CHG')],
            [
                'name' => 'Cardiac Hospital Gilgit',
                'short_name' => 'CHG',
                'facility_type' => 'tertiary_cardiac_hospital',
                'city' => 'Gilgit',
                'region' => 'Gilgit-Baltistan',
                'country' => 'Pakistan',
                'phone' => '+92-5811-920000',
                'address' => 'Hospital Road, Gilgit, Gilgit-Baltistan, Pakistan',
                'is_active' => true,
            ]
        );

        $departments = [
            ['code' => 'CARD-OPD', 'name' => 'Cardiology Outpatient (OPD)', 'category' => 'clinical', 'is_clinical' => true],
            ['code' => 'CARD-ER', 'name' => 'Cardiac Emergency & Chest Pain Unit', 'category' => 'clinical', 'is_clinical' => true],
            ['code' => 'CCU', 'name' => 'Coronary Care Unit (CCU)', 'category' => 'clinical', 'is_clinical' => true],
            ['code' => 'CARD-SURG', 'name' => 'Cardiac Surgery & OT', 'category' => 'clinical', 'is_clinical' => true],
            ['code' => 'CATH-LAB', 'name' => 'Catheterization Laboratory', 'category' => 'clinical', 'is_clinical' => true],
            ['code' => 'ECHO-ECG', 'name' => 'Non-Invasive Cardiology (Echo & ECG)', 'category' => 'diagnostic', 'is_clinical' => true],
            ['code' => 'LIS', 'name' => 'Pathology & Clinical Laboratory', 'category' => 'diagnostic', 'is_clinical' => false],
            ['code' => 'RIS', 'name' => 'Radiology & Diagnostic Imaging', 'category' => 'diagnostic', 'is_clinical' => false],
            ['code' => 'PHARM', 'name' => 'Hospital Pharmacy', 'category' => 'support', 'is_clinical' => false],
            ['code' => 'MRO', 'name' => 'Medical Records & Registration', 'category' => 'support', 'is_clinical' => false],
            ['code' => 'BILLING', 'name' => 'Billing & Accounts', 'category' => 'admin', 'is_clinical' => false],
            ['code' => 'ADMIN-IT', 'name' => 'Hospital Administration & IT', 'category' => 'admin', 'is_clinical' => false],
        ];

        $departmentMap = [];
        foreach ($departments as $dept) {
            $departmentMap[$dept['code']] = Department::query()->updateOrCreate(
                ['code' => $dept['code']],
                [
                    'facility_id' => $facility->id,
                    'name' => $dept['name'],
                    'category' => $dept['category'],
                    'is_clinical' => $dept['is_clinical'],
                    'is_active' => true,
                ]
            );
        }

        $wards = [
            ['code' => 'WARD-CCU', 'dept' => 'CCU', 'name' => 'Coronary Care Unit (CCU)', 'type' => 'ccu', 'floor' => '1st Floor', 'capacity' => 20, 'monitor' => true, 'vent' => true],
            ['code' => 'WARD-ER', 'dept' => 'CARD-ER', 'name' => 'Cardiac Emergency Observation', 'type' => 'emergency', 'floor' => 'Ground Floor', 'capacity' => 16, 'monitor' => true, 'vent' => false],
            ['code' => 'WARD-MALE', 'dept' => 'CARD-OPD', 'name' => 'Male Cardiology Ward', 'type' => 'general', 'floor' => '2nd Floor', 'capacity' => 36, 'monitor' => false, 'vent' => false],
            ['code' => 'WARD-FEMALE', 'dept' => 'CARD-OPD', 'name' => 'Female Cardiology Ward', 'type' => 'general', 'floor' => '2nd Floor', 'capacity' => 32, 'monitor' => false, 'vent' => false],
            ['code' => 'WARD-CSICU', 'dept' => 'CARD-SURG', 'name' => 'Cardiac Surgery ICU', 'type' => 'icu', 'floor' => '3rd Floor', 'capacity' => 12, 'monitor' => true, 'vent' => true],
        ];

        foreach ($wards as $wardData) {
            $ward = Ward::query()->updateOrCreate(
                ['code' => $wardData['code']],
                [
                    'department_id' => $departmentMap[$wardData['dept']]->id,
                    'name' => $wardData['name'],
                    'ward_type' => $wardData['type'],
                    'floor' => $wardData['floor'],
                    'bed_capacity' => $wardData['capacity'],
                    'is_active' => true,
                ]
            );

            // Seed initial representative beds per ward (idempotent)
            $seedBeds = min(4, $wardData['capacity']);
            for ($i = 1; $i <= $seedBeds; $i++) {
                Bed::query()->firstOrCreate(
                    ['ward_id' => $ward->id, 'bed_number' => sprintf('%s-%02d', $wardData['code'], $i)],
                    [
                        'status' => 'available',
                        'has_cardiac_monitor' => $wardData['monitor'],
                        'has_ventilator' => $wardData['vent'],
                        'is_isolation' => false,
                    ]
                );
            }
        }
    }
}
