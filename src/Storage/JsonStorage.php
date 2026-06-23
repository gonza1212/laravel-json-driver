<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Storage;

use Illuminate\Support\Collection;

class JsonStorage
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function listTables(): array
    {
        $files = glob($this->basePath . '/*.json');
        return array_map(function ($file) {
            return (object) ['name' => basename($file, '.json')];
        }, $files ?: []);
    }

    public function get(string $table): Collection
    {
        $path = $this->dataPath($table);

        if (! file_exists($path)) {
            return collect();
        }

        $content = file_get_contents($path);

        if ($content === false || $content === '') {
            return collect();
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            return collect();
        }

        return collect($data);
    }

    public function put(string $table, Collection $rows): void
    {
        $this->ensureDirectoryExists($this->basePath);

        $path = $this->dataPath($table);

        file_put_contents(
            $path,
            json_encode($rows->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX,
        );
    }

    public function getSchema(string $table): array
    {
        $path = $this->schemaPath($table);

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    public function putSchema(string $table, array $schema): void
    {
        $schemaDir = $this->basePath . '/_schema';
        $this->ensureDirectoryExists($schemaDir);

        file_put_contents(
            $this->schemaPath($table),
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX,
        );
    }

    public function tableExists(string $table): bool
    {
        return file_exists($this->dataPath($table));
    }

    public function dropTable(string $table): void
    {
        $dataPath = $this->dataPath($table);
        $schemaPath = $this->schemaPath($table);

        if (file_exists($dataPath)) {
            unlink($dataPath);
        }

        if (file_exists($schemaPath)) {
            unlink($schemaPath);
        }
    }

    public function snapshot(string $table): void
    {
        $timestamp = date('YmdHis');
        $snapshotDir = $this->basePath . '/_snapshots';
        $this->ensureDirectoryExists($snapshotDir);

        $dataFile = $this->dataPath($table);
        $schemaFile = $this->schemaPath($table);

        if (file_exists($dataFile)) {
            copy($dataFile, $snapshotDir . '/' . $table . '_' . $timestamp . '.json');
        }

        if (file_exists($schemaFile)) {
            copy($schemaFile, $snapshotDir . '/' . $table . '_' . $timestamp . '.schema.json');
        }
    }

    public function restore(string $table): void
    {
        $snapshotDir = $this->basePath . '/_snapshots';

        if (! is_dir($snapshotDir)) {
            return;
        }

        $dataFiles = glob($snapshotDir . '/' . $table . '_*.json') ?: [];

        $dataFiles = array_filter($dataFiles, function (string $file): bool {
            return ! str_contains(basename($file), '.schema.');
        });

        if (empty($dataFiles)) {
            return;
        }

        rsort($dataFiles);
        $latestDataFile = $dataFiles[0];

        $basename = basename($latestDataFile, '.json');
        $timestamp = substr($basename, strlen($table) + 1);

        $latestSchemaFile = $snapshotDir . '/' . $table . '_' . $timestamp . '.schema.json';

        copy($latestDataFile, $this->dataPath($table));

        $schemaDir = $this->basePath . '/_schema';
        $this->ensureDirectoryExists($schemaDir);

        if (file_exists($latestSchemaFile)) {
            copy($latestSchemaFile, $this->schemaPath($table));
        }
    }

    private function dataPath(string $table): string
    {
        return $this->basePath . '/' . $table . '.json';
    }

    private function schemaPath(string $table): string
    {
        return $this->basePath . '/_schema/' . $table . '.schema.json';
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
