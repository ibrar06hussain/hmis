<?php

declare(strict_types=1);

namespace App\Modules\Emergency\Policies;

use App\Models\User;

class EmergencyTriagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('encounter.create_emergency')
            || $user->can('patient.view_clinical_profile');
    }

    public function create(User $user): bool
    {
        return $user->can('encounter.create_emergency');
    }

    public function updateDisposition(User $user): bool
    {
        return $user->can('emr.note.create') || $user->can('ipd.admit');
    }
}
