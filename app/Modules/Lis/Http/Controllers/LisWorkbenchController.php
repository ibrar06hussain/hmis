<?php

declare(strict_types=1);

namespace App\Modules\Lis\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Devices\Models\AnalyzerDevice;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabOrderItem;
use App\Modules\Lis\Models\Specimen;
use App\Modules\Lis\Services\LabOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LisWorkbenchController extends Controller
{
    public function __construct(
        private readonly LabOrderService $labOrderService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewWorkbench', LabOrder::class);

        $status = $request->string('status', 'open')->toString();

        $orders = LabOrder::query()
            ->with(['patient', 'encounter', 'specimens', 'items.test', 'items.specimen', 'items.verifiedBy', 'items.criticalAcknowledgedBy'])
            ->when($status === 'open', fn ($q) => $q->whereNotIn('status', [LabOrder::STATUS_VERIFIED, LabOrder::STATUS_CANCELLED]))
            ->when($status === 'verified', fn ($q) => $q->where('status', LabOrder::STATUS_VERIFIED))
            ->orderByRaw("CASE priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderByDesc('ordered_at')
            ->limit(50)
            ->get();

        $devices = AnalyzerDevice::query()->where('is_active', true)->orderBy('device_code')->get();

        return view('modules.lis.index', [
            'orders' => $orders,
            'devices' => $devices,
            'statusFilter' => $status,
        ]);
    }

    public function storeOrder(Request $request, Encounter $encounter): RedirectResponse
    {
        Gate::authorize('create', LabOrder::class);
        Gate::authorize('view', $encounter);

        $validated = $request->validate([
            'test_ids' => ['required', 'array', 'min:1'],
            'test_ids.*' => ['integer', 'exists:lab_test_catalog,id'],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'stat'])],
            'clinical_notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $order = $this->labOrderService->createOrder(
            encounter: $encounter,
            testIds: array_map('intval', $validated['test_ids']),
            priority: (string) $validated['priority'],
            clinicalNotes: $validated['clinical_notes'] ?? null,
            actor: $user,
        );

        return redirect()
            ->route('encounters.show', $encounter)
            ->with('status', "Lab order {$order->order_number} created with ".$order->specimens->count().' barcode specimen(s).');
    }

    public function collectSpecimen(Request $request, Specimen $specimen): RedirectResponse
    {
        Gate::authorize('collectSpecimen', LabOrder::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->labOrderService->collectSpecimen($specimen, $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['specimen' => $e->getMessage()]);
        }

        return back()->with('status', "Specimen {$specimen->barcode} marked Collected.");
    }

    public function receiveSpecimen(Request $request, Specimen $specimen): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->labOrderService->receiveSpecimen($specimen, $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['specimen' => $e->getMessage()]);
        }

        return back()->with('status', "Specimen {$specimen->barcode} received in laboratory.");
    }

    public function rejectSpecimen(Request $request, Specimen $specimen): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $replacement = $this->labOrderService->rejectAndReissueSpecimen($specimen, (string) $validated['rejection_reason'], $user);

        return back()->with('warning', "Specimen {$specimen->barcode} rejected. Replacement barcode {$replacement->barcode} issued for recollection.");
    }

    public function enterResult(Request $request, LabOrderItem $item): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        $validated = $request->validate([
            'result_value_numeric' => ['nullable', 'numeric'],
            'result_value_text' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['result_value_numeric'] === null && empty($validated['result_value_text'])) {
            return back()->withErrors(['result' => 'Enter a numeric or textual laboratory result value.']);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->labOrderService->enterResult(
                item: $item,
                numericValue: $validated['result_value_numeric'] !== null ? (float) $validated['result_value_numeric'] : null,
                textValue: $validated['result_value_text'] ?? null,
                actor: $user,
                source: 'manual',
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }

        $msg = $updated->is_critical
            ? "CRITICAL VALUE entered for {$updated->test->code}: {$updated->result_value_text} {$updated->unit} ({$updated->abnormal_flag}) — Requires verification & clinician notification!"
            : "Result entered for {$updated->test->code} (pending technical validation & pathologist verification).";

        return back()->with($updated->is_critical ? 'warning' : 'status', $msg);
    }

    public function techValidate(Request $request, LabOrderItem $item): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->labOrderService->technicalValidate($item, $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }

        return back()->with('status', 'Technical validation completed; awaiting authorized Pathologist verification.');
    }

    public function verify(Request $request, LabOrderItem $item): RedirectResponse
    {
        Gate::authorize('verifyResult', LabOrder::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $verified = $this->labOrderService->verifyItem($item, $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }

        return back()->with('status', "Result {$verified->test->code} verified and released to Unified Patient Profile.");
    }

    public function acknowledgeCritical(Request $request, LabOrderItem $item): RedirectResponse
    {
        Gate::authorize('acknowledgeCritical', LabOrder::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->labOrderService->acknowledgeCritical($item, $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }

        return back()->with('status', 'Critical laboratory value acknowledged and logged.');
    }
}
