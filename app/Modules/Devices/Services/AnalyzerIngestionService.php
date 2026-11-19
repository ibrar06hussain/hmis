<?php

declare(strict_types=1);

namespace App\Modules\Devices\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Devices\Adapters\Hl7V2AnalyzerAdapter;
use App\Modules\Devices\Models\AnalyzerDevice;
use App\Modules\Devices\Models\AnalyzerRawMessage;
use App\Modules\Lis\Models\LabOrderItem;
use App\Modules\Lis\Models\Specimen;
use App\Modules\Lis\Services\LabOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AnalyzerIngestionService
{
    public function __construct(
        private readonly Hl7V2AnalyzerAdapter $hl7Adapter,
        private readonly LabOrderService $labOrderService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Ingest raw analyzer payload with SHA-256 duplicate protection and stage results for human verification.
     * NEVER auto-publishes results to verified status.
     *
     * @return array{status: string, staged_count: int, ack: string, message_uuid: ?string}
     */
    public function ingest(AnalyzerDevice $device, string $rawPayload): array
    {
        $hash = hash('sha256', $device->id.'|'.trim($rawPayload));

        // Duplicate analyzer frame rejection
        $existing = AnalyzerRawMessage::query()->where('message_hash', $hash)->first();
        if ($existing !== null) {
            return [
                'status' => 'duplicate',
                'staged_count' => 0,
                'ack' => $this->hl7Adapter->buildAcknowledgement($existing->control_id ?? 'DUP', true, 'Duplicate frame ignored'),
                'message_uuid' => $existing->uuid,
            ];
        }

        try {
            $parsed = $this->hl7Adapter->parseInbound($rawPayload, [
                'device_code' => $device->device_code,
                'test_code_map' => $device->test_code_map ?? [],
            ]);
        } catch (\Throwable $e) {
            $rawRecord = AnalyzerRawMessage::query()->create([
                'analyzer_device_id' => $device->id,
                'message_hash' => $hash,
                'control_id' => null,
                'protocol' => $device->protocol,
                'raw_payload' => $rawPayload,
                'parse_status' => 'failed',
                'error_message' => $e->getMessage(),
                'received_at' => Carbon::now('UTC'),
            ]);

            return [
                'status' => 'failed',
                'staged_count' => 0,
                'ack' => $this->hl7Adapter->buildAcknowledgement('ERR', false, $e->getMessage()),
                'message_uuid' => $rawRecord->uuid,
            ];
        }

        return DB::transaction(function () use ($device, $rawPayload, $hash, $parsed): array {
            $rawRecord = AnalyzerRawMessage::query()->create([
                'analyzer_device_id' => $device->id,
                'message_hash' => $hash,
                'control_id' => $parsed['control_id'],
                'protocol' => $device->protocol,
                'raw_payload' => $rawPayload,
                'parse_status' => 'received',
                'received_at' => Carbon::now('UTC'),
            ]);

            $specimen = Specimen::query()
                ->where('barcode', $parsed['specimen_barcode'])
                ->where('status', '!=', Specimen::STATUS_REJECTED)
                ->first();

            if ($specimen === null) {
                $rawRecord->update([
                    'parse_status' => 'failed',
                    'error_message' => "Unknown or rejected specimen barcode '{$parsed['specimen_barcode']}'.",
                ]);

                return [
                    'status' => 'failed',
                    'staged_count' => 0,
                    'ack' => $this->hl7Adapter->buildAcknowledgement($parsed['control_id'], false, 'Specimen not found'),
                    'message_uuid' => $rawRecord->uuid,
                ];
            }

            $codeMap = $device->test_code_map ?? [];
            $stagedCount = 0;

            foreach ($parsed['observations'] as $obs) {
                $mappedCatalogCode = $codeMap[$obs['vendor_code']] ?? $obs['vendor_code'];

                /** @var LabOrderItem|null $item */
                $item = LabOrderItem::query()
                    ->where('specimen_id', $specimen->id)
                    ->whereHas('test', fn ($q) => $q->where('code', $mappedCatalogCode))
                    ->where('status', '!=', LabOrderItem::STATUS_VERIFIED)
                    ->first();

                if ($item === null) {
                    continue;
                }

                $numeric = is_numeric($obs['value']) ? (float) $obs['value'] : null;

                // Stage as result_entered (NEVER verified!)
                $this->labOrderService->enterResult(
                    item: $item,
                    numericValue: $numeric,
                    textValue: $obs['value'],
                    actor: null,
                    source: 'analyzer',
                    analyzerDeviceCode: $device->device_code,
                );
                $stagedCount++;
            }

            $rawRecord->update(['parse_status' => 'staged']);
            $device->update([
                'connection_status' => 'online',
                'last_communication_at' => Carbon::now('UTC'),
            ]);

            $this->auditLogger->log(
                action: 'devices.analyzer.frame_staged',
                actor: null,
                subject: $rawRecord,
                patientId: $specimen->patient_id,
                newValues: [
                    'device_code' => $device->device_code,
                    'specimen_barcode' => $specimen->barcode,
                    'staged_count' => $stagedCount,
                ],
            );

            return [
                'status' => 'staged',
                'staged_count' => $stagedCount,
                'ack' => $this->hl7Adapter->buildAcknowledgement($parsed['control_id'], true),
                'message_uuid' => $rawRecord->uuid,
            ];
        });
    }
}
