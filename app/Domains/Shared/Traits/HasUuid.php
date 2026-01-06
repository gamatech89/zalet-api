<?php

declare(strict_types=1);

namespace App\Domains\Shared\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait for models that use UUID as public identifier.
 *
 * @mixin Model
 */
trait HasUuid
{
    /**
     * Boot the trait.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            // @phpstan-ignore-next-line
            $column = $model->getUuidColumn();
            if (empty($model->{$column})) {
                $model->{$column} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the column name for UUID.
     */
    public function getUuidColumn(): string
    {
        return 'uuid';
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return $this->getUuidColumn();
    }

    /**
     * Scope to find by UUID.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @param string $uuid
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByUuid($query, string $uuid)
    {
        return $query->where($this->getUuidColumn(), $uuid);
    }
}
