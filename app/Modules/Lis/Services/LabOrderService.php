<?php

declare(strict_types=1);

namespace App\Modules\Lis\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Emr\Models\Encounter;
use App\Modules\Lis\Models\LabOrder;
use App\Modules\Lis\Models\LabOrderItem;
use App\Modules\Lis\Models\LabTestCatalog;
use App\Modules\Lis\Models\Specimen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class LabOrderService
{
    public function __construct(
        private readonly DiagnosticSequenceService $sequenceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Create a laboratory order and automatically generate unique barcode specimens grouped by specimen type.
     *
     * @param  list<int>  $testIds
     */
    public function createOrder(
        Encounter $encounter,
        array $testIds,
        string $priority,
        ?string $clinicalNotes,
        User $actor,
    ): LabOrder {
        $tests = LabTestCatalog::query()
            ->whereIn('id', $testIds)
            ->where('is_active', true)
            ->get();

        if ($tests->isEmpty()) {
            throw new \InvalidArgumentException('Select at least one active laboratory test.');
        }

        return DB::transaction(function () use ($encounter, $tests, $priority, $clinicalNotes, $actor): LabOrder {
            $now = Carbon::now('UTC');
            $patient = $encounter->patient;

            $order = LabOrder::query()->create([
                'order_number' => $this->sequenceService->next('LAB'),
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'ordered_by_user_id' => $actor->id,
                'priority' => $priority,
                'status' => LabOrder::STATUS_ORDERED,
                'clinical_notes' => $clinicalNotes,
                'ordered_at' => $now,
                'version' => 1,
            ]);

            // Group tests by specimen type so each tube receives a unique barcode
            $specimensByType = [];
            foreach ($tests->groupBy('specimen_type') as $specimenType => $groupTests) {
                $specimen = Specimen::query()->create([
                    'barcode' => $this->sequenceService->next('SMP'),
                    'lab_order_id' => $order->id,
                    'patient_id' => $patient->id,
                    'specimen_type' => (string) $specimenType,
                    'status' => Specimen::STATUS_PENDING_COLLECTION,
                ]);
                $specimensByType[(string) $specimenType] = $specimen;
            }

            foreach ($tests as $test) {
                [$refLow, $refHigh] = $test->referenceRangeForSex($patient->sex);

                LabOrderItem::query()->create([
                    'lab_order_id' => $order->id,
                    'lab_test_catalog_id' => $test->id,
                    'specimen_id' => $specimensByType[$test->specimen_type]->id,
                    'status' => LabOrderItem::STATUS_ORDERED,
                    'unit' => $test->unit,
                    'ref_low' => $refLow,
                    'ref_high' => $refHigh,
                    'version' => 1,
                ]);
            }

            $this->auditLogger->log(
                action: 'lis.order.created',
                actor: $actor,
                subject: $order,
                patientId: $patient->id,
                newValues: [
                    'order_number' => $order->order_number,
                    'priority' => $priority,
                    'test_codes' => $tests->pluck('code')->all(),
                ],
            );

            return $order->fresh(['items.test', 'specimens', 'patient']);
        });
    }

    public function collectSpecimen(Specimen $specimen, User $actor): Specimen
    {
        return DB::transaction(function () use ($specimen, $actor): Specimen {
            $locked = Specimen::query()->whereKey($specimen->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Specimen::STATUS_PENDING_COLLECTION) {
                throw new \DomainException("Specimen {$locked->barcode} is already in status '{$locked->status}'.");
            }

            $locked->status = Specimen::STATUS_COLLECTED;
            $locked->collected_by_user_id = $actor->id;
            $locked->collected_at = Carbon::now('UTC');
            $locked->save();

            $locked->labOrder()->where('status', LabOrder::STATUS_ORDERED)->update(['status' => LabOrder::STATUS_COLLECTED]);

            $this->auditLogger->log(
                action: 'lis.specimen.collected',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: ['barcode' => $locked->barcode],
            );

            return $locked;
        });
    }

    public function receiveSpecimen(Specimen $specimen, User $actor): Specimen
    {
        return DB::transaction(function () use ($specimen, $actor): Specimen {
            $locked = Specimen::query()->whereKey($specimen->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [Specimen::STATUS_COLLECTED, Specimen::STATUS_PENDING_COLLECTION], true)) {
                throw new \DomainException("Specimen {$locked->barcode} cannot be received from status '{$locked->status}'.");
            }

            $now = Carbon::now('UTC');
            if ($locked->collected_at === null) {
                $locked->collected_at = $now;
                $locked->collected_by_user_id = $actor->id;
            }

            $locked->status = Specimen::STATUS_RECEIVED;
            $locked->received_by_user_id = $actor->id;
            $locked->received_at = $now;
            $locked->save();

            $locked->labOrder()->whereIn('status', [LabOrder::STATUS_ORDERED, LabOrder::STATUS_COLLECTED])
                ->update(['status' => LabOrder::STATUS_RECEIVED]);

            $this->auditLogger->log(
                action: 'lis.specimen.received',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                newValues: ['barcode' => $locked->barcode],
            );

            return $locked;
        });
    }

    /**
     * Reject a compromised specimen (e.g., hemolyzed/clotted) and automatically issue a new barcode for recollection.
     */
    public function rejectAndReissueSpecimen(Specimen $specimen, string $reason, User $actor): Specimen
    {
        return DB::transaction(function () use ($specimen, $reason, $actor): Specimen {
            $locked = Specimen::query()->whereKey($specimen->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Specimen::STATUS_REJECTED) {
                throw new \DomainException('Specimen is already rejected.');
            }

            $locked->status = Specimen::STATUS_REJECTED;
            $locked->rejection_reason = trim($reason);
            $locked->save();

            $replacement = Specimen::query()->create([
                'barcode' => $this->sequenceService->next('SMP'),
                'lab_order_id' => $locked->lab_order_id,
                'patient_id' => $locked->patient_id,
                'specimen_type' => $locked->specimen_type,
                'status' => Specimen::STATUS_PENDING_COLLECTION,
            ]);

            LabOrderItem::query()
                ->where('specimen_id', $locked->id)
                ->where('status', '!=', LabOrderItem::STATUS_VERIFIED)
                ->update(['specimen_id' => $replacement->id]);

            $this->auditLogger->log(
                action: 'lis.specimen.rejected_reissued',
                actor: $actor,
                subject: $locked,
                patientId: $locked->patient_id,
                oldValues: ['rejected_barcode' => $locked->barcode],
                newValues: ['replacement_barcode' => $replacement->barcode],
                reason: $reason,
            );

            return $replacement;
        });
    }

    /**
     * Enter manual or analyzer-staged result. NEVER publishes directly to verified clinical state.
     */
    public function enterResult(
        LabOrderItem $item,
        ?float $numericValue,
        ?string $textValue,
        ?User $actor,
        string $source = 'manual',
        ?string $analyzerDeviceCode = null,
    ): LabOrderItem {
        return DB::transaction(function () use ($item, $numericValue, $textValue, $actor, $source, $analyzerDeviceCode): LabOrderItem {
            $locked = LabOrderItem::query()->with(['test', 'labOrder'])->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === LabOrderItem::STATUS_VERIFIED) {
                throw new \DomainException('Verified laboratory results cannot be overwritten directly. Use audited amendment.');
            }

            [$flag, $isCritical] = $this->evaluateAbnormality($numericValue, $locked->ref_low, $locked->ref_high, $locked->test);

            $locked->result_value_numeric = $numericValue;
            $locked->result_value_text = $textValue !== null && trim($textValue) !== '' ? trim($textValue) : ($numericValue !== null ? (string) $numericValue : null);
            $locked->abnormal_flag = $flag;
            $locked->is_critical = $isCritical;
            $locked->result_source = $source;
            $locked->analyzer_device_code = $analyzerDeviceCode;
            $locked->status = LabOrderItem::STATUS_RESULT_ENTERED;
            $locked->entered_by_user_id = $actor?->id;
            $locked->entered_at = Carbon::now('UTC');
            $locked->version = $locked->version + 1;
            $locked->save();

            $locked->labOrder()->whereNotIn('status', [LabOrder::STATUS_VERIFIED, LabOrder::STATUS_CANCELLED])
                ->update(['status' => LabOrder::STATUS_IN_ANALYSIS]);

            $this->auditLogger->log(
                action: $isCritical ? 'lis.result.critical_entered' : 'lis.result.entered',
                actor: $actor,
                subject: $locked,
                patientId: $locked->labOrder->patient_id,
                newValues: [
                    'test_code' => $locked->test->code,
                    'value' => $locked->result_value_text,
                    'abnormal_flag' => $flag,
                    'is_critical' => $isCritical,
                    'source' => $source,
                ],
            );

            return $locked;
        });
    }

    /**
     * Step 1 of 2-step clinical release: Technical Validation by Laboratory Technician.
     */
    public function technicalValidate(LabOrderItem $item, User $actor): LabOrderItem
    {
        return DB::transaction(function () use ($item, $actor): LabOrderItem {
            $locked = LabOrderItem::query()->with('labOrder')->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LabOrderItem::STATUS_RESULT_ENTERED) {
                throw new \DomainException('Result must be entered before technical validation.');
            }

            $locked->status = LabOrderItem::STATUS_TECH_VALIDATED;
            $locked->tech_validated_by_user_id = $actor->id;
            $locked->tech_validated_at = Carbon::now('UTC');
            $locked->version = $locked->version + 1;
            $locked->save();

            $locked->labOrder()->whereNotIn('status', [LabOrder::STATUS_VERIFIED, LabOrder::STATUS_CANCELLED])
                ->update(['status' => LabOrder::STATUS_PRELIMINARY]);

            $this->auditLogger->log(
                action: 'lis.result.tech_validated',
                actor: $actor,
                subject: $locked,
                patientId: $locked->labOrder->patient_id,
                newValues: ['item_id' => $locked->id],
            );

            return $locked;
        });
    }

    /**
     * Step 2 of 2-step clinical release: Authorized Pathologist / Senior Verifier Final Release.
     * Only verified items appear on the patient's clinical report.
     */
    public function verifyItem(LabOrderItem $item, User $verifier): LabOrderItem
    {
        return DB::transaction(function () use ($item, $verifier): LabOrderItem {
            $locked = LabOrderItem::query()->with(['labOrder', 'test'])->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LabOrderItem::STATUS_RESULT_ENTERED, LabOrderItem::STATUS_TECH_VALIDATED], true)) {
                throw new \DomainException('Result must be entered/technically validated before final verification.');
            }

            $now = Carbon::now('UTC');
            $locked->status = LabOrderItem::STATUS_VERIFIED;
            $locked->verified_by_user_id = $verifier->id;
            $locked->verified_at = $now;
            $locked->version = $locked->version + 1;
            $locked->save();

            // Transition parent LabOrder to 'verified' once every item is verified
            $order = LabOrder::query()->whereKey($locked->lab_order_id)->lockForUpdate()->firstOrFail();
            $unverifiedRemaining = $order->items()->where('status', '!=', LabOrderItem::STATUS_VERIFIED)->exists();
            if (! $unverifiedRemaining) {
                $order->status = LabOrder::STATUS_VERIFIED;
                $order->verified_by_user_id = $verifier->id;
                $order->verified_at = $now;
                $order->version = $order->version + 1;
                $order->save();
            }

            $this->auditLogger->log(
                action: 'lis.result.verified',
                actor: $verifier,
                subject: $locked,
                patientId: $order->patient_id,
                newValues: [
                    'order_number' => $order->order_number,
                    'test_code' => $locked->test->code,
                    'value' => $locked->result_value_text,
                    'order_fully_verified' => ! $unverifiedRemaining,
                ],
            );

            return $locked;
        });
    }

    public function acknowledgeCritical(LabOrderItem $item, User $clinician): LabOrderItem
    {
        return DB::transaction(function () use ($item, $clinician): LabOrderItem {
            $locked = LabOrderItem::query()->with(['labOrder', 'test'])->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_critical) {
                throw new \DomainException('This laboratory result is not flagged as a critical value.');
            }

            $locked->critical_acknowledged_by_user_id = $clinician->id;
            $locked->critical_acknowledged_at = Carbon::now('UTC');
            $locked->save();

            $this->auditLogger->log(
                action: 'lis.critical.acknowledged',
                actor: $clinician,
                subject: $locked,
                patientId: $locked->labOrder->patient_id,
                newValues: [
                    'test_code' => $locked->test->code,
                    'value' => $locked->result_value_text,
                    'abnormal_flag' => $locked->abnormal_flag,
                ],
            );

            return $locked;
        });
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function evaluateAbnormality(?float $value, ?float $refLow, ?float $refHigh, LabTestCatalog $test): array
    {
        if ($value === null) {
            return ['normal', false];
        }

        if ($test->critical_low !== null && $value <= $test->critical_low) {
            return ['critical_low', true];
        }

        if ($test->critical_high !== null && $value >= $test->critical_high) {
            return ['critical_high', true];
        }

        if ($refLow !== null && $value < $refLow) {
            return ['low', false];
        }

        if ($refHigh !== null && $value > $refHigh) {
            return ['high', false];
        }

        return ['normal', false];
    }
}
