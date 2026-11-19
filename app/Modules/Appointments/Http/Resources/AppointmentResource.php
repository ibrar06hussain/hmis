<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Resources;

use App\Modules\Appointments\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'appointment_date' => $this->appointment_date?->format('Y-m-d'),
            'token_number' => $this->token_number,
            'formatted_token' => $this->formattedToken(),
            'visit_type' => $this->visit_type,
            'status' => $this->status,
            'chief_complaint' => $this->chief_complaint,
            'patient' => $this->whenLoaded('patient', fn (): array => [
                'id' => $this->patient->id,
                'uuid' => $this->patient->uuid,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ]),
            'department' => $this->whenLoaded('department', fn (): array => [
                'id' => $this->department->id,
                'code' => $this->department->code,
                'name' => $this->department->name,
            ]),
            'encounter_id' => $this->encounter_id,
            'encounter_uuid' => $this->whenLoaded('encounter', fn (): ?string => $this->encounter?->uuid),
            'checked_in_at_utc' => $this->checked_in_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
