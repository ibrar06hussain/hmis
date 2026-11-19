<?php

declare(strict_types=1);

namespace App\Modules\Ris\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Pacs\Models\ImagingStudy;
use App\Modules\Ris\Services\ImagingOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RisWorkbenchController extends Controller
{
    public function __construct(
        private readonly ImagingOrderService $imagingOrderService,
        private readonly PacsGatewayInterface $pacsGateway,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewWorkbench', ImagingStudy::class);

        $studies = ImagingStudy::query()
            ->with(['patient.activeAllergies', 'encounter', 'catalog', 'orderedBy', 'verifiedBy'])
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        return view('modules.ris.index', [
            'studies' => $studies,
            'pacsGateway' => $this->pacsGateway,
        ]);
    }

    public function storeOrder(Request $request, Encounter $encounter): RedirectResponse
    {
        Gate::authorize('create', ImagingStudy::class);
        Gate::authorize('view', $encounter);

        $validated = $request->validate([
            'imaging_catalog_id' => ['required', 'integer', 'exists:imaging_catalog,id'],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'stat'])],
            'clinical_indication' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $result = $this->imagingOrderService->order(
            encounter: $encounter,
            catalogId: (int) $validated['imaging_catalog_id'],
            priority: (string) $validated['priority'],
            clinicalIndication: $validated['clinical_indication'] ?? null,
            actor: $user,
        );

        $study = $result['study'];

        if ($result['contrast_allergy_warning']) {
            return redirect()
                ->route('encounters.show', $encounter)
                ->with('warning', "CONTRAST ALLERGY ALERT: Imaging order {$study->accession_number} ({$study->procedure_name}) created, but patient has an active IV Contrast Dye allergy! Follow pre-medication / alternative modality protocol.");
        }

        return redirect()
            ->route('encounters.show', $encounter)
            ->with('status', "Imaging order {$study->accession_number} ({$study->procedure_name}) created.");
    }

    public function saveReport(Request $request, ImagingStudy $study): RedirectResponse
    {
        Gate::authorize('report', ImagingStudy::class);

        $verifyNow = $request->boolean('verify_now');
        if ($verifyNow) {
            Gate::authorize('verify', ImagingStudy::class);
        }

        $validated = $request->validate([
            'report_summary' => ['required', 'string', 'min:10', 'max:5000'],
            'study_instance_uid' => ['nullable', 'string', 'max:128'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->imagingOrderService->saveReport(
                study: $study,
                reportSummary: (string) $validated['report_summary'],
                studyInstanceUid: $validated['study_instance_uid'] ?? null,
                verifyNow: $verifyNow,
                actor: $user,
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return back()->with('status', "Radiology report for {$updated->accession_number} saved ({$updated->status}).");
    }

    public function syncPacs(ImagingStudy $study): RedirectResponse
    {
        Gate::authorize('report', ImagingStudy::class);

        if (! $this->pacsGateway->isEnabled()) {
            return back()->with('warning', 'Orthanc PACS gateway is currently disabled (HMIS_PACS_ENABLED=false).');
        }

        $uid = $this->pacsGateway->syncStudyByAccession($study);

        if ($uid === null) {
            return back()->with('warning', "No DICOM study found in Orthanc PACS yet for Accession {$study->accession_number}.");
        }

        return back()->with('status', "Synced DICOM StudyInstanceUID ({$uid}) from Orthanc PACS for {$study->accession_number}.");
    }
}
