<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\AiGatewayInterface;

/**
 * Offline-safe, zero-PHI-transmission deterministic draft synthesizer for Phase 7 validation.
 * Replaceable with an approved local on-premises LLM server via AiGatewayInterface without touching domain code.
 */
final class LocalDraftAiGateway implements AiGatewayInterface
{
    public function isEnabled(): bool
    {
        return (bool) config('hmis.ai.enabled', false);
    }

    public function providerName(): string
    {
        return 'local_offline_draft_engine';
    }

    public function modelVersion(): string
    {
        return 'chg-deterministic-draft-v1';
    }

    public function generateDraft(string $toolName, array $scopedContext): string
    {
        $mrn = (string) ($scopedContext['mrn'] ?? 'UNKNOWN');
        $encountersCount = (int) ($scopedContext['encounters_count'] ?? 0);
        $allergiesCount = (int) ($scopedContext['active_allergies_count'] ?? 0);
        $diagnoses = implode('; ', (array) ($scopedContext['diagnoses'] ?? []));

        $body = match ($toolName) {
            'summarize_patient_history' => "MRN {$mrn}: {$encountersCount} encounter(s), {$allergiesCount} active allergy alert(s). Coded diagnoses: ".($diagnoses ?: 'None recorded').'.',
            'draft_discharge_summary' => "Discharge Summary Outline for MRN {$mrn}: Review hospital course across {$encountersCount} encounter(s), confirm reconciliation of allergies ({$allergiesCount}) and coded diagnoses (".($diagnoses ?: 'Pending').').',
            'check_missing_documentation' => "Documentation Check for MRN {$mrn}: Verify allergy status ({$allergiesCount} active), coded diagnoses (".($diagnoses ? 'Present' : 'Missing').'), and signed encounter notes.',
            default => "Scoped draft prepared for MRN {$mrn}.",
        };

        return "[DRAFT — REQUIRES AUTHORIZED CLINICIAN REVIEW] {$body}";
    }
}
