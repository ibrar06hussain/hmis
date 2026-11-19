<?php

declare(strict_types=1);

namespace App\Modules\Cardiology\Policies;

use App\Models\User;

class CardiacProcedurePolicy
{
    public function viewWorkbench(User $user): bool
    {
        return $user->can('emr.note.create')
            || $user->can('emr.vitals.record')
            || $user->can('patient.view_all_clinical_governance');
    }

    public function schedule(User $user): bool
    {
        return $user->can('emr.diagnosis.record') || $user->can('emr.note.sign');
    }

    public function sign(User $user): bool
    {
        return $user->can('emr.note.sign');
    }
}
