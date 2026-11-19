<?php

declare(strict_types=1);

namespace App\Modules\Patient\Services;

use App\Modules\Patient\Models\MrnSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MrnGeneratorService
{
    /**
     * Generate a permanent, non-recyclable Hospital Medical Record Number.
     * Format: {FACILITY_CODE}-{YEAR}-{6+ DIGIT SEQUENCE}, e.g. CHG-2026-000001.
     */
    public function generate(?string $facilityCode = null, ?int $year = null): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $facilityCode ?? (string) config('hmis.facility_code', 'CHG')) ?: 'CHG');
        $targetYear = $year ?? (int) Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->format('Y');

        $nextSequence = DB::transaction(function () use ($code, $targetYear): int {
            $row = MrnSequence::query()
                ->where('facility_code', $code)
                ->where('year', $targetYear)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    $row = MrnSequence::query()->create([
                        'facility_code' => $code,
                        'year' => $targetYear,
                        'last_sequence' => 1,
                    ]);

                    return 1;
                } catch (QueryException) {
                    // Handle concurrent first-of-year creation cleanly
                    $row = MrnSequence::query()
                        ->where('facility_code', $code)
                        ->where('year', $targetYear)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $row->last_sequence = $row->last_sequence + 1;
            $row->save();

            return (int) $row->last_sequence;
        });

        return sprintf('%s-%04d-%06d', $code, $targetYear, $nextSequence);
    }
}
