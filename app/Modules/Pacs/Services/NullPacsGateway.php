<?php

declare(strict_types=1);

namespace App\Modules\Pacs\Services;

use App\Modules\Pacs\Contracts\PacsGatewayInterface;
use App\Modules\Pacs\Models\ImagingStudy;

final class NullPacsGateway implements PacsGatewayInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function buildViewerUrl(ImagingStudy $study, int $userId): ?string
    {
        return null;
    }
}
