<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver;

use Gonza1212\JsonDriver\Storage\JsonStorage;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Schema\Grammars\Grammar as SchemaGrammar;
use Illuminate\Support\Collection;

class JsonConnection extends Connection
{
    private JsonStorage $storage;

    private JsonPdo $jsonPdo;

    public function __construct(JsonPdo $pdo, string $database = '', string $tablePrefix = '', array $config = [])
    {
        $this->storage = new JsonStorage($database);
        $this->jsonPdo = $pdo;

        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    public function getName(): string
    {
        return 'json';
    }

    /**
     * @return JsonQueryGrammar
     */
    public function getQueryGrammar()
    {
        /* @phpstan-ignore return.type */
        return $this->queryGrammar;
    }

    /**
     * @return JsonSchemaGrammar
     */
    public function getSchemaGrammar()
    {
        /* @phpstan-ignore isset.property */
        if (! isset($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        /* @phpstan-ignore return.type */
        return $this->schemaGrammar;
    }

    protected function getDefaultQueryGrammar(): Grammar
    {
        return new JsonQueryGrammar($this);
    }

    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return new JsonSchemaGrammar($this);
    }

    public function getSchemaBuilder(): \Illuminate\Database\Schema\Builder
    {
        return new \Illuminate\Database\Schema\Builder($this);
    }

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): array
    {
        $operation = json_decode($query, true);

        if (! is_array($operation)) {
            return [];
        }

        if (($operation['op'] ?? null) === 'list_tables') {
            return $this->storage->listTables();
        }

        $table = $operation['table'];
        $columns = $operation['columns'] ?? ['*'];
        $wheres = $operation['wheres'] ?? [];
        $orders = $operation['orders'] ?? [];
        $limit = $operation['limit'] ?? null;
        $offset = $operation['offset'] ?? null;

        $rows = $this->storage->get($table);
        $rows = $this->applyPivotHydration($rows, $columns, $wheres, $table);
        $wheres = $this->resolvePivotWheres($table, $wheres);
        $wheres = $this->resolveExistsWheres($table, $wheres);
        $rows = $this->applyWheres($rows, $wheres);
        $rows = $this->applyOrders($rows, $orders);

        if ($offset !== null) {
            $rows = $rows->slice($offset);
        }

        if ($limit !== null) {
            $rows = $rows->take($limit);
        }

        if (isset($operation['aggregate'])) {
            $aggFunc = $operation['aggregate']['function'];
            $aggCols = $operation['aggregate']['columns'] ?? ['*'];

            $aggregate = match ($aggFunc) {
                'count' => $rows->count(),
                'sum' => $rows->sum($aggCols !== ['*'] ? $aggCols[0] : null),
                'avg' => $rows->avg($aggCols !== ['*'] ? $aggCols[0] : null),
                'min' => $rows->min($aggCols !== ['*'] ? $aggCols[0] : null),
                'max' => $rows->max($aggCols !== ['*'] ? $aggCols[0] : null),
                default => 0,
            };

            return [(object) ['aggregate' => $aggregate]];
        }

        $rows = $this->applySubqueryCounts($rows, $columns, $table);

        $selectAll = empty($columns) || $columns === ['*'];

        return $rows->map(function (array $row) use ($columns, $selectAll, $table): object {
            if ($selectAll) {
                return (object) $row;
            }

            $selected = [];
            foreach ($columns as $column) {
                $selected = array_merge($selected, $this->projectColumn($column, $row, $table));
            }

            return (object) $selected;
        })->values()->all();
    }

    private function applyPivotHydration(Collection $rows, array $columns, array $wheres, string $table): Collection
    {
        $pivotTable = null;
        $pivotAliases = [];

        foreach ($columns as $column) {
            if (! is_string($column)) {
                continue;
            }
            if (preg_match('/^(\w+)\.(\w+)\s+as\s+(pivot_\w+)$/i', trim($column), $matches)) {
                $pivotTable = $matches[1];
                $pivotAliases[$matches[3]] = $matches[2];
            }
        }

        if ($pivotTable === null || empty($pivotAliases)) {
            return $rows;
        }

        $parentFkWhere = null;
        foreach ($wheres as $w) {
            $col = (string) ($w['column'] ?? '');
            if (str_starts_with($col, $pivotTable . '.')) {
                $parentFkWhere = $w;
                break;
            }
        }

        if ($parentFkWhere === null) {
            return $rows;
        }

        $parentFkCol = explode('.', (string) $parentFkWhere['column'], 2)[1] ?? 'id';
        $parentFkValue = $parentFkWhere['value'] ?? null;
        $operator = $parentFkWhere['operator'] ?? '=';

        $pivotRows = $this->storage->get($pivotTable);
        $matched = $pivotRows->filter(function (array $row) use ($parentFkCol, $parentFkValue, $operator): bool {
            $rowValue = $row[$parentFkCol] ?? null;
            return match ($operator) {
                '=' => $rowValue == $parentFkValue,
                'in' => in_array($rowValue, (array) $parentFkValue, false),
                default => $rowValue == $parentFkValue,
            };
        });

        $pivotSchema = $this->storage->getSchema($pivotTable);
        $foreignKeys = $pivotSchema['foreign_keys'] ?? [];
        $targetFkCol = null;
        foreach ($foreignKeys as $fk) {
            if (($fk['references_table'] ?? null) === $table) {
                $targetFkCol = $fk['column'];
                break;
            }
        }

        if ($targetFkCol === null) {
            return $rows;
        }

        $pivotByTargetId = [];
        foreach ($matched as $pRow) {
            $targetId = $pRow[$targetFkCol] ?? null;
            if ($targetId === null) {
                continue;
            }
            $pivotByTargetId[$targetId] = $pRow;
        }

        return $rows->map(function (array $row) use ($pivotAliases, $pivotByTargetId): array {
            $rowId = $row['id'] ?? null;
            $pivot = $pivotByTargetId[$rowId] ?? null;

            if ($pivot === null) {
                return $row;
            }

            foreach ($pivotAliases as $alias => $colName) {
                $row[$alias] = $pivot[$colName] ?? null;
            }

            return $row;
        });
    }

    private function projectColumn(mixed $column, array $row, string $table): array
    {
        if (is_array($column)) {
            return [];
        }

        $stripped = trim((string) $column);

        if (str_contains($stripped, ' as ')) {
            [$expression, $alias] = preg_split('/\s+as\s+/i', $stripped, 2);
            $expression = trim($expression);
            $alias = trim($alias);

            if (array_key_exists($alias, $row)) {
                return [$alias => $row[$alias]];
            }

            $key = str_contains($expression, '.')
                ? substr($expression, strrpos($expression, '.') + 1)
                : $expression;

            return [$alias => $row[$key] ?? null];
        }

        if (str_ends_with($stripped, '.*')) {
            $selected = [];
            foreach ($row as $k => $v) {
                $selected[$k] = $v;
            }
            return $selected;
        }

        $key = str_contains($stripped, '.')
            ? substr($stripped, strrpos($stripped, '.') + 1)
            : $stripped;

        return [$key => $row[$key] ?? null];
    }

    public function selectOne($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): mixed
    {
        $results = $this->select($query, $bindings, $useReadPdo, $fetchUsing);

        return ! empty($results) ? $results[0] : null;
    }

    public function insert($query, $bindings = []): bool
    {
        $operation = json_decode($query, true);

        if (! is_array($operation)) {
            return false;
        }

        $table = $operation['table'];
        $values = $operation['values'];

        $rows = $this->storage->get($table);

        $maxId = (int) $rows->max('id');

        foreach ($values as &$value) {
            if (! isset($value['id'])) {
                $maxId++;
                $value['id'] = $maxId;
            }
        }
        unset($value);

        foreach ($values as $value) {
            $rows->push($value);
        }

        $this->storage->put($table, $rows);

        $lastRow = $values[count($values) - 1];
        $this->jsonPdo->lastInsertedId = isset($lastRow['id']) ? (string) $lastRow['id'] : '0';

        return true;
    }

    public function update($query, $bindings = []): int
    {
        $operation = json_decode($query, true);

        if (! is_array($operation)) {
            return 0;
        }

        $table = $operation['table'];
        $values = $this->normalizeValueKeys($operation['values']);
        $wheres = $operation['wheres'] ?? [];

        $allRows = $this->storage->get($table);
        $matchedRows = $this->applyWheres($allRows, $wheres);

        $affected = 0;
        $matchedIds = $matchedRows->pluck('id')->toArray();

        $updatedRows = $allRows->map(function (array $row) use ($matchedIds, $values, &$affected): array {
            if (in_array($row['id'] ?? null, $matchedIds, true)) {
                foreach ($values as $key => $value) {
                    $row[$key] = $value;
                }
                $affected++;
            }

            return $row;
        });

        $this->storage->put($table, $updatedRows);

        return $affected;
    }

    public function delete($query, $bindings = []): int
    {
        $operation = json_decode($query, true);

        if (! is_array($operation)) {
            return 0;
        }

        $table = $operation['table'];
        $wheres = $operation['wheres'] ?? [];

        $allRows = $this->storage->get($table);

        if (empty($wheres)) {
            $idsToDelete = $allRows->pluck('id')->all();
        } else {
            $matchedRows = $this->applyWheres($allRows, $wheres);
            $idsToDelete = $matchedRows->pluck('id')->all();
        }

        if (empty($idsToDelete)) {
            return 0;
        }

        $this->checkForeignKeyConstraints($table, $idsToDelete);
        $this->cascadeDelete($table, $idsToDelete);

        if (empty($wheres)) {
            $count = $allRows->count();
            $this->storage->put($table, collect());

            return $count;
        }

        $matchedIds = $idsToDelete;

        $remainingRows = $allRows->reject(function (array $row) use ($matchedIds): bool {
            return in_array($row['id'] ?? null, $matchedIds, true);
        });

        $affected = $allRows->count() - $remainingRows->count();

        $this->storage->put($table, $remainingRows);

        return $affected;
    }

    private function checkForeignKeyConstraints(string $table, array $idsToDelete): void
    {
        foreach ($this->storage->listTables() as $tableInfo) {
            $otherTable = $tableInfo->name;

            if ($otherTable === $table) {
                continue;
            }

            $schema = $this->storage->getSchema($otherTable);
            $foreignKeys = $schema['foreign_keys'] ?? [];

            foreach ($foreignKeys as $fk) {
                if (($fk['references_table'] ?? null) !== $table) {
                    continue;
                }

                $relatedRows = $this->storage->get($otherTable);
                $blockingRows = $relatedRows->filter(function (array $row) use ($fk, $idsToDelete): bool {
                    return in_array($row[$fk['column']] ?? null, $idsToDelete, true);
                });

                if ($blockingRows->isEmpty()) {
                    continue;
                }

                if (($fk['on_delete'] ?? 'restrict') === 'restrict') {
                    throw new \RuntimeException(
                        "No se puede eliminar el registro de '{$table}' porque existen registros relacionados en '{$otherTable}' ({$fk['column']})."
                    );
                }
            }
        }
    }

    private function cascadeDelete(string $table, array $idsToDelete): void
    {
        foreach ($this->storage->listTables() as $tableInfo) {
            $otherTable = $tableInfo->name;

            if ($otherTable === $table) {
                continue;
            }

            $schema = $this->storage->getSchema($otherTable);
            $foreignKeys = $schema['foreign_keys'] ?? [];

            foreach ($foreignKeys as $fk) {
                if (($fk['references_table'] ?? null) !== $table) {
                    continue;
                }

                if (($fk['on_delete'] ?? 'restrict') !== 'cascade') {
                    continue;
                }

                $relatedRows = $this->storage->get($otherTable);
                $relatedIds = $relatedRows
                    ->filter(fn (array $row): bool => in_array($row[$fk['column']] ?? null, $idsToDelete, true))
                    ->pluck('id')
                    ->all();

                if (empty($relatedIds)) {
                    continue;
                }

                $this->checkForeignKeyConstraints($otherTable, $relatedIds);
                $this->cascadeDelete($otherTable, $relatedIds);

                $remainingRows = $relatedRows->reject(function (array $row) use ($relatedIds): bool {
                    return in_array($row['id'] ?? null, $relatedIds, true);
                });

                $this->storage->put($otherTable, $remainingRows);
            }
        }
    }

    public function statement($query, $bindings = []): bool
    {
        $operation = json_decode($query, true);

        if (! is_array($operation)) {
            return false;
        }

        return match ($operation['type']) {
            'create' => $this->executeCreate($operation),
            'add_column' => $this->executeAddColumn($operation),
            'drop_column' => $this->executeDropColumn($operation),
            'rename' => $this->executeRename($operation),
            'drop' => $this->executeDrop($operation),
            'drop_if_exists' => $this->executeDropIfExists($operation),
            default => false,
        };
    }

    private function executeCreate(array $operation): bool
    {
        $table = $operation['table'];
        $columns = $operation['columns'];

        if ($this->storage->tableExists($table)) {
            throw new \RuntimeException("Table [{$table}] already exists.");
        }

        $schema = [
            'table' => $table,
            'columns' => $columns,
        ];

        if (isset($operation['foreign_keys']) && ! empty($operation['foreign_keys'])) {
            $schema['foreign_keys'] = $operation['foreign_keys'];
        }

        $this->storage->putSchema($table, $schema);
        $this->storage->put($table, collect());

        return true;
    }

    private function executeAddColumn(array $operation): bool
    {
        $table = $operation['table'];
        $newColumns = $operation['columns'];

        $schema = $this->storage->getSchema($table);
        $existingColumns = $schema['columns'] ?? [];

        foreach ($newColumns as $col) {
            $existingColumns[] = $col;
        }

        $schema['columns'] = $existingColumns;

        $newForeignKeys = $operation['foreign_keys'] ?? [];
        if (! empty($newForeignKeys)) {
            $existingForeignKeys = $schema['foreign_keys'] ?? [];
            $schema['foreign_keys'] = array_merge($existingForeignKeys, $newForeignKeys);
        }

        $this->storage->putSchema($table, $schema);

        $rows = $this->storage->get($table);

        $defaults = [];
        foreach ($newColumns as $col) {
            $defaults[$col['name']] = $col['default'] ?? null;
        }

        $rows = $rows->map(function (array $row) use ($defaults): array {
            foreach ($defaults as $name => $default) {
                $row[$name] = $default;
            }

            return $row;
        });

        $this->storage->put($table, $rows);

        return true;
    }

    private function executeDropColumn(array $operation): bool
    {
        $table = $operation['table'];
        $dropColumns = $operation['columns'];

        $this->storage->snapshot($table);

        $schema = $this->storage->getSchema($table);
        $existingColumns = $schema['columns'] ?? [];

        $schema['columns'] = array_values(array_filter(
            $existingColumns,
            fn (array $col): bool => ! in_array($col['name'], $dropColumns, true),
        ));

        $this->storage->putSchema($table, $schema);

        $rows = $this->storage->get($table);

        $rows = $rows->map(function (array $row) use ($dropColumns): array {
            foreach ($dropColumns as $col) {
                unset($row[$col]);
            }

            return $row;
        });

        $this->storage->put($table, $rows);

        return true;
    }

    private function executeRename(array $operation): bool
    {
        $from = $operation['from'];
        $to = $operation['to'];

        $this->storage->snapshot($from);

        $data = $this->storage->get($from);
        $schema = $this->storage->getSchema($from);

        if (! empty($schema)) {
            $schema['table'] = $to;
            $this->storage->putSchema($to, $schema);
        }

        $this->storage->put($to, $data);

        $this->storage->dropTable($from);

        return true;
    }

    private function executeDrop(array $operation): bool
    {
        $table = $operation['table'];

        $this->storage->snapshot($table);
        $this->storage->dropTable($table);

        return true;
    }

    private function executeDropIfExists(array $operation): bool
    {
        $table = $operation['table'];

        if (! $this->storage->tableExists($table)) {
            return true;
        }

        $this->storage->snapshot($table);
        $this->storage->dropTable($table);

        return true;
    }

    private function applyWheres(Collection $rows, array $wheres): Collection
    {
        $groups = $this->splitWhereGroups($wheres);

        if (empty($groups)) {
            return $rows;
        }

        $results = collect();

        foreach ($groups as $group) {
            $groupResult = $rows;

            foreach ($group as $where) {
                $groupResult = $this->applySingleWhere($groupResult, $where);
            }

            $results = $results->merge($groupResult);
        }

        return $results->unique(function (array $row): string {
            return (string) ($row['id'] ?? spl_object_id((object) $row));
        })->values();
    }

    private function resolvePivotWheres(string $currentTable, array $wheres): array
    {
        $rewritten = [];

        foreach ($wheres as $where) {
            $type = $where['type'] ?? 'Basic';
            $column = (string) ($where['column'] ?? '');

            if (in_array($type, ['Basic', 'In', 'NotIn'], true)
                && str_contains($column, '.')
                && ! str_contains($column, ' as ')
            ) {
                [$prefix, $col] = explode('.', $column, 2);

                if ($prefix !== $currentTable) {
                    $rewritten[] = $this->resolvePivotWhere($currentTable, $prefix, $col, $where);
                    continue;
                }
            }

            $rewritten[] = $where;
        }

        return $rewritten;
    }

    private function resolvePivotWhere(string $currentTable, string $pivotTable, string $pivotCol, array $where): array
    {
        $pivotRows = $this->storage->get($pivotTable);

        $matched = $pivotRows->filter(function (array $row) use ($pivotCol, $where): bool {
            $rowValue = $row[$pivotCol] ?? null;
            $type = $where['type'] ?? 'Basic';
            $operator = $where['operator'] ?? '=';
            $value = $where['value'] ?? null;

            if ($type === 'In' || $operator === 'in') {
                return in_array($rowValue, (array) $value, false);
            }

            if ($type === 'NotIn' || $operator === 'not in') {
                return ! in_array($rowValue, (array) $value, false);
            }

            return match ($operator) {
                '=' => $rowValue == $value,
                '!=' => $rowValue != $value,
                '<>' => $rowValue != $value,
                default => false,
            };
        });

        $pivotSchema = $this->storage->getSchema($pivotTable);
        $foreignKeys = $pivotSchema['foreign_keys'] ?? [];
        $targetFkCol = null;

        foreach ($foreignKeys as $fk) {
            if (($fk['references_table'] ?? null) === $currentTable) {
                $targetFkCol = $fk['column'];
                break;
            }
        }

        $targetIds = $targetFkCol === null
            ? $matched->pluck('id')->all()
            : $matched->pluck($targetFkCol)->all();

        $targetIds = array_values(array_unique(array_filter($targetIds, fn ($v) => $v !== null)));

        return [
            'type' => 'In',
            'column' => 'id',
            'operator' => 'in',
            'value' => $targetIds,
            'boolean' => $where['boolean'] ?? 'and',
        ];
    }

    private function resolveExistsWheres(string $currentTable, array $wheres): array
    {
        $rewritten = [];
        $existsIds = null;
        $notExistsIds = null;

        foreach ($wheres as $where) {
            $type = $where['type'] ?? 'Basic';

            if ($type === 'Exists') {
                $ids = $this->resolveExistsSubquery($currentTable, $where['subquery'] ?? []);
                $existsIds = $existsIds === null
                    ? $ids
                    : array_values(array_intersect($existsIds, $ids));
                continue;
            }

            if ($type === 'NotExists') {
                $ids = $this->resolveExistsSubquery($currentTable, $where['subquery'] ?? []);
                $notExistsIds = $notExistsIds === null
                    ? $ids
                    : array_unique(array_merge($notExistsIds, $ids));
                continue;
            }

            $rewritten[] = $where;
        }

        if ($existsIds !== null) {
            $rewritten[] = [
                'type' => 'In',
                'column' => 'id',
                'operator' => 'in',
                'value' => $existsIds,
                'boolean' => 'and',
            ];
        }

        if ($notExistsIds !== null) {
            $rewritten[] = [
                'type' => 'NotIn',
                'column' => 'id',
                'operator' => 'not in',
                'value' => $notExistsIds,
                'boolean' => 'and',
            ];
        }

        return $rewritten;
    }

    private function resolveExistsSubquery(string $currentTable, array $subquery): array
    {
        $subquery = $this->resolveNestedExists($subquery);

        $subqueryWheres = $subquery['wheres'] ?? [];
        $subqueryTable = $subquery['table'] ?? null;

        $columnWhere = null;
        $regularWheres = [];

        foreach ($subqueryWheres as $sw) {
            if (($sw['type'] ?? '') === 'Column' && $columnWhere === null) {
                $columnWhere = $sw;
                continue;
            }
            $regularWheres[] = $sw;
        }

        if ($subqueryTable === null) {
            return [];
        }

        if ($columnWhere === null) {
            $subOp = $subquery;
            $subOp['wheres'] = $regularWheres;
            $rows = $this->select(json_encode($subOp));
            $pk = $this->primaryKeyOf($subqueryTable);
            return collect($rows)->pluck($pk)->unique()->filter()->values()->all();
        }

        $second = (string) ($columnWhere['second'] ?? '');

        if (str_contains($second, '.')) {
            [$prefix, $col] = explode('.', $second, 2);
            if ($prefix !== $subqueryTable) {
                return $this->resolveExistsBelongsToMany($subqueryTable, $prefix, $col, $regularWheres, $subquery);
            }
        }

        $fkCol = str_contains($second, '.') ? explode('.', $second, 2)[1] : $second;

        $subOp = $subquery;
        $subOp['wheres'] = $regularWheres;
        $rows = $this->select(json_encode($subOp));

        return collect($rows)
            ->pluck($fkCol)
            ->unique()
            ->filter(fn ($v) => $v !== null)
            ->values()
            ->all();
    }

    private function resolveNestedExists(array $subquery): array
    {
        $wheres = $subquery['wheres'] ?? [];
        $subqueryTable = $subquery['table'] ?? '';
        $resolved = [];

        foreach ($wheres as $w) {
            $type = $w['type'] ?? 'Basic';

            if ($type === 'Exists' && isset($w['subquery'])) {
                $ids = $this->resolveExistsSubquery($subqueryTable, $w['subquery']);
                $resolved[] = [
                    'type' => 'In',
                    'column' => 'id',
                    'operator' => 'in',
                    'value' => $ids,
                    'boolean' => $w['boolean'] ?? 'and',
                ];
                continue;
            }

            if ($type === 'NotExists' && isset($w['subquery'])) {
                $ids = $this->resolveExistsSubquery($subqueryTable, $w['subquery']);
                $resolved[] = [
                    'type' => 'NotIn',
                    'column' => 'id',
                    'operator' => 'not in',
                    'value' => $ids,
                    'boolean' => $w['boolean'] ?? 'and',
                ];
                continue;
            }

            $resolved[] = $w;
        }

        $subquery['wheres'] = $resolved;
        return $subquery;
    }

    private function resolveExistsBelongsToMany(string $subqueryTable, string $pivotTable, string $pivotFk, array $regularWheres, array $subquery): array
    {
        $pivotSchema = $this->storage->getSchema($pivotTable);
        $foreignKeys = $pivotSchema['foreign_keys'] ?? [];
        $targetFkCol = null;

        foreach ($foreignKeys as $fk) {
            if (($fk['references_table'] ?? null) === $subqueryTable) {
                $targetFkCol = $fk['column'];
                break;
            }
        }

        if ($targetFkCol === null) {
            return [];
        }

        $subOp = $subquery;
        $subOp['wheres'] = $regularWheres;
        $rows = $this->select(json_encode($subOp));

        $subIds = collect($rows)
            ->map(fn ($r) => is_array($r) ? ($r['id'] ?? null) : ($r->id ?? null))
            ->filter(fn ($v) => $v !== null)
            ->unique()
            ->values()
            ->all();

        if (empty($subIds)) {
            return [];
        }

        $pivotRows = $this->storage->get($pivotTable);
        return $pivotRows
            ->filter(fn (array $row) => in_array($row[$pivotFk] ?? null, $subIds, true))
            ->pluck($targetFkCol)
            ->unique()
            ->filter(fn ($v) => $v !== null)
            ->values()
            ->all();
    }

    private function applySubqueryCounts(Collection $rows, array $columns, string $table): Collection
    {
        $countSpecs = [];

        foreach ($columns as $column) {
            if (is_array($column) && isset($column['subquery_count'])) {
                $countSpecs[] = $column['subquery_count'];
            }
        }

        if (empty($countSpecs)) {
            return $rows;
        }

        $countsByAlias = [];

        foreach ($countSpecs as $spec) {
            $subquery = $spec['subquery'] ?? [];
            $alias = $spec['alias'];

            $relatedTable = $subquery['table'] ?? null;
            if ($relatedTable === null) {
                $countsByAlias[$alias] = [];
                continue;
            }

            $subqueryWheres = $subquery['wheres'] ?? [];
            $columnWhere = null;
            $regularWheres = [];

            foreach ($subqueryWheres as $sw) {
                if (($sw['type'] ?? '') === 'Column' && $columnWhere === null) {
                    $columnWhere = $sw;
                    continue;
                }
                $regularWheres[] = $sw;
            }

            if ($columnWhere === null) {
                $countsByAlias[$alias] = [];
                continue;
            }

            $secondValue = (string) ($columnWhere['second'] ?? '');
            $fkCol = str_contains($secondValue, '.') ? explode('.', $secondValue, 2)[1] : $secondValue;

            $relatedOp = [
                'type' => 'select',
                'table' => $relatedTable,
                'columns' => ['*'],
                'wheres' => $regularWheres,
                'orders' => [],
                'limit' => null,
                'offset' => null,
            ];

            $relatedRows = $this->select(json_encode($relatedOp));

            $counts = [];
            foreach ($relatedRows as $r) {
                $rArr = (array) $r;
                $fkValue = $rArr[$fkCol] ?? null;
                if ($fkValue === null) {
                    continue;
                }
                $counts[$fkValue] = ($counts[$fkValue] ?? 0) + 1;
            }

            $countsByAlias[$alias] = $counts;
        }

        return $rows->map(function (array $row) use ($countsByAlias): array {
            foreach ($countsByAlias as $alias => $counts) {
                $row[$alias] = $counts[$row['id'] ?? null] ?? 0;
            }
            return $row;
        });
    }

    private function primaryKeyOf(string $table): string
    {
        $schema = $this->storage->getSchema($table);
        $columns = $schema['columns'] ?? [];

        foreach ($columns as $col) {
            if (! empty($col['auto_increment'])) {
                return $col['name'];
            }
        }

        return 'id';
    }

    private function splitWhereGroups(array $wheres): array
    {
        $groups = [];
        $currentGroup = [];

        foreach ($wheres as $where) {
            $boolean = $where['boolean'] ?? 'and';

            if ($boolean === 'or' && ! empty($currentGroup)) {
                $groups[] = $currentGroup;
                $currentGroup = [$where];
            } else {
                $currentGroup[] = $where;
            }
        }

        if (! empty($currentGroup)) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    private function applySingleWhere(Collection $rows, array $where): Collection
    {
        $type = $where['type'] ?? 'Basic';
        $column = $this->normalizeColumn($where['column'] ?? '');

        if ($type === 'between') {
            return $rows->filter(function (array $row) use ($column, $where): bool {
                $rowValue = $row[$column] ?? null;
                $values = $where['values'] ?? [];
                $not = $where['not'] ?? false;

                if ($rowValue === null || count($values) < 2) {
                    return false;
                }

                $matches = $rowValue >= $values[0] && $rowValue <= $values[1];

                return $not ? ! $matches : $matches;
            });
        }

        if (in_array($type, ['Date', 'Year', 'Month'], true)) {
            return $rows->filter(function (array $row) use ($column, $where, $type): bool {
                $rawValue = $row[$column] ?? null;

                if ($rawValue === null || $rawValue === '') {
                    return false;
                }

                $timestamp = strtotime((string) $rawValue);

                if ($timestamp === false) {
                    return false;
                }

                $extracted = match ($type) {
                    'Date' => date('Y-m-d', $timestamp),
                    'Year' => (int) date('Y', $timestamp),
                    'Month' => (int) date('n', $timestamp),
                };

                $operator = $where['operator'] ?? '=';
                $value = $type === 'Date' ? ($where['value'] ?? '') : (int) ($where['value'] ?? 0);

                return match ($operator) {
                    '=' => $extracted == $value,
                    '!=' => $extracted != $value,
                    '<>' => $extracted != $value,
                    '<' => $extracted < $value,
                    '>' => $extracted > $value,
                    '<=' => $extracted <= $value,
                    '>=' => $extracted >= $value,
                    default => true,
                };
            });
        }

        $operator = $where['operator'] ?? '';
        $value = $where['value'] ?? null;

        return $rows->filter(function (array $row) use ($column, $operator, $value): bool {
            $rowValue = $row[$column] ?? null;

            return match ($operator) {
                '=' => $rowValue == $value,
                '!=' => $rowValue != $value,
                '<>' => $rowValue != $value,
                '<' => $rowValue < $value,
                '>' => $rowValue > $value,
                '<=' => $rowValue <= $value,
                '>=' => $rowValue >= $value,
                'in' => in_array($rowValue, (array) $value, false),
                'not in' => ! in_array($rowValue, (array) $value, false),
                'is null' => is_null($rowValue),
                'is not null' => ! is_null($rowValue),
                'like' => $this->matchLike($rowValue, $value),
                default => true,
            };
        });
    }

    private function matchLike(mixed $rowValue, mixed $pattern): bool
    {
        if ($rowValue === null || $pattern === null) {
            return false;
        }

        $rowValue = strtolower((string) $rowValue);
        $pattern = strtolower((string) $pattern);

        if (strlen($pattern) > 2 && str_starts_with($pattern, '%') && str_ends_with($pattern, '%')) {
            return str_contains($rowValue, substr($pattern, 1, -1));
        }

        if (str_starts_with($pattern, '%')) {
            return str_ends_with($rowValue, substr($pattern, 1));
        }

        if (strlen($pattern) > 1 && str_ends_with($pattern, '%')) {
            return str_starts_with($rowValue, substr($pattern, 0, -1));
        }

        return $rowValue === $pattern;
    }

    private function applyOrders(Collection $rows, array $orders): Collection
    {
        if (empty($orders)) {
            return $rows;
        }

        $rowsArray = $rows->values()->all();

        usort($rowsArray, function (array $a, array $b) use ($orders): int {
            foreach ($orders as $order) {
                $column = $this->normalizeColumn($order['column']);
                $direction = $order['direction'] ?? 'asc';

                $valA = $a[$column] ?? null;
                $valB = $b[$column] ?? null;

                if ($valA == $valB) {
                    continue;
                }

                $result = $valA <=> $valB;

                return $direction === 'desc' ? -$result : $result;
            }

            return 0;
        });

        return collect($rowsArray);
    }

    private function normalizeColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            return substr($column, strrpos($column, '.') + 1);
        }

        return $column;
    }

    private function normalizeValueKeys(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[$this->normalizeColumn($key)] = $value;
        }

        return $normalized;
    }
}
