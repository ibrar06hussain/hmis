<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Models\User;

class InvoicePolicy
{
    public function manage(User $user): bool
    {
        return $user->can('billing.manage');
    }
}
