<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('migrate crea archivos de datos y schema', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->timestamps();
    });

    $dataPath = $this->storagePath . '/notas.json';
    $schemaPath = $this->storagePath . '/_schema/notas.schema.json';

    expect(file_exists($dataPath))->toBeTrue();
    expect(file_exists($schemaPath))->toBeTrue();

    $data = json_decode(file_get_contents($dataPath), true);
    expect($data)->toBe([]);

    $schema = json_decode(file_get_contents($schemaPath), true);
    expect($schema['table'])->toBe('notas');
    expect($schema['columns'])->toHaveCount(4);
});

test('migrate con tabla ya existente lanza error', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->timestamps();
    });

    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->timestamps();
    });
})->throws(\Exception::class);

test('migrate rollback elimina archivos', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->timestamps();
    });

    Schema::dropIfExists('notas');

    $dataPath = $this->storagePath . '/notas.json';
    $schemaPath = $this->storagePath . '/_schema/notas.schema.json';

    expect(file_exists($dataPath))->toBeFalse();
    expect(file_exists($schemaPath))->toBeFalse();
});

test('migrate con tabla sin timestamps funciona', function () {
    Schema::create('categorias', function ($table): void {
        $table->id();
        $table->string('nombre');
    });

    $schema = json_decode(
        file_get_contents($this->storagePath . '/_schema/categorias.schema.json'),
        true,
    );

    $columnNames = array_column($schema['columns'], 'name');
    expect($columnNames)->toContain('id');
    expect($columnNames)->toContain('nombre');
    expect($columnNames)->not()->toContain('created_at');
});
