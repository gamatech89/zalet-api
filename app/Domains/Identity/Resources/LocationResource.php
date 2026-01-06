<?php

declare(strict_types=1);

namespace App\Domains\Identity\Resources;

use App\Domains\Shared\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Domains\Identity\Models\Location
 */
final class LocationResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'city' => $this->city,
            'country' => $this->country,
            'countryCode' => $this->country_code,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
        ];
    }
}
