<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AmendClinicalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('emr.note.sign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amendment_reason' => ['required', 'string', 'min:10', 'max:500'],
            'chief_complaint' => ['required', 'string', 'max:2000'],
            'history_of_present_illness' => ['nullable', 'string', 'max:5000'],
            'physical_examination' => ['nullable', 'string', 'max:4000'],
            'assessment_and_diagnosis' => ['nullable', 'string', 'max:3000'],
            'treatment_plan' => ['nullable', 'string', 'max:4000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
