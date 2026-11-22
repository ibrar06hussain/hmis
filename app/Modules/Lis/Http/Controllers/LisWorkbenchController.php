<?php

declare(strict_types=1);

namespace App\Modules\Lis\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Devices\Models\AnalyzerDevice;
use App\Modules\Devices\Models\AnalyzerRawMessage;
use App\Modules\Devices\Services\AnalyzerIngestionService;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabOrderItem;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Lis\Models\Specimen;
use App\Modules\Lis\Services\LabOrderService;
use App\Modules\Patient\Models\Patient;
use App\Support\OfflineQrSvg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LisWorkbenchController extends Controller
{
    public function __construct(
        private readonly LabOrderService $labOrderService,
        private readonly AnalyzerIngestionService $analyzerIngestionService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewWorkbench', LabOrder::class);

        $tab = $request->string('tab', 'orders')->toString();
        if (! in_array($tab, ['orders', 'receiving', 'machines', 'catalog'], true)) {
            $tab = 'orders';
        }

        $status = $request->string('status', 'open')->toString();
        $search = trim($request->string('q')->toString());

        $orders = LabOrder::query()
            ->with([
                'patient',
                'encounter',
                'orderedBy',
                'specimens.collectedBy',
                'specimens.receivedBy',
                'items.test',
                'items.specimen',
                'items.verifiedBy',
                'items.criticalAcknowledgedBy',
            ])
            ->when($status === 'open', fn ($q) => $q->whereNotIn('status', [LabOrder::STATUS_VERIFIED, LabOrder::STATUS_CANCELLED]))
            ->when($status === 'booked', fn ($q) => $q->whereIn('status', [LabOrder::STATUS_ORDERED, LabOrder::STATUS_COLLECTED]))
            ->when($status === 'received', fn ($q) => $q->whereIn('status', [LabOrder::STATUS_RECEIVED, LabOrder::STATUS_IN_ANALYSIS, LabOrder::STATUS_PRELIMINARY]))
            ->when($status === 'verified', fn ($q) => $q->where('status', LabOrder::STATUS_VERIFIED))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('order_number', 'like', '%'.$search.'%')
                        ->orWhereHas('patient', fn ($p) => $p->where('mrn', 'like', '%'.$search.'%')->orWhere('full_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('specimens', fn ($s) => $s->where('barcode', 'like', '%'.$search.'%'));
                });
            })
            ->orderByRaw("CASE priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderByDesc('ordered_at')
            ->limit(50)
            ->get();

        $catalogTests = LabTestCatalog::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('panel_code')
            ->orderBy('name')
            ->get();

        $panels = $catalogTests
            ->groupBy(fn (LabTestCatalog $t) => $t->panel_code ?: strtoupper($t->section))
            ->map(fn ($group, $code) => [
                'code' => $code,
                'name' => $group->first()->panel_name ?: ucfirst(str_replace('_', ' ', $group->first()->section)),
                'specimen_type' => $group->first()->specimen_type,
                'test_ids' => $group->pluck('id')->all(),
                'tests' => $group,
            ]);

        $pendingSpecimens = Specimen::query()
            ->with(['labOrder.patient', 'labOrder.items.test', 'collectedBy', 'receivedBy'])
            ->orderByRaw("CASE status WHEN 'pending_collection' THEN 1 WHEN 'collected' THEN 2 WHEN 'received_in_lab' THEN 3 ELSE 4 END")
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $receivedSpecimensForMachine = Specimen::query()
            ->with(['labOrder.patient', 'labOrder.items.test'])
            ->where('status', Specimen::STATUS_RECEIVED)
            ->orderByDesc('received_at')
            ->limit(30)
            ->get();

        $devices = AnalyzerDevice::query()->where('is_active', true)->orderBy('device_code')->get();
        $recentRawMessages = AnalyzerRawMessage::query()
            ->with('device')
            ->orderByDesc('received_at')
            ->limit(25)
            ->get();

        $activePatients = Patient::query()->active()->orderByDesc('id')->limit(100)->get();

        $counts = [
            'booked' => LabOrder::query()->whereIn('status', [LabOrder::STATUS_ORDERED, LabOrder::STATUS_COLLECTED])->count(),
            'in_analysis' => LabOrder::query()->whereIn('status', [LabOrder::STATUS_RECEIVED, LabOrder::STATUS_IN_ANALYSIS, LabOrder::STATUS_PRELIMINARY])->count(),
            'verified_today' => LabOrder::query()->where('status', LabOrder::STATUS_VERIFIED)->count(),
            'catalog_params' => $catalogTests->count(),
            'devices_online' => $devices->where('connection_status', 'online')->count(),
        ];

        $specimenMapForJs = $receivedSpecimensForMachine->map(fn (Specimen $s) => [
            'barcode' => $s->barcode,
            'label' => "{$s->barcode} — {$s->labOrder?->patient?->full_name} ({$s->specimen_type})",
            'test_codes' => $s->labOrder?->items
                ->where('specimen_id', $s->id)
                ->where('status', '!=', LabOrderItem::STATUS_VERIFIED)
                ->map(fn (LabOrderItem $i) => [
                    'code' => $i->test->code,
                    'unit' => $i->unit ?? '',
                    'result_type' => $i->test->result_type,
                    'sample_value' => $i->test->isQualitative()
                        ? ($i->test->normal_text ?? ($i->test->reporting_options[0] ?? 'Negative'))
                        : (string) ($i->ref_high !== null ? round((($i->ref_low ?? 0) + $i->ref_high) / 2, 1) : '12.5'),
                ])
                ->values()
                ->all() ?? [],
        ])->values()->all();

        return view('modules.lis.index', [
            'tab' => $tab,
            'orders' => $orders,
            'panels' => $panels,
            'catalogTests' => $catalogTests,
            'pendingSpecimens' => $pendingSpecimens,
            'receivedSpecimensForMachine' => $receivedSpecimensForMachine,
            'specimenMapForJs' => $specimenMapForJs,
            'devices' => $devices,
            'recentRawMessages' => $recentRawMessages,
            'activePatients' => $activePatients,
            'counts' => $counts,
            'statusFilter' => $status,
            'search' => $search,
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

    /**
     * Direct Lab Test Booking at the LIS Reception / Bench Desk (by panel or individual parameter).
     */
    public function bookDirectOrder(Request $request): RedirectResponse
    {
        Gate::authorize('create', LabOrder::class);

        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'test_ids' => ['required', 'array', 'min:1'],
            'test_ids.*' => ['integer', 'exists:lab_test_catalog,id'],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'stat'])],
            'clinical_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $patient = Patient::query()->findOrFail((int) $validated['patient_id']);
        $latestEncounter = $patient->encounters()->whereIn('status', ['arrived', 'in_progress'])->orderByDesc('started_at')->first();

        /** @var User $user */
        $user = $request->user();

        $order = $this->labOrderService->createOrderForPatient(
            patient: $patient,
            testIds: array_map('intval', $validated['test_ids']),
            priority: (string) $validated['priority'],
            clinicalNotes: $validated['clinical_notes'] ?? null,
            actor: $user,
            encounter: $latestEncounter,
        );

        return redirect()
            ->route('lis.index', ['tab' => 'orders', 'status' => 'open'])
            ->with('status', "Booked Lab Order {$order->order_number} for {$patient->full_name} ({$order->items->count()} parameter(s), {$order->specimens->count()} barcode tube(s)).");
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

    public function scanReceiveSpecimen(Request $request): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'min:4', 'max:40'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $received = $this->labOrderService->receiveSpecimenByBarcode((string) $validated['barcode'], $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['barcode' => $e->getMessage()]);
        }

        return back()->with('status', "Barcode {$received->barcode} scanned & received in laboratory (ready for manual or analyzer results).");
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
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['result_value_numeric'] === null && empty($validated['result_value_text'])) {
            return back()->withErrors(['result' => 'Enter a numeric or textual/option laboratory result value.']);
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
                remarks: $validated['remarks'] ?? null,
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }

        $msg = $updated->is_critical
            ? "CRITICAL VALUE entered for {$updated->test->code}: {$updated->result_value_text} {$updated->unit} ({$updated->abnormal_flag}) — Requires verification & clinician notification!"
            : "Result entered for {$updated->test->code}: {$updated->result_value_text} (pending technical validation & pathologist verification).";

        return back()->with($updated->is_critical ? 'warning' : 'status', $msg);
    }

    public function enterBulkResults(Request $request, LabOrder $order): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        $validated = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.numeric' => ['nullable', 'numeric'],
            'results.*.text' => ['nullable', 'string', 'max:255'],
            'results.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $outcome = $this->labOrderService->enterBulkResults($order, $validated['results'], $user);

        if ($outcome['saved'] === 0) {
            return back()->withErrors(['result' => 'Enter at least one parameter result before saving.']);
        }

        $msg = "Saved {$outcome['saved']} parameter result(s) for {$order->order_number}.";
        if ($outcome['critical_codes'] !== []) {
            $msg .= ' CRITICAL ALERT: '.implode(', ', $outcome['critical_codes']).' — notify clinician immediately!';
        }

        return back()->with($outcome['critical_codes'] !== [] ? 'warning' : 'status', $msg);
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

    public function verifyAll(Request $request, LabOrder $order): RedirectResponse
    {
        Gate::authorize('verifyResult', LabOrder::class);

        /** @var User $user */
        $user = $request->user();
        $count = $this->labOrderService->verifyOrder($order, $user);

        if ($count === 0) {
            return back()->withErrors(['result' => 'No entered/validated parameter results available to verify in this order.']);
        }

        return back()->with('status', "Verified and released {$count} parameter(s) for {$order->order_number}.");
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

    /**
     * Automatic Machine Integration Console Ingestion (HL7 v2 ORU^R01 -> staged results, NEVER auto-verified).
     */
    public function ingestMachineFrame(Request $request): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        $validated = $request->validate([
            'device_code' => ['required', 'string', 'exists:analyzer_devices,device_code'],
            'raw_payload' => ['required', 'string', 'min:10', 'max:20000'],
        ]);

        $device = AnalyzerDevice::query()->where('device_code', $validated['device_code'])->where('is_active', true)->firstOrFail();
        $result = $this->analyzerIngestionService->ingest($device, (string) $validated['raw_payload']);

        if ($result['status'] === 'staged') {
            return redirect()
                ->route('lis.index', ['tab' => 'machines'])
                ->with('status', "Machine {$device->device_code} frame ingested: {$result['staged_count']} parameter(s) automatically staged for verification.")
                ->with('analyzer_ack', $result['ack']);
        }

        if ($result['status'] === 'duplicate') {
            return redirect()
                ->route('lis.index', ['tab' => 'machines'])
                ->with('warning', "Duplicate frame from {$device->device_code} ignored (SHA-256 duplicate protection).")
                ->with('analyzer_ack', $result['ack']);
        }

        return redirect()
            ->route('lis.index', ['tab' => 'machines'])
            ->withErrors(['raw_payload' => "Analyzer frame rejected ({$result['status']}): {$result['ack']}"])
            ->with('analyzer_ack', $result['ack']);
    }

    /**
     * Add a new Laboratory Test Parameter & Reporting Options to the offline catalog.
     */
    public function storeCatalogTest(Request $request): RedirectResponse
    {
        Gate::authorize('enterResult', LabOrder::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:32', 'alpha_dash', 'unique:lab_test_catalog,code'],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'loinc_code' => ['nullable', 'string', 'max:32'],
            'section' => ['required', Rule::in(['cardiac_markers', 'hematology', 'biochemistry', 'coagulation', 'serology', 'urinalysis', 'blood_bank', 'microbiology'])],
            'panel_code' => ['nullable', 'string', 'max:32'],
            'panel_name' => ['nullable', 'string', 'max:120'],
            'specimen_type' => ['required', Rule::in(['serum', 'edta_blood', 'citrate_plasma', 'urine', 'whole_blood', 'swab'])],
            'result_type' => ['required', Rule::in(['numeric', 'select', 'positive_negative', 'text'])],
            'reporting_options_raw' => ['nullable', 'string', 'max:1000'],
            'normal_text' => ['nullable', 'string', 'max:80'],
            'method_name' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:32'],
            'ref_low_male' => ['nullable', 'numeric'],
            'ref_high_male' => ['nullable', 'numeric'],
            'ref_low_female' => ['nullable', 'numeric'],
            'ref_high_female' => ['nullable', 'numeric'],
            'critical_low' => ['nullable', 'numeric'],
            'critical_high' => ['nullable', 'numeric'],
            'turnaround_minutes' => ['nullable', 'integer', 'min:5', 'max:4320'],
            'price_pkr' => ['nullable', 'numeric', 'min:0'],
        ]);

        $options = null;
        if (! empty($validated['reporting_options_raw'])) {
            $parsedOptions = array_values(array_filter(array_map(
                'trim',
                preg_split('/[\r\n,]+/', (string) $validated['reporting_options_raw']) ?: []
            )));
            if ($parsedOptions !== []) {
                $options = $parsedOptions;
            }
        } elseif ($validated['result_type'] === 'positive_negative') {
            $options = ['Non-Reactive (Negative)', 'Reactive (Positive)', 'Borderline / Repeat'];
        }

        $test = LabTestCatalog::query()->create([
            'code' => strtoupper(trim((string) $validated['code'])),
            'name' => trim((string) $validated['name']),
            'loinc_code' => $validated['loinc_code'] ?? null,
            'section' => (string) $validated['section'],
            'panel_code' => ! empty($validated['panel_code']) ? strtoupper(trim((string) $validated['panel_code'])) : strtoupper((string) $validated['section']),
            'panel_name' => ! empty($validated['panel_name']) ? trim((string) $validated['panel_name']) : ucfirst(str_replace('_', ' ', (string) $validated['section'])),
            'specimen_type' => (string) $validated['specimen_type'],
            'result_type' => (string) $validated['result_type'],
            'reporting_options' => $options,
            'normal_text' => $validated['normal_text'] ?? null,
            'method_name' => $validated['method_name'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'ref_low_male' => $validated['ref_low_male'] ?? null,
            'ref_high_male' => $validated['ref_high_male'] ?? null,
            'ref_low_female' => $validated['ref_low_female'] ?? null,
            'ref_high_female' => $validated['ref_high_female'] ?? null,
            'critical_low' => $validated['critical_low'] ?? null,
            'critical_high' => $validated['critical_high'] ?? null,
            'turnaround_minutes' => (int) ($validated['turnaround_minutes'] ?? 60),
            'price_pkr' => (float) ($validated['price_pkr'] ?? 0),
            'display_order' => ((int) LabTestCatalog::query()->max('display_order')) + 1,
            'is_active' => true,
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->auditLogger->log(
            action: 'lis.catalog.parameter_added',
            actor: $user,
            subject: $test,
            newValues: [
                'code' => $test->code,
                'panel_code' => $test->panel_code,
                'result_type' => $test->result_type,
                'reporting_options' => $test->reporting_options,
            ],
        );

        return redirect()
            ->route('lis.index', ['tab' => 'catalog'])
            ->with('status', "Added laboratory parameter {$test->code} ({$test->name}) with reporting configuration.");
    }

    /**
     * Printable Official Bilingual Laboratory Report.
     */
    public function printReport(Request $request, LabOrder $order): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->can('viewWorkbench', LabOrder::class) || $user->can('viewClinicalProfile', $order->patient),
            403
        );

        $order->load([
            'patient',
            'orderedBy',
            'verifiedBy',
            'specimens',
            'items.test',
            'items.specimen',
            'items.verifiedBy',
        ]);

        $groupedItems = $order->items->groupBy(fn (LabOrderItem $item) => $item->test->panel_name ?: ucfirst(str_replace('_', ' ', $item->test->section)));

        return view('modules.lis.report', [
            'order' => $order,
            'patient' => $order->patient,
            'groupedItems' => $groupedItems,
            'qrSvg' => OfflineQrSvg::render(
                sprintf('CHG-LAB|%s|%s|%s', $order->order_number, $order->patient->mrn, strtoupper($order->status)),
                96,
                1
            ),
        ]);
    }
}
