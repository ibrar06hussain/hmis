<?php

declare(strict_types=1);

namespace App\Modules\Emr\Policies;

use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Iam\Services\BreakGlassService;

class EncounterPolicy
{
    public function __construct(
        private readonly BreakGlassService $breakGlassService,
    ) {}

    public function view(User $user, Encounter $encounter): bool
    {
        if (! $user->can('patient.view_clinical_profile')) {
            return false;
        }

        if ($user->can('patient.view_all_clinical_governance')) {
            return true;
        }

        if ($user->departmentId() !== null && $user->departmentId() === $encounter->department_id) {
            return true;
        }

        return $this->breakGlassService->hasActiveGrant($user, $encounter->patient);
    }

    public function recordVitals(User $user, Encounter $encounter): bool
    {
        return $user->can('emr.vitals.record') && $this->view($user, $encounter);
    }

    public function createNote(User $user, Encounter $encounter): bool
    {
        return $user->can('emr.note.create') && $this->view($user, $encounter);
    }

    public function signNote(User $user, Encounter $encounter): bool
    {
        return $user->can('emr.note.sign') && $this->view($user, $encounter);
    }

    public function amendNote(User $user, Encounter $encounter): bool
    {
        return $user->can('emr.note.sign') && $this->view($user, $encounter);
    }

    public function recordDiagnosis(User $user, Encounter $encounter): bool
    {
        return $user->can('emr.diagnosis.record') && $this->view($user, $encounter);
    }

    public function complete(User $user, Encounter $encounter): bool
    {
        return ($user->can('emr.note.sign') || $user->can('emr.note.create')) && $this->view($user, $encounter);
    }
}
