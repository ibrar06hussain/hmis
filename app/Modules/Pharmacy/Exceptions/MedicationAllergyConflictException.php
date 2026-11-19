<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Exceptions;

use RuntimeException;

final class MedicationAllergyConflictException extends RuntimeException
{
    /**
     * @param  list<string>  $conflictingAllergies
     */
    public function __construct(
        public readonly array $conflictingAllergies,
        string $message = 'Active medication allergy conflict detected.',
    ) {
        parent::__construct($message);
    }
}
