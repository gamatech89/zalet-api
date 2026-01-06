<?php

declare(strict_types=1);

namespace App\Domains\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base resource class with camelCase transformation.
 */
abstract class BaseResource extends JsonResource
{
    /**
     * Transform keys to camelCase.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function toCamelCase(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $camelKey = $this->snakeToCamel((string) $key);

            if (is_array($value)) {
                $result[$camelKey] = $this->toCamelCase($value);
            } else {
                $result[$camelKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert snake_case to camelCase.
     */
    protected function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }

    /**
     * Standard wrapper for successful responses.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}
