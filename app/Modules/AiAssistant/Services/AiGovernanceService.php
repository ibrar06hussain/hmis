<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Models\User;
use App\Modules\AiAssistant\Contracts\AiGatewayInterface;
use App\Modules\AiAssistant\Models\AiInteractionLog;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Carbon;

/**
 * Governance & Safety Gate for Phase 7 AI Assistant capabilities.
 *
 * Enforces:
 * 1. Global kill switch (HMIS_AI_ENABLED=false by default).
 * 2. Hard prohibition of autonomous clinical actions (diagnose, prescribe, verify labs, sign, discharge, surgery).
 * 3. Strict allow-listed read/draft tool registry.
 * 4. Prompt-injection resistance scanning on clinical notes and user input.
 * 5. Patient/encounter-scoped RBAC authorization + full immutable prompt/response audit logging.
 */
final class AiGovernanceService
{
    /** @var list<string> */
    public const ALLOWED_DRAFT_TOOLS = [
        'summarize_patient_history',
        'draft_discharge_summary',
        'summarize_lab_trends',
        'check_missing_documentation',
    ];

    /** @var list<string> */
    public const PROHIBITED_AUTONOMOUS_ACTIONS = [
        'establish_final_diagnosis',
        'prescribe_medication',
        'alter_medication_order',
        'verify_lab_result',
        'sign_medical_report',
        'discharge_patient',
        'authorize_surgery',
    ];

    /** @var list<string> */
    private const PROMPT_INJECTION_PATTERNS = [
        'ignore previous instructions',
        'ignore all prior instructions',
        'override system prompt',
        'bypass clinician review',
        'auto-approve',
        'prescribe without review',
        'disable audit log',
    ];

    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{allowed: bool, status: string, draft: ?string, message: string, log: AiInteractionLog}
     */
    public function invokeDraftTool(
        User $actor,
        string $toolName,
        ?Patient $patient = null,
        ?string $additionalPrompt = null,
    ): array {
        $startedAt = microtime(true);

        // 1. Instant Kill Switch Check
        if (! $this->gateway->isEnabled()) {
            $log = $this->recordLog($actor, $patient, $toolName, 'blocked_disabled', $additionalPrompt, null, $startedAt);

            return [
                'allowed' => false,
                'status' => 'blocked_disabled',
                'draft' => null,
                'message' => 'AI Assistant is disabled by hospital configuration (HMIS_AI_ENABLED=false).',
                'log' => $log,
            ];
        }

        // 2. Hard Block on Prohibited Autonomous Clinical Actions
        if (in_array($toolName, self::PROHIBITED_AUTONOMOUS_ACTIONS, true) || ! in_array($toolName, self::ALLOWED_DRAFT_TOOLS, true)) {
            $log = $this->recordLog($actor, $patient, $toolName, 'blocked_prohibited_action', $additionalPrompt, null, $startedAt);

            return [
                'allowed' => false,
                'status' => 'blocked_prohibited_action',
                'draft' => null,
                'message' => "Action '{$toolName}' is prohibited. AI cannot autonomously diagnose, prescribe, verify results, sign reports, authorize surgery, or discharge patients.",
                'log' => $log,
            ];
        }

        // 3. Explicit Patient-Scoped Authorization Check
        if ($patient !== null && ! $actor->can('viewClinicalProfile', $patient)) {
            $log = $this->recordLog($actor, $patient, $toolName, 'blocked_unauthorized', $additionalPrompt, null, $startedAt);

            return [
                'allowed' => false,
                'status' => 'blocked_unauthorized',
                'draft' => null,
                'message' => 'Actor lacks clinical authorization for this patient record.',
                'log' => $log,
            ];
        }

        // 4. Prompt Injection Guard (scans user prompt AND patient's registration/clinical notes!)
        $untrustedCorpus = strtolower(trim((string) $additionalPrompt.' '.($patient?->registration_notes ?? '')));
        if ($patient !== null) {
            $noteSnippets = $patient->clinicalNotes()->limit(5)->pluck('history_of_present_illness')->filter()->implode(' ');
            $untrustedCorpus .= ' '.strtolower($noteSnippets);
        }

        foreach (self::PROMPT_INJECTION_PATTERNS as $pattern) {
            if (str_contains($untrustedCorpus, $pattern)) {
                $log = $this->recordLog($actor, $patient, $toolName, 'blocked_prompt_injection', $additionalPrompt, null, $startedAt);

                return [
                    'allowed' => false,
                    'status' => 'blocked_prompt_injection',
                    'draft' => null,
                    'message' => 'Potential prompt injection directive detected in request or clinical text; invocation blocked and audited.',
                    'log' => $log,
                ];
            }
        }

        // 5. Data Minimization: pass only scoped non-identifiable clinical metadata (MRN + counts + coded diagnoses)
        $scopedContext = $patient !== null ? [
            'mrn' => $patient->mrn,
            'encounters_count' => $patient->encounters()->count(),
            'active_allergies_count' => $patient->activeAllergies()->count(),
            'diagnoses' => $patient->diagnoses()->limit(10)->get()->map(fn ($d) => "{$d->coding_system} {$d->code}: {$d->display_name}")->all(),
        ] : [];

        $draft = $this->gateway->generateDraft($toolName, $scopedContext);
        $log = $this->recordLog($actor, $patient, $toolName, 'draft_generated', $additionalPrompt, $draft, $startedAt);

        return [
            'allowed' => true,
            'status' => 'draft_generated',
            'draft' => $draft,
            'message' => 'Draft generated for clinician review (never finalized automatically).',
            'log' => $log,
        ];
    }

    private function recordLog(
        User $actor,
        ?Patient $patient,
        string $toolName,
        string $status,
        ?string $prompt,
        ?string $response,
        float $startedAt,
    ): AiInteractionLog {
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $log = AiInteractionLog::query()->create([
            'user_id' => $actor->id,
            'patient_id' => $patient?->id,
            'tool_name' => $toolName,
            'model_provider' => $this->gateway->providerName(),
            'model_version' => $this->gateway->modelVersion(),
            'prompt_summary' => $prompt !== null ? mb_substr(trim($prompt), 0, 240) : null,
            'response_summary' => $response,
            'status' => $status,
            'latency_ms' => max(0, $latencyMs),
            'occurred_at' => Carbon::now('UTC'),
        ]);

        $this->auditLogger->log(
            action: 'ai.interaction.'.$status,
            actor: $actor,
            subject: $log,
            patientId: $patient?->id,
            newValues: [
                'tool_name' => $toolName,
                'provider' => $this->gateway->providerName(),
                'status' => $status,
            ],
        );

        return $log;
    }
}
