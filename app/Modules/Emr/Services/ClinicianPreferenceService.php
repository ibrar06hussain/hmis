<?php

declare(strict_types=1);

namespace App\Modules\Emr\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\ClinicianFavoriteMedication;
use App\Modules\Emr\Models\ClinicianQuickChip;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Terminology\Models\TerminologyCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Per-Doctor Personalization Engine for Rapid 60-Second Consults.
 *
 * Remembers each consultant's own:
 * 1. Clinical Assessment Tap Chips (Symptoms, Exam Findings, ECG notes) — add custom or hide defaults.
 * 2. 1-Click Diagnosis Shortcuts (ICD-10 / ICD-11) — pin any diagnosis or hide defaults.
 * 3. Doctor-Added Medicines & Quick-Tap Favorite Medicines with saved dosing defaults.
 */
final class ClinicianPreferenceService
{
    /** @var array<string, list<string>> */
    public const DEFAULT_TEXT_CHIPS = [
        ClinicianQuickChip::CATEGORY_SYMPTOM => [
            'Exertional chest pain',
            'Shortness of breath',
            'Palpitations',
            'Orthopnea / PND',
            'Pedal edema',
            'Dizziness / Syncope',
            'Routine cardiac follow-up (asymptomatic)',
        ],
        ClinicianQuickChip::CATEGORY_EXAM => [
            'Bilateral basal crepitations',
            'Raised JVP',
            'Bilateral pedal edema',
            'Irregularly irregular pulse',
            'Ejection systolic murmur',
            'Pansystolic murmur',
        ],
        ClinicianQuickChip::CATEGORY_ECG => [
            'NSR, no acute ST-T changes',
            'LVH by voltage criteria',
            'Atrial Fibrillation',
            'Old Anterior MI (Q waves V1-V4)',
            'ST depression lateral leads',
        ],
    ];

    /** @var list<array{code: string, label: string}> */
    public const DEFAULT_DIAGNOSIS_SHORTCUTS = [
        ['code' => 'I10', 'label' => 'HTN (I10)'],
        ['code' => 'I20.9', 'label' => 'Stable Angina (I20.9)'],
        ['code' => 'I25.1', 'label' => 'IHD / CAD (I25.1)'],
        ['code' => 'I50.9', 'label' => 'Heart Failure (I50.9)'],
        ['code' => 'I48.9', 'label' => 'AFib (I48.9)'],
        ['code' => 'I21.0', 'label' => 'Anterior STEMI (I21.0)'],
        ['code' => 'E11.9', 'label' => 'DM Type 2 (E11.9)'],
        ['code' => 'E78.5', 'label' => 'Dyslipidemia (E78.5)'],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Return merged per-doctor Tap Chips (defaults minus hidden + custom additions) across all categories.
     *
     * @return array{
     *     symptom: list<array{label: string, value: string, is_custom: bool}>,
     *     exam: list<array{label: string, value: string, is_custom: bool}>,
     *     ecg: list<array{label: string, value: string, is_custom: bool}>,
     *     diagnosis_shortcut: list<array{label: string, code: string, terminology_code_id: int, is_custom: bool}>
     * }
     */
    public function chipsForUser(User $user): array
    {
        $rows = ClinicianQuickChip::query()
            ->with('terminologyCode')
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byCategory = $rows->groupBy('category');
        $result = [
            ClinicianQuickChip::CATEGORY_SYMPTOM => [],
            ClinicianQuickChip::CATEGORY_EXAM => [],
            ClinicianQuickChip::CATEGORY_ECG => [],
            ClinicianQuickChip::CATEGORY_DIAGNOSIS_SHORTCUT => [],
        ];

        // 1. Merge text chip categories (symptom, exam, ecg)
        foreach (self::DEFAULT_TEXT_CHIPS as $category => $defaults) {
            /** @var Collection<int, ClinicianQuickChip> $userRows */
            $userRows = $byCategory->get($category, collect());
            $hiddenLabels = $userRows->where('is_hidden', true)->pluck('label')->map(fn ($l) => strtolower((string) $l))->all();

            $merged = [];
            $seen = [];

            foreach ($defaults as $defaultLabel) {
                $norm = strtolower($defaultLabel);
                if (in_array($norm, $hiddenLabels, true)) {
                    continue;
                }
                $merged[] = [
                    'label' => $defaultLabel,
                    'value' => $defaultLabel,
                    'is_custom' => false,
                ];
                $seen[$norm] = true;
            }

            foreach ($userRows->where('is_hidden', false) as $custom) {
                $norm = strtolower($custom->label);
                if (isset($seen[$norm])) {
                    continue;
                }
                $merged[] = [
                    'label' => $custom->label,
                    'value' => $custom->value,
                    'is_custom' => true,
                ];
                $seen[$norm] = true;
            }

            $result[$category] = $merged;
        }

        // 2. Merge 1-click Diagnosis Shortcuts (resolved to active TerminologyCode IDs)
        $termsByCode = TerminologyCode::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        /** @var Collection<int, ClinicianQuickChip> $dxUserRows */
        $dxUserRows = $byCategory->get(ClinicianQuickChip::CATEGORY_DIAGNOSIS_SHORTCUT, collect());
        $hiddenDxTermIds = $dxUserRows->where('is_hidden', true)->pluck('terminology_code_id')->filter()->map(fn ($id) => (int) $id)->all();
        $hiddenDxLabels = $dxUserRows->where('is_hidden', true)->pluck('label')->map(fn ($l) => strtolower((string) $l))->all();

        $dxMerged = [];
        $seenTermIds = [];

        foreach (self::DEFAULT_DIAGNOSIS_SHORTCUTS as $defaultDx) {
            $term = $termsByCode->get($defaultDx['code']);
            if ($term === null) {
                continue;
            }
            if (in_array($term->id, $hiddenDxTermIds, true) || in_array(strtolower($defaultDx['label']), $hiddenDxLabels, true)) {
                continue;
            }
            $dxMerged[] = [
                'label' => $defaultDx['label'],
                'code' => $term->code,
                'terminology_code_id' => $term->id,
                'is_custom' => false,
            ];
            $seenTermIds[$term->id] = true;
        }

        foreach ($dxUserRows->where('is_hidden', false) as $customDx) {
            $term = $customDx->terminologyCode;
            if ($term === null || ! $term->is_active || isset($seenTermIds[$term->id])) {
                continue;
            }
            $dxMerged[] = [
                'label' => $customDx->label,
                'code' => $term->code,
                'terminology_code_id' => $term->id,
                'is_custom' => true,
            ];
            $seenTermIds[$term->id] = true;
        }

        $result[ClinicianQuickChip::CATEGORY_DIAGNOSIS_SHORTCUT] = $dxMerged;

        return $result;
    }

    public function addChip(
        User $user,
        string $category,
        string $label,
        ?string $value = null,
        ?int $terminologyCodeId = null,
    ): ClinicianQuickChip {
        $cleanLabel = trim($label);
        $cleanValue = trim($value ?? $cleanLabel);

        if ($category === ClinicianQuickChip::CATEGORY_DIAGNOSIS_SHORTCUT) {
            $term = TerminologyCode::query()->where('is_active', true)->findOrFail((int) $terminologyCodeId);
            $cleanLabel = $cleanLabel !== '' ? $cleanLabel : "{$term->code} — ".Str::limit($term->display_name, 26);
            $cleanValue = $term->code;

            // Remove any prior hidden tombstone for the same terminology_code_id
            ClinicianQuickChip::query()
                ->where('user_id', $user->id)
                ->where('category', $category)
                ->where('terminology_code_id', $term->id)
                ->where('is_hidden', true)
                ->delete();
        }

        return ClinicianQuickChip::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'category' => $category,
                'label' => $cleanLabel,
            ],
            [
                'value' => $cleanValue,
                'terminology_code_id' => $terminologyCodeId,
                'is_hidden' => false,
            ]
        );
    }

    public function removeChip(
        User $user,
        string $category,
        string $label,
        ?int $terminologyCodeId = null,
    ): void {
        $cleanLabel = trim($label);

        if ($category === ClinicianQuickChip::CATEGORY_DIAGNOSIS_SHORTCUT && $terminologyCodeId !== null) {
            $isDefault = collect(self::DEFAULT_DIAGNOSIS_SHORTCUTS)
                ->contains(function (array $d) use ($terminologyCodeId): bool {
                    $term = TerminologyCode::query()->where('code', $d['code'])->first();

                    return $term !== null && $term->id === $terminologyCodeId;
                });

            if ($isDefault) {
                $term = TerminologyCode::query()->findOrFail($terminologyCodeId);
                ClinicianQuickChip::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'category' => $category,
                        'label' => $cleanLabel !== '' ? $cleanLabel : $term->code,
                    ],
                    [
                        'value' => $term->code,
                        'terminology_code_id' => $term->id,
                        'is_hidden' => true,
                    ]
                );

                return;
            }

            ClinicianQuickChip::query()
                ->where('user_id', $user->id)
                ->where('category', $category)
                ->where('terminology_code_id', $terminologyCodeId)
                ->delete();

            return;
        }

        $defaults = array_map('strtolower', self::DEFAULT_TEXT_CHIPS[$category] ?? []);
        if (in_array(strtolower($cleanLabel), $defaults, true)) {
            ClinicianQuickChip::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category' => $category,
                    'label' => $cleanLabel,
                ],
                [
                    'value' => $cleanLabel,
                    'is_hidden' => true,
                ]
            );

            return;
        }

        ClinicianQuickChip::query()
            ->where('user_id', $user->id)
            ->where('category', $category)
            ->where('label', $cleanLabel)
            ->delete();
    }

    /**
     * All active medicines visible to this doctor: shared hospital formulary + doctor's own added medicines.
     *
     * @return Collection<int, Medication>
     */
    public function medicationsVisibleToUser(User $user): Collection
    {
        return Medication::query()
            ->where('is_active', true)
            ->where(function ($q) use ($user): void {
                $q->where('is_hospital_formulary', true)
                    ->orWhere('added_by_user_id', $user->id);
            })
            ->orderByDesc('added_by_user_id')
            ->orderBy('generic_name')
            ->get();
    }

    /**
     * Doctor's pinned 1-tap Favorite Medicines with saved default dosing.
     *
     * @return list<array<string, mixed>>
     */
    public function favoriteMedicationsForUser(User $user): array
    {
        return ClinicianFavoriteMedication::query()
            ->with('medication')
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->filter(fn (ClinicianFavoriteMedication $fav) => $fav->medication !== null && $fav->medication->is_active)
            ->map(fn (ClinicianFavoriteMedication $fav) => [
                'medication_id' => $fav->medication_id,
                'generic_name' => $fav->medication->generic_name,
                'brand_name' => $fav->medication->brand_name,
                'strength' => $fav->medication->strength,
                'is_hospital_formulary' => $fav->medication->is_hospital_formulary,
                'label' => trim($fav->medication->generic_name.' '.$fav->medication->strength.($fav->medication->brand_name ? " ({$fav->medication->brand_name})" : '')),
                'dose' => $fav->default_dose,
                'route' => $fav->default_route,
                'frequency' => $fav->default_frequency,
                'duration_days' => $fav->default_duration_days,
                'meal_timing' => $fav->default_meal_timing,
                'instructions' => $fav->default_instructions ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * Allow a clinician to add their own preferred/outside-formulary medicine
     * and automatically pin it to their personal Quick Medicines bar.
     *
     * @param  array<string, mixed>  $data
     */
    public function addCustomMedication(User $user, array $data): Medication
    {
        return DB::transaction(function () use ($user, $data): Medication {
            $code = sprintf('DOC-%d-%s', $user->id, strtoupper(Str::random(6)));

            $medication = Medication::query()->create([
                'code' => $code,
                'generic_name' => trim((string) $data['generic_name']),
                'brand_name' => isset($data['brand_name']) && trim((string) $data['brand_name']) !== ''
                    ? trim((string) $data['brand_name'])
                    : null,
                'drug_class' => isset($data['drug_class']) && trim((string) $data['drug_class']) !== ''
                    ? trim((string) $data['drug_class'])
                    : 'Cardiology Custom',
                'dosage_form' => (string) ($data['dosage_form'] ?? 'tablet'),
                'strength' => trim((string) $data['strength']),
                'unit_price_pkr' => 0.00,
                'stock_quantity' => 0,
                'reorder_level' => 0,
                'is_high_alert' => false,
                'is_active' => true,
                'added_by_user_id' => $user->id,
                'is_hospital_formulary' => false,
                'version' => 1,
            ]);

            $this->pinFavoriteMedication($user, $medication, [
                'default_dose' => (string) ($data['default_dose'] ?? '1 tab'),
                'default_route' => (string) ($data['default_route'] ?? 'PO'),
                'default_frequency' => (string) ($data['default_frequency'] ?? 'OD'),
                'default_duration_days' => (int) ($data['default_duration_days'] ?? 30),
                'default_meal_timing' => (string) ($data['default_meal_timing'] ?? 'after_meal'),
                'default_instructions' => $data['default_instructions'] ?? null,
            ]);

            $this->auditLogger->log(
                action: 'emr.preferences.custom_medication_added',
                actor: $user,
                subject: $medication,
                newValues: [
                    'code' => $medication->code,
                    'generic_name' => $medication->generic_name,
                    'brand_name' => $medication->brand_name,
                    'strength' => $medication->strength,
                ],
            );

            return $medication;
        });
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function pinFavoriteMedication(User $user, Medication $medication, array $defaults = []): ClinicianFavoriteMedication
    {
        return ClinicianFavoriteMedication::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'medication_id' => $medication->id,
            ],
            [
                'default_dose' => trim((string) ($defaults['default_dose'] ?? '1 tab')) ?: '1 tab',
                'default_route' => (string) ($defaults['default_route'] ?? 'PO'),
                'default_frequency' => (string) ($defaults['default_frequency'] ?? 'OD'),
                'default_duration_days' => max(1, (int) ($defaults['default_duration_days'] ?? 14)),
                'default_meal_timing' => (string) ($defaults['default_meal_timing'] ?? 'after_meal'),
                'default_instructions' => isset($defaults['default_instructions']) && trim((string) $defaults['default_instructions']) !== ''
                    ? trim((string) $defaults['default_instructions'])
                    : null,
            ]
        );
    }

    public function unpinFavoriteMedication(User $user, Medication $medication): void
    {
        ClinicianFavoriteMedication::query()
            ->where('user_id', $user->id)
            ->where('medication_id', $medication->id)
            ->delete();
    }
}
