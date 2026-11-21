<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Emr\Models\ClinicianQuickChip;
use App\Modules\Emr\Services\ClinicianPreferenceService;
use App\Modules\Pharmacy\Models\Medication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-Doctor Personalization Endpoints (Zero-Reload JSON API for Rapid 60s Consult).
 * Persists custom Tap Chips, Diagnosis Shortcuts, Doctor-Added Medicines, and Quick Medicine Favorites.
 */
class ClinicianPreferenceController extends Controller
{
    public function __construct(
        private readonly ClinicianPreferenceService $preferenceService,
    ) {}

    public function storeChip(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('emr.note.create'), 403);

        $validated = $request->validate([
            'category' => ['required', Rule::in(ClinicianQuickChip::CATEGORIES)],
            'label' => ['nullable', 'string', 'max:160'],
            'value' => ['nullable', 'string', 'max:255'],
            'terminology_code_id' => ['nullable', 'integer', 'exists:terminology_codes,id'],
        ]);

        if ($validated['category'] === ClinicianQuickChip::CATEGORY_DIAGNOSIS_SHORTCUT) {
            if (empty($validated['terminology_code_id'])) {
                return response()->json(['message' => 'Select a valid ICD-10/ICD-11 diagnosis code.'], 422);
            }
        } elseif (trim((string) ($validated['label'] ?? '')) === '') {
            return response()->json(['message' => 'Chip label is required.'], 422);
        }

        $this->preferenceService->addChip(
            user: $user,
            category: (string) $validated['category'],
            label: (string) ($validated['label'] ?? ''),
            value: $validated['value'] ?? null,
            terminologyCodeId: isset($validated['terminology_code_id']) ? (int) $validated['terminology_code_id'] : null,
        );

        return response()->json([
            'ok' => true,
            'chips' => $this->preferenceService->chipsForUser($user),
        ], 201);
    }

    public function destroyChip(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('emr.note.create'), 403);

        $validated = $request->validate([
            'category' => ['required', Rule::in(ClinicianQuickChip::CATEGORIES)],
            'label' => ['nullable', 'string', 'max:160'],
            'terminology_code_id' => ['nullable', 'integer'],
        ]);

        $this->preferenceService->removeChip(
            user: $user,
            category: (string) $validated['category'],
            label: (string) ($validated['label'] ?? ''),
            terminologyCodeId: isset($validated['terminology_code_id']) ? (int) $validated['terminology_code_id'] : null,
        );

        return response()->json([
            'ok' => true,
            'chips' => $this->preferenceService->chipsForUser($user),
        ]);
    }

    public function storeMedication(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pharmacy.prescribe'), 403);

        $validated = $request->validate([
            'generic_name' => ['required', 'string', 'min:2', 'max:140'],
            'brand_name' => ['nullable', 'string', 'max:140'],
            'strength' => ['required', 'string', 'min:1', 'max:60'],
            'dosage_form' => ['nullable', Rule::in(['tablet', 'capsule', 'syrup', 'injection', 'sublingual', 'inhaler', 'sachet', 'drops'])],
            'drug_class' => ['nullable', 'string', 'max:100'],
            'default_dose' => ['nullable', 'string', 'max:60'],
            'default_route' => ['nullable', Rule::in(['PO', 'SL', 'IV', 'SC', 'IM'])],
            'default_frequency' => ['nullable', Rule::in(['OD', 'BD', 'TDS', 'QID', 'HS', 'STAT', 'PRN'])],
            'default_duration_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            'default_meal_timing' => ['nullable', Rule::in(['before_meal', 'after_meal', 'with_meal', 'empty_stomach', 'anytime'])],
            'default_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $med = $this->preferenceService->addCustomMedication($user, $validated);

        return response()->json([
            'ok' => true,
            'medication' => [
                'id' => $med->id,
                'generic_name' => $med->generic_name,
                'brand_name' => $med->brand_name,
                'strength' => $med->strength,
                'drug_class' => $med->drug_class,
                'is_hospital_formulary' => $med->is_hospital_formulary,
                'label' => trim($med->generic_name.' '.$med->strength.($med->brand_name ? " ({$med->brand_name})" : '')),
                'search_text' => strtolower(trim("{$med->generic_name} {$med->brand_name} {$med->strength} {$med->drug_class}")),
            ],
            'favorites' => $this->preferenceService->favoriteMedicationsForUser($user),
        ], 201);
    }

    public function pinMedication(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pharmacy.prescribe'), 403);

        $validated = $request->validate([
            'medication_id' => ['required', 'integer', 'exists:medications,id'],
            'default_dose' => ['nullable', 'string', 'max:60'],
            'default_route' => ['nullable', Rule::in(['PO', 'SL', 'IV', 'SC', 'IM'])],
            'default_frequency' => ['nullable', Rule::in(['OD', 'BD', 'TDS', 'QID', 'HS', 'STAT', 'PRN'])],
            'default_duration_days' => ['nullable', 'integer', 'min:1', 'max:180'],
            'default_meal_timing' => ['nullable', Rule::in(['before_meal', 'after_meal', 'with_meal', 'empty_stomach', 'anytime'])],
            'default_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $medication = Medication::query()->where('is_active', true)->findOrFail((int) $validated['medication_id']);
        $this->preferenceService->pinFavoriteMedication($user, $medication, $validated);

        return response()->json([
            'ok' => true,
            'favorites' => $this->preferenceService->favoriteMedicationsForUser($user),
        ]);
    }

    public function unpinMedication(Request $request, Medication $medication): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('pharmacy.prescribe'), 403);

        $this->preferenceService->unpinFavoriteMedication($user, $medication);

        return response()->json([
            'ok' => true,
            'favorites' => $this->preferenceService->favoriteMedicationsForUser($user),
        ]);
    }
}
