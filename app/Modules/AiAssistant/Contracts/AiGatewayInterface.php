<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

/**
 * Future AI Assistant Contract (Phase 7).
 *
 * Disabled by default. AI must NEVER autonomously diagnose, prescribe, verify lab
 * results, sign reports, or discharge patients.
 */
interface AiGatewayInterface
{
    public function isEnabled(): bool;
}
