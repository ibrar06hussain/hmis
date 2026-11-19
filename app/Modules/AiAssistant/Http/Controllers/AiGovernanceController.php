<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AiAssistant\Contracts\AiGatewayInterface;
use App\Modules\AiAssistant\Models\AiInteractionLog;
use App\Modules\AiAssistant\Services\AiGovernanceService;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiGovernanceController extends Controller
{
    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly AiGovernanceService $governanceService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('audit.view_logs') && ! $user->can('emr.note.sign')) {
            abort(403, 'AI Governance console is restricted to authorized governance & senior clinical roles.');
        }

        return view('modules.ai.index', [
            'aiEnabled' => $this->gateway->isEnabled(),
            'providerName' => $this->gateway->providerName(),
            'modelVersion' => $this->gateway->modelVersion(),
            'allowedTools' => AiGovernanceService::ALLOWED_DRAFT_TOOLS,
            'prohibitedActions' => AiGovernanceService::PROHIBITED_AUTONOMOUS_ACTIONS,
            'logs' => AiInteractionLog::query()->with('user')->orderByDesc('occurred_at')->limit(30)->get(),
            'patients' => Patient::query()->active()->orderByDesc('id')->limit(50)->get(),
            'generatedDraft' => session('ai_draft'),
        ]);
    }

    public function invokeDraft(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tool_name' => ['required', 'string', 'max:80'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'additional_prompt' => ['nullable', 'string', 'max:500'],
        ]);

        $patient = ! empty($validated['patient_id']) ? Patient::query()->find((int) $validated['patient_id']) : null;

        /** @var User $user */
        $user = $request->user();
        $result = $this->governanceService->invokeDraftTool(
            actor: $user,
            toolName: (string) $validated['tool_name'],
            patient: $patient,
            additionalPrompt: $validated['additional_prompt'] ?? null,
        );

        if (! $result['allowed']) {
            return redirect()
                ->route('ai.index')
                ->withErrors(['ai' => $result['message']]);
        }

        return redirect()
            ->route('ai.index')
            ->with('ai_draft', $result['draft'])
            ->with('status', $result['message']);
    }
}
