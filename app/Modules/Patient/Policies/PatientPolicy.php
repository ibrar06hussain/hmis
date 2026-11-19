<?php

declare(strict_types=1);

namespace App\Modules\Patient\Policies;

use App\Models\User;
use App\Modules\Iam\Services\BreakGlassService;
use App\Modules\Patient\Models\Patient;

class PatientPolicy
{
    public function __construct(
        private readonly BreakGlassService $breakGlassService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('patient.view_demographics');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->can('patient.view_demographics') || $this->viewClinicalProfile($user, $patient);
    }

    public function create(User $user): bool
    {
        return $user->can('patient.register') || $user->can('patient.register_emergency');
    }

    public function registerEmergency(User $user): bool
    {
        return $user->can('patient.register_emergency') || $user->can('patient.register');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $patient->status !== Patient::STATUS_MERGED && $user->can('patient.update_demographics');
    }

    public function merge(User $user): bool
    {
        return $user->can('patient.merge');
    }

    /**
     * Clinical profile access is NOT globally granted to every staff account.
     * Requires clinical permission AND either:
     *  - clinical governance role (Medical Superintendent), OR
     *  - department match with an active/existing encounter (or patient has no encounters yet for new intake clinicians), OR
     *  - an active, time-boxed Break-Glass emergency grant.
     */
    public function viewClinicalProfile(User $user, Patient $patient): bool
    {
        if (! $user->can('patient.view_clinical_profile')) {
            return false;
        }

        if ($user->can('patient.view_all_clinical_governance')) {
            return true;
        }

        if ($this->breakGlassService->hasActiveGrant($user, $patient)) {
            return true;
        }

        $userDepartmentId = $user->departmentId();
        if ($userDepartmentId === null) {
            return false;
        }

        // If the patient has encounters in the clinician's department, allow access
        $hasDepartmentEncounter = $patient->encounters()
            ->where('department_id', $userDepartmentId)
            ->exists();

        if ($hasDepartmentEncounter) {
            return true;
        }

        // New patient with zero encounters yet: clinicians in OPD/ER can view to start initial intake
        return ! $patient->encounters()->exists();
    }

    public function requestBreakGlass(User $user, Patient $patient): bool
    {
        return $user->can('iam.break_glass') && ! $this->viewClinicalProfile($user, $patient);
    }
}
