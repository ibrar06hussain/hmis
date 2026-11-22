<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'patient.view_demographics',
        'patient.register',
        'patient.register_emergency',
        'patient.register_override_duplicate',
        'patient.update_demographics',
        'patient.merge',
        'patient.view_clinical_profile',
        'patient.view_all_clinical_governance',
        'encounter.create_opd',
        'encounter.create_emergency',
        'ipd.admit',
        'ipd.transfer',
        'ipd.discharge',
        'bed.manage',
        'emr.vitals.record',
        'emr.note.create',
        'emr.note.sign',
        'emr.diagnosis.record',
        'lis.order.create',
        'lis.specimen.collect',
        'lis.result.enter',
        'lis.result.verify',
        'lis.critical.acknowledge',
        'ris.order.create',
        'ris.report.enter',
        'ris.report.verify',
        'pharmacy.prescribe',
        'pharmacy.dispense',
        'billing.manage',
        'hr.roster.manage',
        'reporting.view_executive',
        'audit.view_logs',
        'iam.manage_users',
        'iam.break_glass',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'hospital_director' => [
                'patient.view_demographics',
                'hr.roster.manage',
                'reporting.view_executive',
                'audit.view_logs',
            ],
            'medical_superintendent' => [
                'patient.view_demographics',
                'patient.view_clinical_profile',
                'patient.view_all_clinical_governance',
                'patient.merge',
                'hr.roster.manage',
                'reporting.view_executive',
                'audit.view_logs',
            ],
            'administrator' => [
                'patient.view_demographics',
                'patient.register',
                'patient.register_emergency',
                'patient.register_override_duplicate',
                'patient.update_demographics',
                'patient.merge',
                'billing.manage',
                'hr.roster.manage',
                'reporting.view_executive',
                'iam.manage_users',
                'audit.view_logs',
            ],
            'consultant' => [
                'patient.view_demographics',
                'patient.view_clinical_profile',
                'encounter.create_opd',
                'encounter.create_emergency',
                'ipd.admit',
                'ipd.transfer',
                'ipd.discharge',
                'bed.manage',
                'emr.vitals.record',
                'emr.note.create',
                'emr.note.sign',
                'emr.diagnosis.record',
                'lis.order.create',
                'lis.critical.acknowledge',
                'ris.order.create',
                'pharmacy.prescribe',
                'iam.break_glass',
            ],
            'medical_officer' => [
                'patient.view_demographics',
                'patient.register_emergency',
                'patient.view_clinical_profile',
                'encounter.create_opd',
                'encounter.create_emergency',
                'ipd.admit',
                'ipd.transfer',
                'emr.vitals.record',
                'emr.note.create',
                'emr.diagnosis.record',
                'lis.order.create',
                'lis.critical.acknowledge',
                'ris.order.create',
                'pharmacy.prescribe',
                'iam.break_glass',
            ],
            'nurse' => [
                'patient.view_demographics',
                'patient.register_emergency',
                'patient.view_clinical_profile',
                'encounter.create_emergency',
                'ipd.transfer',
                'bed.manage',
                'emr.vitals.record',
                'emr.note.create',
                'lis.specimen.collect',
                'iam.break_glass',
            ],
            'lab_technician' => [
                'patient.view_demographics',
                'lis.order.create',
                'lis.specimen.collect',
                'lis.result.enter',
            ],
            'pathologist' => [
                'patient.view_demographics',
                'lis.order.create',
                'lis.specimen.collect',
                'lis.result.enter',
                'lis.result.verify',
                'lis.critical.acknowledge',
            ],
            'radiologist' => [
                'patient.view_demographics',
                'patient.view_clinical_profile',
                'ris.report.enter',
                'ris.report.verify',
            ],
            'pharmacist' => [
                'patient.view_demographics',
                'pharmacy.dispense',
            ],
            'billing_officer' => [
                'patient.view_demographics',
                'billing.manage',
            ],
            'receptionist' => [
                'patient.view_demographics',
                'patient.register',
                'patient.register_emergency',
                'patient.update_demographics',
                'encounter.create_opd',
                'encounter.create_emergency',
            ],
            'it_administrator' => [
                'iam.manage_users',
                'audit.view_logs',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
