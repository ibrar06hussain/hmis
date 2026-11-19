<?php

declare(strict_types=1);

namespace App\Modules\Patient\Http\Requests;

use App\Modules\Patient\Models\Patient;
use App\Support\CnicNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ($this->input('registration_type') === Patient::REG_EMERGENCY) {
            return $user->can('patient.register_emergency') || $user->can('patient.register');
        }

        return $user->can('patient.register');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isEmergency = $this->input('registration_type') === Patient::REG_EMERGENCY;
        $isUnknown = (bool) $this->boolean('is_unknown_emergency');

        return [
            'registration_type' => ['required', Rule::in([Patient::REG_STANDARD, Patient::REG_EMERGENCY, Patient::REG_NEWBORN])],
            'is_unknown_emergency' => ['nullable', 'boolean'],
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'father_or_spouse_name' => [$isEmergency || $isUnknown ? 'nullable' : 'required', 'string', 'max:160'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'dob_estimated' => ['nullable', 'boolean'],
            'estimated_age_years' => ['nullable', 'integer', 'min:0', 'max:130'],
            'sex' => ['required', Rule::in(['male', 'female', 'other', 'unknown'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'widowed', 'divorced', 'unknown'])],
            'occupation' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:64'],
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
            'sehat_card_number' => ['nullable', 'string', 'max:64'],
            'phone_primary' => [$isEmergency || $isUnknown ? 'nullable' : 'required', 'string', 'min:7', 'max:32'],
            'phone_secondary' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'tehsil' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'registration_notes' => ['nullable', 'string', 'max:1000'],
            'confirm_distinct' => ['nullable', 'boolean'],

            // Emergency or Guardian contact (required for minors in standard registration)
            'emergency_contact_type' => ['nullable', Rule::in(['emergency', 'guardian', 'next_of_kin'])],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:64'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'emergency_contact_cnic' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && trim((string) $value) !== '' && ! CnicNormalizer::isValid((string) $value)) {
                        $fail('Guardian / emergency contact CNIC must contain exactly 13 digits.');
                    }
                },
            ],
            'emergency_contact_address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $isEmergency = $this->input('registration_type') === Patient::REG_EMERGENCY;
            $dob = $this->input('date_of_birth');
            $estimatedAge = $this->input('estimated_age_years');

            if (! $isEmergency && empty($dob) && ($estimatedAge === null || $estimatedAge === '')) {
                $v->errors()->add('date_of_birth', 'Provide either Date of Birth or Estimated Age (Years).');
            }

            // Require guardian details for minors during standard registration
            $isMinor = false;
            if (! empty($dob)) {
                try {
                    $isMinor = Carbon::parse((string) $dob)->age < 18;
                } catch (\Throwable) {
                    $isMinor = false;
                }
            } elseif ($estimatedAge !== null && $estimatedAge !== '' && (int) $estimatedAge < 18) {
                $isMinor = true;
            }

            if ($isMinor && ! $isEmergency && empty($this->input('emergency_contact_name'))) {
                $v->errors()->add('emergency_contact_name', 'Guardian name is required for patients under 18 years of age.');
            }
        });
    }
}
