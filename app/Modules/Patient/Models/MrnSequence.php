<?php

declare(strict_types=1);

namespace App\Modules\Patient\Models;

use Illuminate\Database\Eloquent\Model;

class MrnSequence extends Model
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
