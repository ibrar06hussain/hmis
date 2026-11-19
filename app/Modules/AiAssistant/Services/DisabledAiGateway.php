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

    public function providerName(): string
    {
        return 'disabled';
    }

    public function modelVersion(): string
    {
        return 'none';
    }

    public function generateDraft(string $toolName, array $scopedContext): string
    {
        throw new \DomainException('AI Assistant is disabled by hospital governance configuration (HMIS_AI_ENABLED=false).');
    }
}
