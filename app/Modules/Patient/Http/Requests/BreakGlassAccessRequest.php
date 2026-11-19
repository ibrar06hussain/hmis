<?php

declare(strict_types=1);

namespace App\Modules\Patient\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BreakGlassAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('iam.break_glass') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => [
                'required',
                Rule::in([
                    'emergency_cardiac_event',
                    'unconscious_patient',
                    'urgent_consult_handoff',
                    'on_call_coverage',
                    'clinical_safety_verification',
                ]),
            ],
            'justification' => ['required', 'string', 'min:12', 'max:500'],
        ];
    }
}
