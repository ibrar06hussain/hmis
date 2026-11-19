<?php

declare(strict_types=1);

namespace App\Modules\Ipd\Policies;

use App\Models\User;
use App\Modules\Ipd\Models\IpdAdmission;

class IpdAdmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ipd.admit')
            || $user->can('ipd.transfer')
            || $user->can('bed.manage')
            || $user->can('patient.view_all_clinical_governance');
    }

    public function admit(User $user): bool
    {
        return $user->can('ipd.admit');
    }

    public function transfer(User $user, IpdAdmission $admission): bool
    {
        return $user->can('ipd.transfer') && $admission->status === IpdAdmission::STATUS_ADMITTED;
    }

    public function discharge(User $user, IpdAdmission $admission): bool
    {
        return $user->can('ipd.discharge') && $admission->status === IpdAdmission::STATUS_ADMITTED;
    }

    public function manageBed(User $user): bool
    {
        return $user->can('bed.manage');
    }
}
