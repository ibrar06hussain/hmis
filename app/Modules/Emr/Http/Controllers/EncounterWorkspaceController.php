<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Emr\Http\Requests\AmendClinicalNoteRequest;
use App\Modules\Emr\Http\Requests\StoreAllergyRequest;
use App\Modules\Emr\Http\Requests\StoreClinicalNoteRequest;
use App\Modules\Emr\Http\Requests\StoreVitalSignRequest;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Services\AllergyService;
use App\Modules\Emr\Services\ClinicalNoteService;
use App\Modules\Emr\Services\VitalSignService;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EncounterWorkspaceController extends Controller
{
    public function __construct(
        private readonly VitalSignService $vitalSignService,
        private readonly ClinicalNoteService $clinicalNoteService,
        private readonly AllergyService $allergyService,
        private readonly AppointmentService $appointmentService,
    ) {}

    public function show(Encounter $encounter): View
    {
        Gate::authorize('view', $encounter);

        $encounter->load([
            'patient.activeAllergies',
            'department',
            'attendingStaff.user',
            'appointment',
            'vitalSigns.recordedBy',
            'clinicalNotes.authorUser',
            'clinicalNotes.signedBy',
            'clinicalNotes.supersedesNote',
        ]);

        return view('modules.emr.encounter', [
            'encounter' => $encounter,
            'patient' => $encounter->patient,
        ]);
    }

    public function storeVitals(StoreVitalSignRequest $request, Encounter $encounter): RedirectResponse
    {
        Gate::authorize('recordVitals', $encounter);

        /** @var User $user */
        $user = $request->user();
        $vital = $this->vitalSignService->record($encounter, $request->validated(), $user);

        $message = $vital->is_abnormal
            ? 'Vitals recorded with ABNORMAL FLAGS: '.implode(', ', $vital->abnormal_flags ?? [])
            : 'Vital signs recorded successfully.';

        return redirect()
            ->route('encounters.show', $encounter)
            ->with($vital->is_abnormal ? 'warning' : 'status', $message);
    }

    public function storeNote(StoreClinicalNoteRequest $request, Encounter $encounter): RedirectResponse
    {
        Gate::authorize('createNote', $encounter);

        /** @var User $user */
        $user = $request->user();
        $note = $this->clinicalNoteService->createDraft($encounter, $request->validated(), $user);

        return redirect()
            ->route('encounters.show', $encounter)
            ->with('status', "Clinical note draft (Rev {$note->revision_number}) saved.");
    }

    public function updateNote(StoreClinicalNoteRequest $request, Encounter $encounter, ClinicalNote $note): RedirectResponse
    {
        Gate::authorize('createNote', $encounter);

        if ($note->encounter_id !== $encounter->id) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $this->clinicalNoteService->updateDraft($note, $request->validated(), $user);
        } catch (\DomainException $exception) {
            return back()->withErrors(['note' => $exception->getMessage()]);
        }

        return redirect()
            ->route('encounters.show', $encounter)
            ->with('status', 'Draft clinical note updated.');
    }

    public function signNote(Request $request, Encounter $encounter, ClinicalNote $note): RedirectResponse
    {
        Gate::authorize('signNote', $encounter);

        if ($note->encounter_id !== $encounter->id) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $signed = $this->clinicalNoteService->sign($note, $user);
        } catch (\DomainException $exception) {
            return back()->withErrors(['note' => $exception->getMessage()]);
        }

        return redirect()
            ->route('encounters.show', $encounter)
            ->with('status', "Clinical note Rev {$signed->revision_number} digitally signed and locked.");
    }

    public function amendNote(AmendClinicalNoteRequest $request, Encounter $encounter, ClinicalNote $note): RedirectResponse
    {
        Gate::authorize('amendNote', $encounter);

        if ($note->encounter_id !== $encounter->id) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        try {
            $amended = $this->clinicalNoteService->amend(
                signedNote: $note,
                data: $validated,
                reason: (string) $validated['amendment_reason'],
                actor: $user,
            );
        } catch (\DomainException $exception) {
            return back()->withErrors(['note' => $exception->getMessage()]);
        }

        return redirect()
            ->route('encounters.show', $encounter)
            ->with('warning', "Signed amendment Rev {$amended->revision_number} recorded. Original Rev {$note->revision_number} preserved.");
    }

    public function complete(Request $request, Encounter $encounter): RedirectResponse
    {
        Gate::authorize('complete', $encounter);

        /** @var User $user */
        $user = $request->user();
        $this->appointmentService->completeConsultation($encounter, $user);

        return redirect()
            ->route('encounters.show', $encounter)
            ->with('status', "Encounter {$encounter->encounter_number} marked finished.");
    }

    public function storeAllergy(StoreAllergyRequest $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('viewClinicalProfile', $patient);

        /** @var User $user */
        $user = $request->user();
        $allergy = $this->allergyService->record($patient, $request->validated(), $user);

        return back()->with('warning', "Allergy to '{$allergy->substance}' ({$allergy->severity}) recorded on patient safety strip.");
    }
}
