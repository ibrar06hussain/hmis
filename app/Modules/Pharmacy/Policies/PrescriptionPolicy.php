<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Policies;

use App\Models\User;

class PrescriptionPolicy
{
    public function viewWorkbench(User $user): bool
    {
        return $user->can('pharmacy.dispense');
    }

    public function prescribe(User $user): bool
    {
        return $user->can('pharmacy.prescribe');
    }

    public function dispense(User $user): bool
    {
        return $user->can('pharmacy.dispense');
    }
}
