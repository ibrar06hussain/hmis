<?php

declare(strict_types=1);

namespace App\Modules\Lis\Policies;

use App\Models\User;

class LabOrderPolicy
{
    public function viewWorkbench(User $user): bool
    {
        return $user->can('lis.specimen.collect')
            || $user->can('lis.result.enter')
            || $user->can('lis.result.verify')
            || $user->can('lis.order.create');
    }

    public function create(User $user): bool
    {
        return $user->can('lis.order.create');
    }

    public function collectSpecimen(User $user): bool
    {
        return $user->can('lis.specimen.collect');
    }

    public function enterResult(User $user): bool
    {
        return $user->can('lis.result.enter');
    }

    public function verifyResult(User $user): bool
    {
        return $user->can('lis.result.verify');
    }

    public function acknowledgeCritical(User $user): bool
    {
        return $user->can('lis.critical.acknowledge');
    }
}
