<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\AiGatewayInterface;

final class DisabledAiGateway implements AiGatewayInterface
{
    public function isEnabled(): bool
    {
        return false;
    }
}
