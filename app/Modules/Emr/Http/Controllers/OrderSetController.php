<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\ClinicalOrderSet;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Ris\Models\ImagingCatalog;
use App\Modules\Terminology\Services\TerminologySearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Per-Doctor Personal Favorite Order Sets & Shared Hospital Cardiology Protocols.
 */
class OrderSetController extends Controller
{
    public function __construct(
        private readonly TerminologySearchService $terminologySearch,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('emr.note.create'), 403);

        $personalSets = ClinicalOrderSet::query()
            ->where('owner_user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();

        $hospitalSets = ClinicalOrderSet::query()
            ->whereNull('owner_user_id')
            ->where('is_shared', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('modules.emr.favorites', [
            'personalSets' => $personalSets,
            'hospitalSets' => $hospitalSets,
            'medications' => Medication::query()->where('is_active', true)->orderBy('generic_name')->get(),
            'labTests' => LabTestCatalog::query()->where('is_active', true)->orderBy('code')->get(),
            'imagingCatalog' => ImagingCatalog::query()->where('is_active', true)->orderBy('code')->get(),
            'terminologyCodes' => $this->terminologySearch->search(null, null, 50),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('emr.note.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:140'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_nyha_class' => ['nullable', Rule::in(['I', 'II', 'III', 'IV'])],
            'default_chest_pain_type' => ['nullable', Rule::in(['none', 'typical_angina', 'atypical_angina', 'non_cardiac', 'acute_acs'])],
            'default_exam_text' => ['nullable', 'string', 'max:1000'],
            'default_assessment_text' => ['nullable', 'string', 'max:1000'],
            'default_plan_text' => ['nullable', 'string', 'max:1000'],
            'default_follow_up' => ['nullable', 'string', 'max:255'],
            'diagnosis_codes' => ['nullable', 'array'],
            'diagnosis_codes.*' => ['string', 'max:32'],
            'lab_test_codes' => ['nullable', 'array'],
            'lab_test_codes.*' => ['string', 'max:32'],
            'imaging_codes' => ['nullable', 'array'],
            'imaging_codes.*' => ['string', 'max:32'],
            'medication_codes' => ['nullable', 'array'],
            'medication_codes.*' => ['string', 'max:32'],
            'default_duration_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $days = (int) ($validated['default_duration_days'] ?? 30);
        $medRows = collect((array) ($validated['medication_codes'] ?? []))
            ->filter()
            ->unique()
            ->map(fn (string $code) => [
                'medication_code' => $code,
                'dose' => '1 tab',
                'route' => 'PO',
                'frequency' => 'OD',
                'duration_days' => $days,
                'quantity' => $days,
                'meal_timing' => 'after_meal',
                'instructions' => '',
            ])
            ->values()
            ->all();

        $set = ClinicalOrderSet::query()->create([
            'name' => trim((string) $validated['name']),
            'category' => ClinicalOrderSet::CATEGORY_PERSONAL_FAVORITE,
            'description' => $validated['description'] ?? null,
            'owner_user_id' => $user->id,
            'is_shared' => false,
            'default_note_type' => 'cardiology_eval',
            'default_nyha_class' => $validated['default_nyha_class'] ?? null,
            'default_chest_pain_type' => $validated['default_chest_pain_type'] ?? null,
            'default_exam_text' => $validated['default_exam_text'] ?? null,
            'default_assessment_text' => $validated['default_assessment_text'] ?? null,
            'default_plan_text' => $validated['default_plan_text'] ?? null,
            'default_follow_up' => $validated['default_follow_up'] ?? null,
            'diagnosis_codes' => array_values((array) ($validated['diagnosis_codes'] ?? [])),
            'medications' => $medRows,
            'lab_test_codes' => array_values((array) ($validated['lab_test_codes'] ?? [])),
            'imaging_codes' => array_values((array) ($validated['imaging_codes'] ?? [])),
            'is_active' => true,
        ]);

        $this->auditLogger->log(
            action: 'emr.order_set.personal_created',
            actor: $user,
            subject: $set,
            newValues: ['name' => $set->name, 'med_count' => count($medRows)],
        );

        return redirect()
            ->route('emr.favorites.index')
            ->with('status', "Personal favorite bundle '{$set->name}' saved — now available as a 1-click button in Rapid 60s Consult.");
    }

    public function destroy(Request $request, ClinicalOrderSet $orderSet): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($orderSet->owner_user_id !== $user->id) {
            abort(403, 'You may only archive your own personal favorite order sets.');
        }

        // Non-destructive deactivation (no hard clinical template deletion)
        $orderSet->is_active = false;
        $orderSet->save();

        $this->auditLogger->log(
            action: 'emr.order_set.personal_archived',
            actor: $user,
            subject: $orderSet,
            newValues: ['name' => $orderSet->name],
        );

        return redirect()
            ->route('emr.favorites.index')
            ->with('warning', "Personal favorite bundle '{$orderSet->name}' archived.");
    }
}
