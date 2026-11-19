<?php

declare(strict_types=1);

namespace App\Modules\Pacs\Contracts;

use App\Modules\Pacs\Models\ImagingStudy;

/**
 * PACS Integration Contract (Phase 6: Orthanc DICOM Server + OHIF Viewer + DICOMweb).
 *
 * Keeps Unified Patient Profile & RIS completely decoupled from PACS vendor specifics.
 */
interface PacsGatewayInterface
{
    public function isEnabled(): bool;

    public function buildViewerUrl(ImagingStudy $study, int $userId): ?string;

    /**
     * Query PACS (DICOMweb QIDO-RS) by Accession Number and attach StudyInstanceUID if found.
     */
    public function syncStudyByAccession(ImagingStudy $study): ?string;
}
