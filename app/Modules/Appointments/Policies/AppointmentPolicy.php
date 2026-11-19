<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Policies;

use App\Models\User;
use App\Modules\Appointments\Models\Appointment;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('encounter.create_opd')
            || $user->can('patient.view_clinical_profile')
            || $user->can('emr.vitals.record');
    }

    public function create(User $user): bool
    {
        return $user->can('encounter.create_opd');
    }

    public function checkIn(User $user, Appointment $appointment): bool
    {
        return $user->can('encounter.create_opd');
    }

    public function consult(User $user, Appointment $appointment): bool
    {
        return $user->can('emr.note.create') || $user->can('emr.vitals.record');
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can('encounter.create_opd');
    }
}
