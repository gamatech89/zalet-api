<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for a single gift from the catalog.
 *
 * @property string $id
 * @property string $name
 * @property int $credits
 * @property string $icon
 * @property string $animation
 */
final class GiftResource extends JsonResource
{
    /**
     * Create a resource from gift catalog data.
     *
     * @param string $id Gift type identifier
     * @param array{name: string, credits: int, icon: string, animation: string} $gift Gift data
     * @return self
     */
    public static function fromCatalog(string $id, array $gift): self
    {
        return new self((object) [
            'id' => $id,
            'name' => $gift['name'],
            'credits' => $gift['credits'],
            'icon' => $gift['icon'],
            'animation' => $gift['animation'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'credits' => $this->credits,
            'icon' => $this->icon,
            'animation' => $this->animation,
        ];
    }
}
