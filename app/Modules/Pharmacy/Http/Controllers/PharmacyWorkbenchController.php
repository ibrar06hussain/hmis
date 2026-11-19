<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Pharmacy\Exceptions\MedicationAllergyConflictException;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Pharmacy\Models\Prescription;
use App\Modules\Pharmacy\Models\PrescriptionItem;
use App\Modules\Pharmacy\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PharmacyWorkbenchController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacyService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewWorkbench', Prescription::class);

        $prescriptions = Prescription::query()
            ->with(['patient', 'encounter', 'prescribedBy', 'items.medication', 'items.dispensedBy'])
            ->orderByDesc('prescribed_at')
            ->limit(50)
            ->get();

        $medications = Medication::query()
            ->where('is_active', true)
            ->orderBy('generic_name')
            ->get();

        return view('modules.pharmacy.index', [
            'prescriptions' => $prescriptions,
            'medications' => $medications,
        ]);
    }

    public function storePrescription(Request $request, Encounter $encounter): RedirectResponse
    {
        Gate::authorize('prescribe', Prescription::class);
        Gate::authorize('view', $encounter);

        $validated = $request->validate([
            'medication_id' => ['required', 'integer', 'exists:medications,id'],
            'dose' => ['required', 'string', 'max:64'],
            'route' => ['required', Rule::in(['PO', 'IV', 'SL', 'SC', 'IM'])],
            'frequency' => ['required', Rule::in(['OD', 'BD', 'TDS', 'QID', 'STAT', 'PRN'])],
            'duration_days' => ['required', 'integer', 'min:1', 'max:180'],
            'quantity_prescribed' => ['required', 'integer', 'min:1', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:255'],
            'confirm_allergy_override' => ['nullable', 'boolean'],
            'allergy_override_reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $rx = $this->pharmacyService->prescribe(
                encounter: $encounter,
                data: $validated,
                prescriber: $user,
                confirmAllergyOverride: $request->boolean('confirm_allergy_override'),
                overrideReason: $validated['allergy_override_reason'] ?? null,
            );
        } catch (MedicationAllergyConflictException $e) {
            return back()
                ->withInput()
                ->with('allergy_conflict', $e->getMessage())
                ->withErrors(['medication_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('encounters.show', $encounter)
            ->with($rx->allergy_override_used ? 'warning' : 'status', "Prescription {$rx->prescription_number} issued.");
    }

    public function dispense(Request $request, PrescriptionItem $item): RedirectResponse
    {
        Gate::authorize('dispense', Prescription::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $dispensed = $this->pharmacyService->dispenseItem($item, $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['dispense' => $e->getMessage()]);
        }

        return back()->with('status', "Dispensed {$dispensed->quantity_dispensed} x {$dispensed->medication->generic_name} ({$dispensed->prescription->prescription_number}).");
    }
}
