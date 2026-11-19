<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('encounter.create_opd') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'clinician_staff_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'appointment_date' => ['required', 'date'],
            'visit_type' => ['required', Rule::in(['new', 'follow_up', 'urgent'])],
            'chief_complaint' => ['nullable', 'string', 'max:255'],
        ];
    }
}
