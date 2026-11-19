<?php

declare(strict_types=1);

namespace App\Modules\Terminology\Services;

use App\Modules\Terminology\Models\TerminologyCode;
use Illuminate\Database\Eloquent\Collection;

final class TerminologySearchService
{
    /**
     * 100% offline search across official ICD-10 / ICD-11 codes, display names, and local clinical aliases.
     *
     * @return Collection<int, TerminologyCode>
     */
    public function search(?string $query = null, ?string $system = null, int $limit = 25): Collection
    {
        $term = strtolower(trim((string) $query));

        $codes = TerminologyCode::query()
            ->where('is_active', true)
            ->when($system !== null && $system !== '' && $system !== 'all', fn ($q) => $q->where('coding_system', $system))
            ->orderByDesc('is_cardiology_core')
            ->orderBy('coding_system')
            ->orderBy('code')
            ->get();

        if ($term === '') {
            return $codes->take($limit)->values();
        }

        return $codes
            ->filter(function (TerminologyCode $item) use ($term): bool {
                if (str_contains(strtolower($item->code), $term)) {
                    return true;
                }
                if (str_contains($item->normalized_display, $term)) {
                    return true;
                }

                foreach ($item->aliases ?? [] as $alias) {
                    if (str_contains(strtolower((string) $alias), $term)) {
                        return true;
                    }
                }

                return false;
            })
            ->take($limit)
            ->values();
    }
}
