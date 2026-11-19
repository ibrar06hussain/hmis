<?php

declare(strict_types=1);

namespace App\Modules\Ipd\Models;

use App\Models\User;
use App\Modules\Organization\Models\Bed;
use App\Modules\Organization\Models\Ward;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedAllocation extends Model
{
    protected $fillable = [
        'admission_id',
        'patient_id',
        'ward_id',
        'bed_id',
        'allocated_at',
        'released_at',
        'allocated_by_user_id',
        'transfer_reason',
    ];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(IpdAdmission::class, 'admission_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_user_id');
    }
}
