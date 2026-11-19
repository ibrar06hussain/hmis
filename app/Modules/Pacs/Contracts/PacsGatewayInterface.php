<?php

declare(strict_types=1);

namespace App\Modules\Pacs\Contracts;

use App\Modules\Pacs\Models\ImagingStudy;

/**
 * Future PACS Integration Contract (Phase 6: Orthanc + OHIF + DICOMweb).
 *
 * Keeps Unified Patient Profile completely decoupled from PACS vendor specifics.
 */
interface PacsGatewayInterface
{
    public function isEnabled(): bool;

    public function buildViewerUrl(ImagingStudy $study, int $userId): ?string;
}
