<?php

declare(strict_types=1);

namespace App\Modules\Emr\Services;

use App\Modules\Emr\Models\EncounterSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EncounterNumberGeneratorService
{
    public function generate(?string $facilityCode = null, ?int $year = null): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $facilityCode ?? (string) config('hmis.facility_code', 'CHG')) ?: 'CHG');
        $targetYear = $year ?? (int) Carbon::now(config('hmis.display_timezone', 'Asia/Karachi'))->format('Y');

        $nextSequence = DB::transaction(function () use ($code, $targetYear): int {
            $row = EncounterSequence::query()
                ->where('facility_code', $code)
                ->where('year', $targetYear)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    EncounterSequence::query()->create([
                        'facility_code' => $code,
                        'year' => $targetYear,
                        'last_sequence' => 1,
                    ]);

                    return 1;
                } catch (QueryException) {
                    $row = EncounterSequence::query()
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

        return sprintf('ENC-%04d-%06d', $targetYear, $nextSequence);
    }
}
