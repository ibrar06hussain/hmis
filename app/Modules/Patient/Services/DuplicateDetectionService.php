<?php

declare(strict_types=1);

namespace App\Modules\Patient\Services;

use App\Modules\Patient\Models\Patient;
use App\Support\CnicNormalizer;
use Illuminate\Support\Collection;

final class DuplicateDetectionService
{
    /**
     * Find potential duplicate active patients using deterministic clinical identity rules.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, array{patient: Patient, match_level: string, score: int, reasons: list<string>}>
     */
    public function findCandidates(array $attributes, ?int $ignorePatientId = null): Collection
    {
        $cnic = CnicNormalizer::normalize(isset($attributes['cnic']) ? (string) $attributes['cnic'] : null);
        $phone = isset($attributes['phone_primary']) ? trim((string) $attributes['phone_primary']) : null;
        $normalizedName = Patient::normalizeSearchText(isset($attributes['full_name']) ? (string) $attributes['full_name'] : null);
        $normalizedFather = Patient::normalizeSearchText(isset($attributes['father_or_spouse_name']) ? (string) $attributes['father_or_spouse_name'] : null);
        $dob = isset($attributes['date_of_birth']) && $attributes['date_of_birth'] !== '' ? (string) $attributes['date_of_birth'] : null;
        $sex = isset($attributes['sex']) ? (string) $attributes['sex'] : null;

        /** @var array<int, array{patient: Patient, match_level: string, score: int, reasons: list<string>}> $candidates */
        $candidates = [];

        $addCandidate = static function (Patient $patient, string $level, int $score, string $reason) use (&$candidates): void {
            $id = (int) $patient->id;
            if (! isset($candidates[$id])) {
                $candidates[$id] = [
                    'patient' => $patient,
                    'match_level' => $level,
                    'score' => $score,
                    'reasons' => [$reason],
                ];

                return;
            }

            if (! in_array($reason, $candidates[$id]['reasons'], true)) {
                $candidates[$id]['reasons'][] = $reason;
            }

            if ($score > $candidates[$id]['score']) {
                $candidates[$id]['score'] = $score;
                $candidates[$id]['match_level'] = $level;
            }
        };

        // 1. Hard Match: Exact 13-digit CNIC
        if ($cnic !== null && strlen($cnic) === 13) {
            Patient::query()
                ->active()
                ->when($ignorePatientId !== null, fn ($q) => $q->where('id', '!=', $ignorePatientId))
                ->where('cnic_normalized', $cnic)
                ->limit(5)
                ->get()
                ->each(fn (Patient $p) => $addCandidate($p, 'hard', 100, 'Exact CNIC match'));
        }

        // 2. Strong Match: Phone + Name
        if ($phone !== null && $phone !== '' && $normalizedName !== null) {
            $firstToken = explode(' ', $normalizedName)[0];
            Patient::query()
                ->active()
                ->when($ignorePatientId !== null, fn ($q) => $q->where('id', '!=', $ignorePatientId))
                ->where('phone_primary', $phone)
                ->where('normalized_name', 'like', $firstToken.'%')
                ->when($sex !== null && $sex !== 'unknown', fn ($q) => $q->where('sex', $sex))
                ->limit(5)
                ->get()
                ->each(fn (Patient $p) => $addCandidate($p, 'strong', 85, 'Primary phone and name match'));
        }

        // 3. Strong Match: Exact Name + DOB
        if ($normalizedName !== null && $dob !== null) {
            Patient::query()
                ->active()
                ->when($ignorePatientId !== null, fn ($q) => $q->where('id', '!=', $ignorePatientId))
                ->where('normalized_name', $normalizedName)
                ->whereDate('date_of_birth', $dob)
                ->limit(5)
                ->get()
                ->each(fn (Patient $p) => $addCandidate($p, 'strong', 80, 'Exact name and date of birth match'));
        }

        // 4. Possible Match: Name + Father/Spouse Name
        if ($normalizedName !== null && $normalizedFather !== null) {
            Patient::query()
                ->active()
                ->when($ignorePatientId !== null, fn ($q) => $q->where('id', '!=', $ignorePatientId))
                ->where('normalized_name', $normalizedName)
                ->where('normalized_father_name', $normalizedFather)
                ->limit(5)
                ->get()
                ->each(fn (Patient $p) => $addCandidate($p, 'possible', 65, 'Full name and father/spouse name match'));
        }

        return collect($candidates)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * @param  Collection<int, array{patient: Patient, match_level: string, score: int, reasons: list<string>}>  $candidates
     */
    public function hasHardMatch(Collection $candidates): bool
    {
        return $candidates->contains(fn (array $item): bool => $item['match_level'] === 'hard');
    }
}
