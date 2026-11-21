<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Policies;

use App\Models\User;

class NursingPolicy
{
    public function viewWorkbench(User $user): bool
    {
        return $user->hasAnyRole(['nurse', 'medical_superintendent']);
    }

    public function record(User $user): bool
    {
        return $user->can('emr.vitals.record');
    }
}
