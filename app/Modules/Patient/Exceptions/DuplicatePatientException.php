<?php

declare(strict_types=1);

namespace App\Modules\Patient\Exceptions;

use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Collection;
use RuntimeException;

final class DuplicatePatientException extends RuntimeException
{
    /**
     * @param  Collection<int, array{patient: Patient, match_level: string, score: int, reasons: list<string>}>  $candidates
     */
    public function __construct(
        public readonly Collection $candidates,
        string $message = 'Potential duplicate patient detected. Review matching records before proceeding.',
    ) {
        parent::__construct($message);
    }
}
