<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Services\ClinicalNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 1-Click Consultant Co-Sign Tray for MO / Scribe Drafted Notes.
 * Supports the common Pakistani hospital team model where an MO or junior doctor
 * enters history & orders during the exam while the Consultant verifies & signs in 1 click.
 */
class CoSignTrayController extends Controller
{
    public function __construct(
        private readonly ClinicalNoteService $clinicalNoteService,
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('emr.note.sign'), 403, 'Consultant Co-Sign Tray requires emr.note.sign permission.');

        $pendingDrafts = ClinicalNote::query()
            ->with([
                'patient.activeAllergies',
                'encounter.department',
                'encounter.vitalSigns',
                'encounter.diagnoses',
                'encounter.prescriptions.items.medication',
                'authorUser',
            ])
            ->where('status', ClinicalNote::STATUS_DRAFT)
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return view('modules.emr.cosign-tray', [
            'pendingDrafts' => $pendingDrafts,
        ]);
    }

    public function approve(Request $request, ClinicalNote $note): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('emr.note.sign'), 403);

        $completeEncounter = $request->boolean('complete_encounter', false);
        $printAfter = $request->boolean('print_after', false);

        try {
            $signed = DB::transaction(function () use ($note, $user, $completeEncounter): ClinicalNote {
                $signed = $this->clinicalNoteService->sign($note, $user);

                if ($completeEncounter && $signed->encounter && $signed->encounter->status !== 'finished') {
                    $this->appointmentService->completeConsultation($signed->encounter, $user);
                }

                return $signed;
            });
        } catch (\DomainException $exception) {
            return back()->withErrors(['cosign' => $exception->getMessage()]);
        }

        $msg = "Co-signed & locked note for {$signed->patient->full_name} ({$signed->patient->mrn}).";

        if ($printAfter && $signed->encounter) {
            return redirect()
                ->route('encounters.prescription.print', $signed->encounter)
                ->with('status', $msg);
        }

        return redirect()
            ->route('emr.cosign.index')
            ->with('status', $msg);
    }
}
