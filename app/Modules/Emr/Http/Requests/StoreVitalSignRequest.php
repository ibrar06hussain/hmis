<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('emr.vitals.record') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'systolic_bp' => ['nullable', 'integer', 'min:40', 'max:300'],
            'diastolic_bp' => ['nullable', 'integer', 'min:20', 'max:200'],
            'heart_rate' => ['nullable', 'integer', 'min:20', 'max:260'],
            'respiratory_rate' => ['nullable', 'integer', 'min:4', 'max:80'],
            'temperature_c' => ['nullable', 'numeric', 'min:30.0', 'max:45.0'],
            'spo2_percent' => ['nullable', 'integer', 'min:30', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.5', 'max:350'],
            'height_cm' => ['nullable', 'numeric', 'min:30', 'max:250'],
            'blood_glucose_mg_dl' => ['nullable', 'integer', 'min:10', 'max:1200'],
            'pain_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rhythm_note' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasAny = collect([
                'systolic_bp', 'diastolic_bp', 'heart_rate', 'respiratory_rate',
                'temperature_c', 'spo2_percent', 'weight_kg', 'height_cm', 'blood_glucose_mg_dl',
            ])->contains(fn (string $field): bool => $this->filled($field));

            if (! $hasAny) {
                $v->errors()->add('systolic_bp', 'Record at least one vital sign measurement.');
            }
        });
    }
}
