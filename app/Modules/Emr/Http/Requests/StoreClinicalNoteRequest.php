<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('emr.note.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note_type' => ['required', Rule::in(['opd_consult', 'cardiology_eval', 'er_triage', 'follow_up', 'nursing_note'])],
            'chief_complaint' => ['required', 'string', 'max:2000'],
            'history_of_present_illness' => ['nullable', 'string', 'max:5000'],
            'past_medical_history' => ['nullable', 'string', 'max:3000'],
            'family_history' => ['nullable', 'string', 'max:2000'],
            'social_history' => ['nullable', 'string', 'max:2000'],
            'medication_history' => ['nullable', 'string', 'max:3000'],
            'physical_examination' => ['nullable', 'string', 'max:4000'],
            'assessment_and_diagnosis' => ['nullable', 'string', 'max:3000'],
            'treatment_plan' => ['nullable', 'string', 'max:4000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:2000'],
            'referral_notes' => ['nullable', 'string', 'max:2000'],
            'nyha_class' => ['nullable', Rule::in(['I', 'II', 'III', 'IV'])],
            'chest_pain_type' => ['nullable', Rule::in(['none', 'typical_angina', 'atypical_angina', 'non_cardiac', 'acute_acs'])],
            'cardiac_risk_factors' => ['nullable', 'array'],
            'cardiac_risk_factors.*' => ['string', 'max:64'],
            'ecg_summary' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
