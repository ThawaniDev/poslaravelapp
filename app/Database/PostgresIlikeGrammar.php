<?php

namespace App\Database;

use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * Postgres query grammar that transparently rewrites case-sensitive
 * `LIKE` / `NOT LIKE` operators to their case-insensitive `ILIKE` /
 * `NOT ILIKE` counterparts. This restores MySQL-compatible search
 * semantics without touching call sites scattered across the codebase.
 */
class PostgresIlikeGrammar extends PostgresGrammar
{
    protected function whereBasic(\Illuminate\Database\Query\Builder $query, $where): string
    {
        $op = strtolower($where['operator'] ?? '');
        if ($op === 'like') {
            $where['operator'] = 'ilike';
        } elseif ($op === 'not like') {
            $where['operator'] = 'not ilike';
        }
        return parent::whereBasic($query, $where);
    }
}
