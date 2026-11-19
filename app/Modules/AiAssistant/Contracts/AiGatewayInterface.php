<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

/**
 * Provider-Neutral AI Assistant Contract (Phase 7).
 *
 * Disabled by default. AI must NEVER autonomously diagnose, prescribe, verify lab
 * results, sign medical reports, authorize surgery, or discharge patients.
 */
interface AiGatewayInterface
{
    public function isEnabled(): bool;

    public function providerName(): string;

    public function modelVersion(): string;

    /**
     * Generate a clearly watermarked, human-review-required draft from scoped clinical context.
     *
     * @param  array<string, mixed>  $scopedContext
     */
    public function generateDraft(string $toolName, array $scopedContext): string;
}
