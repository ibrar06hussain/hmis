<?php

declare(strict_types=1);

namespace App\Modules\Emr\Models;

use Illuminate\Database\Eloquent\Model;

class EncounterSequence extends Model
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
