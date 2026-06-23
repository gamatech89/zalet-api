<?php

namespace App\Support\JsonMapping;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class JsonTypeCast implements CastsAttributes
{
    public function __construct(
        private string $baseClass,
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?object
    {
        if ($value === null) {
            return null;
        }

        return JsonTypeConverter::fromArray($this->baseClass, json_decode($value, true));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = JsonTypeConverter::fromArray($this->baseClass, $value);
        }

        return json_encode(JsonTypeConverter::toArray($value));
    }
}
