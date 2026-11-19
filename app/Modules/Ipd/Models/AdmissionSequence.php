<?php

declare(strict_types=1);

namespace App\Modules\Ipd\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionSequence extends Model
{
    protected $fillable = [
        'facility_code',
        'year',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_sequence' => 'integer',
        ];
    }
}
