<?php

declare(strict_types=1);

namespace App\Modules\Patient\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('patient.merge') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_patient_id' => ['required', 'integer', 'exists:patients,id', 'different:target_patient_id'],
            'target_patient_id' => ['required', 'integer', 'exists:patients,id'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
