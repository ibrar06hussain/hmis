<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Emr\Models\PatientAllergy;
use App\Modules\Lis\Services\DiagnosticSequenceService;
use App\Modules\Pharmacy\Exceptions\MedicationAllergyConflictException;
use App\Modules\Pharmacy\Models\Medication;
use App\Modules\Pharmacy\Models\Prescription;
use App\Modules\Pharmacy\Models\PrescriptionItem;
use App\Modules\Pharmacy\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PharmacyService
{
    public function __construct(
        private readonly DiagnosticSequenceService $sequenceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Prescribe a medication with automatic active medication allergy safety checking.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws MedicationAllergyConflictException
     */
    public function prescribe(
        Encounter $encounter,
        array $data,
        User $prescriber,
        bool $confirmAllergyOverride = false,
        ?string $overrideReason = null,
    ): Prescription {
        $medication = Medication::query()->where('is_active', true)->findOrFail((int) $data['medication_id']);
        $patient = $encounter->patient;

        // Clinical Safety Check: Match active medication allergies against generic/brand/class
        $conflicts = $this->detectAllergyConflicts($patient->activeAllergies()->where('category', 'medication')->get(), $medication);

        if ($conflicts !== [] && (! $confirmAllergyOverride || trim((string) $overrideReason) === '')) {
            throw new MedicationAllergyConflictException(
                $conflicts,
                "ALLERGY CONFLICT: Patient has active allergy to '".implode(', ', $conflicts)."' which conflicts with {$medication->generic_name}. Provide explicit clinical override justification if medically required."
            );
        }

        return DB::transaction(function () use ($encounter, $medication, $patient, $data, $prescriber, $conflicts, $overrideReason): Prescription {
            $now = Carbon::now('UTC');
            $allergyOverrideUsed = $conflicts !== [];

            $prescription = Prescription::query()->create([
                'prescription_number' => $this->sequenceService->next('RX'),
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'prescribed_by_user_id' => $prescriber->id,
                'status' => Prescription::STATUS_ACTIVE,
                'allergy_override_used' => $allergyOverrideUsed,
                'allergy_override_reason' => $allergyOverrideUsed ? trim((string) $overrideReason) : null,
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'prescribed_at' => $now,
                'version' => 1,
            ]);

            PrescriptionItem::query()->create([
                'prescription_id' => $prescription->id,
                'medication_id' => $medication->id,
                'dose' => trim((string) $data['dose']),
                'route' => (string) ($data['route'] ?? 'PO'),
                'frequency' => (string) ($data['frequency'] ?? 'OD'),
                'duration_days' => (int) ($data['duration_days'] ?? 7),
                'quantity_prescribed' => (int) $data['quantity_prescribed'],
                'quantity_dispensed' => 0,
                'instructions' => $data['instructions'] ?? null,
                'meal_timing' => $data['meal_timing'] ?? null,
                'status' => PrescriptionItem::STATUS_PENDING,
            ]);

            $this->auditLogger->log(
                action: $allergyOverrideUsed ? 'pharmacy.allergy_override_prescribed' : 'pharmacy.prescribed',
                actor: $prescriber,
                subject: $prescription,
                patientId: $patient->id,
                newValues: [
                    'prescription_number' => $prescription->prescription_number,
                    'medication_code' => $medication->code,
                    'generic_name' => $medication->generic_name,
                    'quantity' => (int) $data['quantity_prescribed'],
                    'allergy_override_used' => $allergyOverrideUsed,
                ],
                reason: $overrideReason,
            );

            return $prescription->fresh(['items.medication', 'patient', 'encounter']);
        });
    }

    /**
     * Prescribe multiple medications atomically under one prescription number (RX-YYYY-NNNNNN)
     * with upfront active medication allergy checking across all items.
     *
     * @param  list<array<string, mixed>>  $items
     *
     * @throws MedicationAllergyConflictException
     */
    public function prescribeBatch(
        Encounter $encounter,
        array $items,
        User $prescriber,
        bool $confirmAllergyOverride = false,
        ?string $overrideReason = null,
        ?string $clinicalNotes = null,
    ): Prescription {
        if ($items === []) {
            throw new \InvalidArgumentException('At least one medication item is required.');
        }

        $patient = $encounter->patient;
        $activeMedAllergies = $patient->activeAllergies()->where('category', 'medication')->get();

        $resolvedRows = [];
        $allConflicts = [];

        foreach ($items as $item) {
            $medication = Medication::query()->where('is_active', true)->findOrFail((int) $item['medication_id']);
            $conflicts = $this->detectAllergyConflicts($activeMedAllergies, $medication);
            foreach ($conflicts as $conflict) {
                $allConflicts[] = "{$conflict} ({$medication->generic_name})";
            }
            $resolvedRows[] = ['medication' => $medication, 'data' => $item];
        }

        $allConflicts = array_values(array_unique($allConflicts));

        if ($allConflicts !== [] && (! $confirmAllergyOverride || trim((string) $overrideReason) === '')) {
            throw new MedicationAllergyConflictException(
                $allConflicts,
                'ALLERGY CONFLICT: Active drug allergy detected for '.implode(', ', $allConflicts).'. Provide explicit clinical override justification if medically required.'
            );
        }

        return DB::transaction(function () use ($encounter, $patient, $resolvedRows, $prescriber, $allConflicts, $overrideReason, $clinicalNotes): Prescription {
            $now = Carbon::now('UTC');
            $allergyOverrideUsed = $allConflicts !== [];

            $prescription = Prescription::query()->create([
                'prescription_number' => $this->sequenceService->next('RX'),
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'prescribed_by_user_id' => $prescriber->id,
                'status' => Prescription::STATUS_ACTIVE,
                'allergy_override_used' => $allergyOverrideUsed,
                'allergy_override_reason' => $allergyOverrideUsed ? trim((string) $overrideReason) : null,
                'clinical_notes' => $clinicalNotes,
                'prescribed_at' => $now,
                'version' => 1,
            ]);

            $medCodes = [];
            foreach ($resolvedRows as $row) {
                /** @var Medication $medication */
                $medication = $row['medication'];
                $data = $row['data'];
                $medCodes[] = $medication->code;

                PrescriptionItem::query()->create([
                    'prescription_id' => $prescription->id,
                    'medication_id' => $medication->id,
                    'dose' => trim((string) ($data['dose'] ?? '1 tab')),
                    'route' => (string) ($data['route'] ?? 'PO'),
                    'frequency' => (string) ($data['frequency'] ?? 'OD'),
                    'duration_days' => max(1, (int) ($data['duration_days'] ?? 14)),
                    'quantity_prescribed' => max(1, (int) ($data['quantity_prescribed'] ?? 14)),
                    'quantity_dispensed' => 0,
                    'instructions' => isset($data['instructions']) && trim((string) $data['instructions']) !== ''
                        ? trim((string) $data['instructions'])
                        : null,
                    'meal_timing' => isset($data['meal_timing']) && trim((string) $data['meal_timing']) !== ''
                        ? trim((string) $data['meal_timing'])
                        : null,
                    'status' => PrescriptionItem::STATUS_PENDING,
                ]);
            }

            $this->auditLogger->log(
                action: $allergyOverrideUsed ? 'pharmacy.allergy_override_prescribed' : 'pharmacy.batch_prescribed',
                actor: $prescriber,
                subject: $prescription,
                patientId: $patient->id,
                newValues: [
                    'prescription_number' => $prescription->prescription_number,
                    'medication_codes' => $medCodes,
                    'item_count' => count($medCodes),
                    'allergy_override_used' => $allergyOverrideUsed,
                ],
                reason: $overrideReason,
            );

            return $prescription->fresh(['items.medication', 'patient', 'encounter']);
        });
    }

    /**
     * Dispense a pending prescription item with atomic stock deduction and immutable stock ledger.
     */
    public function dispenseItem(PrescriptionItem $item, User $pharmacist): PrescriptionItem
    {
        return DB::transaction(function () use ($item, $pharmacist): PrescriptionItem {
            $lockedItem = PrescriptionItem::query()->with('prescription')->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($lockedItem->status !== PrescriptionItem::STATUS_PENDING) {
                throw new \DomainException("Prescription item is already '{$lockedItem->status}'.");
            }

            /** @var Medication $medication */
            $medication = Medication::query()->whereKey($lockedItem->medication_id)->lockForUpdate()->firstOrFail();

            if ($medication->stock_quantity < $lockedItem->quantity_prescribed) {
                throw new \DomainException(
                    "Insufficient pharmacy stock for {$medication->generic_name} ({$medication->strength}): available {$medication->stock_quantity}, required {$lockedItem->quantity_prescribed}."
                );
            }

            $now = Carbon::now('UTC');
            $medication->stock_quantity = $medication->stock_quantity - $lockedItem->quantity_prescribed;
            $medication->version = $medication->version + 1;
            $medication->save();

            StockMovement::query()->create([
                'medication_id' => $medication->id,
                'movement_type' => 'dispense',
                'quantity_delta' => -$lockedItem->quantity_prescribed,
                'balance_after' => $medication->stock_quantity,
                'reference_type' => PrescriptionItem::class,
                'reference_id' => $lockedItem->id,
                'notes' => "Dispensed for {$lockedItem->prescription->prescription_number}",
                'actor_user_id' => $pharmacist->id,
                'occurred_at' => $now,
            ]);

            $lockedItem->quantity_dispensed = $lockedItem->quantity_prescribed;
            $lockedItem->status = PrescriptionItem::STATUS_DISPENSED;
            $lockedItem->dispensed_by_user_id = $pharmacist->id;
            $lockedItem->dispensed_at = $now;
            $lockedItem->save();

            // Update parent prescription status
            $prescription = Prescription::query()->whereKey($lockedItem->prescription_id)->lockForUpdate()->firstOrFail();
            $hasPending = $prescription->items()->where('status', PrescriptionItem::STATUS_PENDING)->exists();
            $prescription->status = $hasPending ? Prescription::STATUS_PARTIALLY_DISPENSED : Prescription::STATUS_DISPENSED;
            $prescription->version = $prescription->version + 1;
            $prescription->save();

            $this->auditLogger->log(
                action: 'pharmacy.dispensed',
                actor: $pharmacist,
                subject: $lockedItem,
                patientId: $prescription->patient_id,
                newValues: [
                    'prescription_number' => $prescription->prescription_number,
                    'medication_code' => $medication->code,
                    'quantity_dispensed' => $lockedItem->quantity_dispensed,
                    'remaining_stock' => $medication->stock_quantity,
                ],
            );

            return $lockedItem->fresh(['medication', 'prescription']);
        });
    }

    /**
     * @param  Collection<int, PatientAllergy>  $allergies
     * @return list<string>
     */
    private function detectAllergyConflicts(Collection $allergies, Medication $medication): array
    {
        $haystacks = array_filter([
            strtolower($medication->generic_name),
            strtolower((string) $medication->brand_name),
            strtolower((string) $medication->drug_class),
        ]);

        $conflicts = [];
        foreach ($allergies as $allergy) {
            $needle = strtolower(trim($allergy->substance));
            if ($needle === '') {
                continue;
            }
            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                    $conflicts[] = $allergy->substance;
                    break;
                }
            }
        }

        return array_values(array_unique($conflicts));
    }
}
