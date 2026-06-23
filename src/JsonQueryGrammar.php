<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

class JsonQueryGrammar extends Grammar
{
    public function compileSelect(Builder $query): string
    {
        $wheres = $this->serializeWheres($query);

        $orders = array_map(function (array $order): array {
            return [
                'column' => $order['column'] ?? '',
                'direction' => $order['direction'] ?? 'asc',
            ];
        }, $query->orders ?? []);

        $operation = [
            'type' => 'select',
            'table' => $query->from,
            'columns' => $this->serializeColumns($query),
            'wheres' => $wheres,
            'orders' => $orders,
            'limit' => $query->limit,
            'offset' => $query->offset,
        ];

        if ($query->aggregate) {
            $operation['aggregate'] = $query->aggregate;
        }

        return json_encode($operation);
    }

    private function serializeColumns(Builder $query): array
    {
        $columns = $query->columns ?? ['*'];
        $result = [];

        foreach ($columns as $column) {
            if (is_string($column)) {
                $result[] = $column;
                continue;
            }

            if ($column instanceof \Illuminate\Database\Query\Expression) {
                $value = (string) $column->getValue($this);

                if (preg_match('/^\((.+)\)\s+as\s+"?([^"]+)"?$/s', $value, $matches)) {
                    $subquery = json_decode($matches[1], true);

                    if (is_array($subquery)) {
                        $result[] = ['subquery_count' => ['subquery' => $subquery, 'alias' => $matches[2]]];
                        continue;
                    }
                }

                $result[] = ['expression' => $value];
            }
        }

        return $result;
    }

    public function compileInsert(Builder $query, array $values): string
    {
        if (! is_array(reset($values))) {
            $values = [$values];
        }

        return json_encode([
            'type' => 'insert',
            'table' => $query->from,
            'values' => $values,
        ]);
    }

    public function compileUpdate(Builder $query, array $values): string
    {
        return json_encode([
            'type' => 'update',
            'table' => $query->from,
            'values' => $values,
            'wheres' => $this->serializeWheres($query),
        ]);
    }

    public function compileDelete(Builder $query): string
    {
        return json_encode([
            'type' => 'delete',
            'table' => $query->from,
            'wheres' => $this->serializeWheres($query),
        ]);
    }

    private function serializeWheres(Builder $query): array
    {
        if (empty($query->wheres)) {
            return [];
        }

        return array_map(function (array $where): array {
            return match ($where['type']) {
                'Basic' => [
                    'type' => 'Basic',
                    'column' => $where['column'],
                    'operator' => $where['operator'],
                    'value' => $where['value'],
                    'boolean' => $where['boolean'],
                ],
                'In' => [
                    'type' => 'In',
                    'column' => $where['column'],
                    'operator' => 'in',
                    'value' => $where['values'],
                    'boolean' => $where['boolean'],
                ],
                'NotIn' => [
                    'type' => 'NotIn',
                    'column' => $where['column'],
                    'operator' => 'not in',
                    'value' => $where['values'],
                    'boolean' => $where['boolean'],
                ],
                'Null' => [
                    'type' => 'Null',
                    'column' => $where['column'],
                    'operator' => 'is null',
                    'value' => null,
                    'boolean' => $where['boolean'],
                ],
                'NotNull' => [
                    'type' => 'NotNull',
                    'column' => $where['column'],
                    'operator' => 'is not null',
                    'value' => null,
                    'boolean' => $where['boolean'],
                ],
                'Exists' => [
                    'type' => 'Exists',
                    'subquery' => $this->compileSubquery($where['query']),
                    'boolean' => $where['boolean'],
                ],
                'NotExists' => [
                    'type' => 'NotExists',
                    'subquery' => $this->compileSubquery($where['query']),
                    'boolean' => $where['boolean'],
                ],
                'InRaw' => [
                    'type' => 'In',
                    'column' => $where['column'],
                    'operator' => 'in',
                    'value' => $where['values'] ?? [],
                    'boolean' => $where['boolean'],
                ],
                'NotInRaw' => [
                    'type' => 'NotIn',
                    'column' => $where['column'],
                    'operator' => 'not in',
                    'value' => $where['values'] ?? [],
                    'boolean' => $where['boolean'],
                ],
                default => $where,
            };
        }, $query->wheres);
    }

    private function compileSubquery(Builder $query): array
    {
        $serialized = $this->compileSelect($query);
        $decoded = json_decode($serialized, true);

        return is_array($decoded) ? $decoded : [];
    }
}
