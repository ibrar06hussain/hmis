<?php

declare(strict_types=1);

namespace App\Modules\Ris\Policies;

use App\Models\User;

class ImagingStudyPolicy
{
    public function viewWorkbench(User $user): bool
    {
        return $user->can('ris.report.enter')
            || $user->can('ris.report.verify');
    }

    public function create(User $user): bool
    {
        return $user->can('ris.order.create');
    }

    public function report(User $user): bool
    {
        return $user->can('ris.report.enter') || $user->can('ris.report.verify');
    }

    public function verify(User $user): bool
    {
        return $user->can('ris.report.verify');
    }
}
