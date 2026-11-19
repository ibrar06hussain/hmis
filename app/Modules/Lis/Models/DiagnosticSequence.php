<?php

declare(strict_types=1);

namespace App\Modules\Lis\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticSequence extends Model
{
    protected $fillable = [
        'facility_code',
        'prefix',
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
