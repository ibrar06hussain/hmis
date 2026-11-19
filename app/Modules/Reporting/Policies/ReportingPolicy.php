<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Policies;

use App\Models\User;

class ReportingPolicy
{
    public function viewExecutive(User $user): bool
    {
        return $user->can('reporting.view_executive');
    }
}
