<?php

declare(strict_types=1);

namespace App\Modules\Hr\Policies;

use App\Models\User;

class DutyRosterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function manage(User $user): bool
    {
        return $user->can('hr.roster.manage');
    }
}
