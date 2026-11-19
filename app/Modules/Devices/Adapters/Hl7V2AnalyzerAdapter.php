<?php

declare(strict_types=1);

namespace App\Modules\Devices\Adapters;

use App\Modules\Devices\Contracts\AnalyzerAdapterInterface;

/**
 * HL7 v2 ORU^R01 laboratory analyzer adapter (contract-tested with synthetic frames).
 */
final class Hl7V2AnalyzerAdapter implements AnalyzerAdapterInterface
{
    public function protocol(): string
    {
        return 'hl7_v2';
    }

    /**
     * Parse an HL7 v2 ORU^R01 message into structured specimen barcode & observation entries.
     *
     * @param  array<string, mixed>  $deviceConfig
     * @return array{control_id: string, specimen_barcode: ?string, observations: list<array{vendor_code: string, value: string, unit: ?string}>}
     */
    public function parseInbound(string $rawPayload, array $deviceConfig): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawPayload)) ?: [];
        if ($lines === [] || ! str_starts_with($lines[0], 'MSH|')) {
            throw new \InvalidArgumentException('Invalid HL7 v2 frame: missing MSH header segment.');
        }

        $controlId = '';
        $specimenBarcode = null;
        $observations = [];

        foreach ($lines as $line) {
            $fields = explode('|', $line);
            $segment = $fields[0] ?? '';

            if ($segment === 'MSH') {
                $controlId = trim((string) ($fields[9] ?? ''));
            } elseif ($segment === 'OBR') {
                // OBR-2 (Placer Order / Specimen Barcode) or OBR-3 (Filler Order)
                $specimenBarcode = trim((string) ($fields[2] ?? $fields[3] ?? '')) ?: null;
            } elseif ($segment === 'OBX') {
                $identifier = explode('^', (string) ($fields[3] ?? ''));
                $vendorCode = strtoupper(trim($identifier[0] ?? ''));
                $value = trim((string) ($fields[5] ?? ''));
                $unit = trim((string) ($fields[6] ?? '')) ?: null;

                if ($vendorCode !== '' && $value !== '') {
                    $observations[] = [
                        'vendor_code' => $vendorCode,
                        'value' => $value,
                        'unit' => $unit,
                    ];
                }
            }
        }

        if ($controlId === '') {
            throw new \InvalidArgumentException('Invalid HL7 v2 frame: MSH-10 Message Control ID is required.');
        }

        if ($specimenBarcode === null) {
            throw new \InvalidArgumentException('Invalid HL7 v2 frame: OBR specimen barcode is missing.');
        }

        return [
            'control_id' => $controlId,
            'specimen_barcode' => $specimenBarcode,
            'observations' => $observations,
        ];
    }

    public function buildAcknowledgement(string $messageControlId, bool $accepted, ?string $error = null): string
    {
        $ackCode = $accepted ? 'AA' : 'AE';
        $timestamp = now('UTC')->format('YmdHis');
        $text = $accepted ? 'Message accepted and staged for human verification' : ($error ?? 'Processing error');

        return "MSH|^~\\&|CHG-HMIS|CHG|ANALYZER|LIS|{$timestamp}||ACK^R01|ACK-{$messageControlId}|P|2.5.1\rMSA|{$ackCode}|{$messageControlId}|{$text}";
    }
}
