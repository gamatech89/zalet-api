<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class SearchLocationsAction
{
    /**
     * Search locations by city or country name.
     *
     * @return LengthAwarePaginator<int, Location>
     */
    public function execute(
        ?string $query = null,
        ?string $countryCode = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Location::query()
            ->when($query !== null && $query !== '', function (Builder $q) use ($query): void {
                /** @var string $query */
                $q->where(function (Builder $subQuery) use ($query): void {
                    $searchTerm = '%' . mb_strtolower($query) . '%';
                    $subQuery->whereRaw('LOWER(city) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(country) LIKE ?', [$searchTerm]);
                });
            })
            ->when($countryCode !== null && $countryCode !== '', function (Builder $q) use ($countryCode): void {
                /** @var string $countryCode */
                $q->where('country_code', mb_strtoupper($countryCode));
            })
            ->orderBy('country')
            ->orderBy('city')
            ->paginate($perPage);
    }
}
