<?php

namespace App\Domain\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Provides a helper to scope Eloquent/query-builder queries by store_id.
 * Accepts a single ID (string) or multiple IDs (array) for multi-store support.
 */
trait ScopesStoreQuery
{
    /**
     * Scope a query by store_id — single ID uses WHERE, array uses WHERE IN.
     */
    protected function scopeByStore(Builder|QueryBuilder $query, string|array $storeId, string $column = 'store_id'): Builder|QueryBuilder
    {
        return is_array($storeId)
            ? $query->whereIn($column, $storeId)
            : $query->where($column, $storeId);
    }
}
