<?php

declare(strict_types=1);

namespace App\Modules\Patient\Services;

use App\Modules\Patient\Models\Patient;
use App\Support\CnicNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PatientSearchService
{
    public function search(?string $term, ?string $status = 'active', int $perPage = 15): LengthAwarePaginator
    {
        $query = Patient::query()->withCount(['encounters', 'activeAllergies']);

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $trimmed = trim((string) $term);

        if ($trimmed !== '') {
            $digits = CnicNormalizer::normalize($trimmed);
            $normalized = Patient::normalizeSearchText($trimmed);

            $query->where(function (Builder $builder) use ($trimmed, $digits, $normalized): void {
                $builder->where('mrn', 'like', strtoupper($trimmed).'%')
                    ->orWhere('phone_primary', 'like', '%'.$trimmed.'%')
                    ->orWhereHas('identifiers', function (Builder $idQuery) use ($trimmed, $digits): void {
                        $idQuery->where('value', strtoupper($trimmed))
                            ->orWhere('value', $trimmed);
                        if ($digits !== null) {
                            $idQuery->orWhere('value', $digits);
                        }
                    });

                if ($normalized !== null) {
                    $builder->orWhere('normalized_name', 'like', '%'.$normalized.'%')
                        ->orWhere('normalized_father_name', 'like', '%'.$normalized.'%');
                }

                if ($digits !== null && strlen($digits) >= 5) {
                    $builder->orWhere('cnic_normalized', 'like', $digits.'%');
                }
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Resolve an MRN (including historical merged MRNs) to the canonical active patient.
     */
    public function resolveByMrn(string $mrn): ?Patient
    {
        $upper = strtoupper(trim($mrn));

        $direct = Patient::query()->where('mrn', $upper)->first();
        if ($direct !== null) {
            return $direct->canonicalPatient();
        }

        $alias = Patient::query()
            ->whereHas('identifiers', fn (Builder $q) => $q->where('system', 'merged_mrn')->where('value', $upper))
            ->first();

        return $alias?->canonicalPatient();
    }
}
