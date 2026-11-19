<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Policies;

use App\Models\User;

class NursingPolicy
{
    public function viewWorkbench(User $user): bool
    {
        return $user->can('emr.vitals.record') || $user->can('patient.view_all_clinical_governance');
    }

    public function record(User $user): bool
    {
        return $user->can('emr.vitals.record');
    }
}
