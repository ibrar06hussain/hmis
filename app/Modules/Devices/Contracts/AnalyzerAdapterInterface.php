<?php

declare(strict_types=1);

namespace App\Modules\Devices\Contracts;

/**
 * Future LIS Equipment Adapter Contract (Phase 5/6).
 *
 * All clinical laboratory analyzer integrations MUST parse inbound frames into
 * staged, unverified observations; automatic result publication is prohibited.
 */
interface AnalyzerAdapterInterface
{
    public function protocol(): string;

    /**
     * @param  array<string, mixed>  $deviceConfig
     * @return array<string, mixed>
     */
    public function parseInbound(string $rawPayload, array $deviceConfig): array;

    public function buildAcknowledgement(string $messageControlId, bool $accepted, ?string $error = null): string;
}
