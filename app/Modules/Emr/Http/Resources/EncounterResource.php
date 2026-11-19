<?php

declare(strict_types=1);

namespace App\Modules\Emr\Http\Resources;

use App\Modules\Emr\Models\Encounter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Encounter
 */
class EncounterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'encounter_number' => $this->encounter_number,
            'class' => $this->class,
            'status' => $this->status,
            'chief_complaint' => $this->chief_complaint,
            'patient_id' => $this->patient_id,
            'department_id' => $this->department_id,
            'started_at_utc' => $this->started_at?->setTimezone('UTC')->toIso8601String(),
            'ended_at_utc' => $this->ended_at?->setTimezone('UTC')->toIso8601String(),
            'version' => $this->version,
        ];
    }
}
