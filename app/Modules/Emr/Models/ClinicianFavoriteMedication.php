<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use App\Models\User;
use App\Modules\Pharmacy\Models\Medication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicianFavoriteMedication extends Model
{
    protected $fillable = [
        'user_id',
        'medication_id',
        'default_dose',
        'default_route',
        'default_frequency',
        'default_duration_days',
        'default_meal_timing',
        'default_instructions',
    ];

    protected function casts(): array
    {
        return [
            'default_duration_days' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
