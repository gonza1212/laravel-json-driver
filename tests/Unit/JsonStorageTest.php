<?php

declare(strict_types=1);

use Gonza1212\JsonDriver\Storage\JsonStorage;

beforeEach(function () {
    $this->basePath = __DIR__ . '/../storage/json-storage-test';
    $this->storage = new JsonStorage($this->basePath);

    if (is_dir($this->basePath)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($this->basePath);
    }
});

afterEach(function () {
    if (is_dir($this->basePath)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($this->basePath);
    }
});

test('get sobre tabla inexistente devuelve Collection vacia', function () {
    $result = $this->storage->get('inexistente');

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->isEmpty())->toBeTrue();
});

test('put escribe archivo con datos recibidos', function () {
    $rows = collect([
        ['id' => 1, 'titulo' => 'hola'],
        ['id' => 2, 'titulo' => 'mundo'],
    ]);

    $this->storage->put('notas', $rows);

    $path = $this->basePath . '/notas.json';
    expect(file_exists($path))->toBeTrue();

    $content = json_decode(file_get_contents($path), true);
    expect($content)->toBe($rows->values()->all());
});

test('get lee archivo persistido con put', function () {
    $rows = collect([
        ['id' => 1, 'titulo' => 'hola'],
    ]);

    $this->storage->put('notas', $rows);

    $loaded = $this->storage->get('notas');

    expect($loaded->count())->toBe(1);
    expect($loaded->first()['titulo'])->toBe('hola');
});

test('tableExists con archivo presente devuelve true', function () {
    $this->storage->put('notas', collect([['id' => 1]]));

    expect($this->storage->tableExists('notas'))->toBeTrue();
});

test('tableExists sin archivo devuelve false', function () {
    expect($this->storage->tableExists('inexistente'))->toBeFalse();
});

test('getSchema y putSchema persisten y leen schema', function () {
    $schema = ['table' => 'notas', 'columns' => [['name' => 'id', 'type' => 'integer']]];

    $this->storage->putSchema('notas', $schema);

    $loaded = $this->storage->getSchema('notas');

    expect($loaded)->toBe($schema);
});

test('getSchema sobre tabla sin schema devuelve array vacio', function () {
    $loaded = $this->storage->getSchema('inexistente');

    expect($loaded)->toBe([]);
});

test('snapshot copia archivos a _snapshots con timestamp', function () {
    $this->storage->put('notas', collect([['id' => 1]]));
    $this->storage->putSchema('notas', ['table' => 'notas', 'columns' => []]);

    $this->storage->snapshot('notas');

    $snapshotDir = $this->basePath . '/_snapshots';
    expect(is_dir($snapshotDir))->toBeTrue();

    $dataFiles = glob($snapshotDir . '/notas_*.json') ?: [];
    $dataFiles = array_filter($dataFiles, fn ($f) => ! str_contains(basename($f), '.schema.'));
    expect(count($dataFiles))->toBe(1);

    $schemaFiles = glob($snapshotDir . '/notas_*.schema.json') ?: [];
    expect(count($schemaFiles))->toBe(1);
});

test('restore recupera datos desde snapshot mas reciente', function () {
    $this->storage->put('notas', collect([['id' => 1, 'titulo' => 'original']]));
    $this->storage->putSchema('notas', ['table' => 'notas', 'columns' => []]);

    $this->storage->snapshot('notas');

    $this->storage->put('notas', collect([['id' => 2, 'titulo' => 'modificado']]));

    $this->storage->restore('notas');

    $restored = $this->storage->get('notas');
    expect($restored->first()['titulo'])->toBe('original');
});

test('dropTable elimina archivos de datos y schema', function () {
    $this->storage->put('notas', collect([['id' => 1]]));
    $this->storage->putSchema('notas', ['table' => 'notas', 'columns' => []]);

    $this->storage->dropTable('notas');

    expect(file_exists($this->basePath . '/notas.json'))->toBeFalse();
    expect(file_exists($this->basePath . '/_schema/notas.schema.json'))->toBeFalse();
});
