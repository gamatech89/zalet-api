<?php

namespace App\Rules;

use App\Support\JsonMapping\JsonTypeConverter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class ResolvableJsonType implements ValidationRule
{
    public function __construct(
        private string $baseClass,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be a valid JSON object.');
            return;
        }

        try {
            JsonTypeConverter::fromArray($this->baseClass, $value);
        } catch (InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}
