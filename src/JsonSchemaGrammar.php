<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;

class JsonSchemaGrammar extends Grammar
{
    public function compileTables($schema): string
    {
        return json_encode(['op' => 'list_tables']);
    }

    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $columns[] = [
                'name' => $column->get('name'),
                'type' => $column->get('type'),
                'nullable' => $column->get('nullable', false),
                'auto_increment' => $column->get('autoIncrement', false),
                'unsigned' => $column->get('unsigned', false),
                'default' => $column->get('default'),
                'length' => $column->get('length'),
            ];
        }

        $foreignKeys = $this->extractForeignKeys($blueprint);

        return json_encode([
            'type' => 'create',
            'table' => $blueprint->getTable(),
            'columns' => $columns,
            'foreign_keys' => $foreignKeys,
        ]);
    }

    public function compileAdd(Blueprint $blueprint, Fluent $command): string
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            if ($column->get('change', false)) {
                continue;
            }
            $columns[] = [
                'name' => $column->get('name'),
                'type' => $column->get('type'),
                'nullable' => $column->get('nullable', false),
                'auto_increment' => $column->get('autoIncrement', false),
                'unsigned' => $column->get('unsigned', false),
                'default' => $column->get('default'),
                'length' => $column->get('length'),
            ];
        }

        $foreignKeys = $this->extractForeignKeys($blueprint);

        return json_encode([
            'type' => 'add_column',
            'table' => $blueprint->getTable(),
            'columns' => $columns,
            'foreign_keys' => $foreignKeys,
        ]);
    }

    private function extractForeignKeys(Blueprint $blueprint): array
    {
        $foreignKeys = [];

        foreach ($blueprint->getCommands() as $cmd) {
            if ($cmd->get('name') !== 'foreign') {
                continue;
            }

            $localColumns = (array) $cmd->get('columns');
            $referencedColumns = (array) ($cmd->get('references') ?? ['id']);
            $referencedTable = $cmd->get('on');
            $onDelete = $cmd->get('onDelete') ?? 'restrict';

            foreach ($localColumns as $i => $localColumn) {
                $foreignKeys[] = [
                    'column' => $localColumn,
                    'references_table' => $referencedTable,
                    'references_column' => $referencedColumns[$i] ?? ($referencedColumns[0] ?? 'id'),
                    'on_delete' => $onDelete,
                ];
            }
        }

        return $foreignKeys;
    }

    public function compileDropColumn(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $blueprint->getColumns();

        if (count($columns) > 0) {
            $columnNames = array_map(fn ($col) => $col->get('name'), $columns);
        } else {
            $columnNames = $command->get('columns', []);
        }

        return json_encode([
            'type' => 'drop_column',
            'table' => $blueprint->getTable(),
            'columns' => $columnNames,
        ]);
    }

    public function compileRename(Blueprint $blueprint, Fluent $command): string
    {
        return json_encode([
            'type' => 'rename',
            'from' => $blueprint->getTable(),
            'to' => $command->get('to'),
        ]);
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command): string
    {
        return json_encode([
            'type' => 'drop',
            'table' => $blueprint->getTable(),
        ]);
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        return json_encode([
            'type' => 'drop_if_exists',
            'table' => $blueprint->getTable(),
        ]);
    }
}
