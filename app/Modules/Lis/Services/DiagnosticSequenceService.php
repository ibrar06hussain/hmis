<?php

declare(strict_types=1);

namespace App\Modules\Lis\Services;

use App\Modules\Lis\Models\DiagnosticSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DiagnosticSequenceService
{
    public function next(string $prefix, ?string $facilityCode = null): string
    {
        $code = strtoupper($facilityCode ?? (string) config('hmis.facility_code', 'CHG'));
        $cleanPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?: 'LAB');
        $year = (int) Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->format('Y');

        $seq = DB::transaction(function () use ($code, $cleanPrefix, $year): int {
            $row = DiagnosticSequence::query()
                ->where('facility_code', $code)
                ->where('prefix', $cleanPrefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DiagnosticSequence::query()->create([
                        'facility_code' => $code,
                        'prefix' => $cleanPrefix,
                        'year' => $year,
                        'last_sequence' => 1,
                    ]);

                    return 1;
                } catch (QueryException) {
                    $row = DiagnosticSequence::query()
                        ->where('facility_code', $code)
                        ->where('prefix', $cleanPrefix)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $row->last_sequence = $row->last_sequence + 1;
            $row->save();

            return (int) $row->last_sequence;
        });

        return sprintf('%s-%04d-%06d', $cleanPrefix, $year, $seq);
    }
}
