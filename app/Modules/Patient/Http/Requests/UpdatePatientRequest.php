<?php

declare(strict_types=1);

namespace App\Modules\Patient\Http\Requests;

use App\Support\CnicNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('patient.update_demographics') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'father_or_spouse_name' => ['nullable', 'string', 'max:160'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'dob_estimated' => ['nullable', 'boolean'],
            'sex' => ['required', Rule::in(['male', 'female', 'other', 'unknown'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'widowed', 'divorced', 'unknown'])],
            'occupation' => ['nullable', 'string', 'max:100'],
            'cnic' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && trim((string) $value) !== '' && ! CnicNormalizer::isValid((string) $value)) {
                        $fail('CNIC must contain exactly 13 digits (e.g., 71501-1234567-1).');
                    }
                },
            ],
            'phone_primary' => ['nullable', 'string', 'min:7', 'max:32'],
            'phone_secondary' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'tehsil' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'is_unknown_emergency' => ['nullable', 'boolean'],
            'registration_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
