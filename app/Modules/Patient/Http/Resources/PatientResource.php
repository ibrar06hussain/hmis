<?php

declare(strict_types=1);

namespace App\Modules\Patient\Http\Resources;

use App\Modules\Patient\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Patient
 */
class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'mrn' => $this->mrn,
            'status' => $this->status,
            'full_name' => $this->full_name,
            'father_or_spouse_name' => $this->father_or_spouse_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'dob_estimated' => $this->dob_estimated,
            'age' => $this->ageDisplay(),
            'sex' => $this->sex,
            'blood_group' => $this->blood_group,
            'cnic' => $this->formattedCnic(),
            'phone_primary' => $this->phone_primary,
            'district' => $this->district,
            'province' => $this->province,
            'registration_type' => $this->registration_type,
            'is_unknown_emergency' => $this->is_unknown_emergency,
            'identity_verification_status' => $this->identity_verification_status,
            'version' => $this->version,
            'created_at_utc' => $this->created_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
