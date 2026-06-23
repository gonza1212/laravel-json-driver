<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('drop de tabla existente crea snapshot antes de eliminar', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->timestamps();
    });

    $dataPath = $this->storagePath . '/notas.json';
    $schemaPath = $this->storagePath . '/_schema/notas.schema.json';

    expect(file_exists($dataPath))->toBeTrue();
    expect(file_exists($schemaPath))->toBeTrue();

    Schema::drop('notas');

    expect(file_exists($dataPath))->toBeFalse();
    expect(file_exists($schemaPath))->toBeFalse();

    $snapshotDir = $this->storagePath . '/_snapshots';
    $dataSnapshots = glob($snapshotDir . '/notas_*.json') ?: [];
    $dataSnapshots = array_filter($dataSnapshots, fn ($f) => ! str_contains(basename($f), '.schema.'));

    expect(count($dataSnapshots))->toBe(1);
    expect(basename($dataSnapshots[0]))->toMatch('/^notas_\d{14}\.json$/');
});

test('dropIfExists de tabla existente crea snapshot', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
    });

    Schema::dropIfExists('notas');

    $snapshotDir = $this->storagePath . '/_snapshots';
    $dataSnapshots = glob($snapshotDir . '/notas_*.json') ?: [];
    $dataSnapshots = array_filter($dataSnapshots, fn ($f) => ! str_contains(basename($f), '.schema.'));

    expect(count($dataSnapshots))->toBe(1);
});

test('dropIfExists de tabla inexistente no lanza error', function () {
    Schema::dropIfExists('inexistente');

    expect(true)->toBeTrue();
});

test('snapshot contiene datos y schema', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
    });

    Schema::drop('notas');

    $snapshotDir = $this->storagePath . '/_snapshots';
    $schemaSnapshots = glob($snapshotDir . '/notas_*.schema.json') ?: [];

    expect(count($schemaSnapshots))->toBe(1);
});

test('rename crea snapshot de tabla original', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
    });

    Schema::rename('notas', 'entradas');

    $snapshotDir = $this->storagePath . '/_snapshots';
    $dataSnapshots = glob($snapshotDir . '/notas_*.json') ?: [];
    $dataSnapshots = array_filter($dataSnapshots, fn ($f) => ! str_contains(basename($f), '.schema.'));

    expect(count($dataSnapshots))->toBe(1);
    expect(file_exists($this->storagePath . '/entradas.json'))->toBeTrue();
    expect(file_exists($this->storagePath . '/_schema/entradas.schema.json'))->toBeTrue();
});

test('dropColumn crea snapshot antes de modificar', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->string('descripcion');
    });

    Schema::table('notas', function ($table): void {
        $table->dropColumn('descripcion');
    });

    $snapshotDir = $this->storagePath . '/_snapshots';
    $dataSnapshots = glob($snapshotDir . '/notas_*.json') ?: [];
    $dataSnapshots = array_filter($dataSnapshots, fn ($f) => ! str_contains(basename($f), '.schema.'));

    expect(count($dataSnapshots))->toBe(1);

    $schema = json_decode(
        file_get_contents($this->storagePath . '/_schema/notas.schema.json'),
        true,
    );
    $columnNames = array_column($schema['columns'], 'name');
    expect($columnNames)->not()->toContain('descripcion');
});
