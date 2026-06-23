<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('foreignId con constrained persiste FK en schema', function () {
    Schema::create('autores', function ($table): void {
        $table->id();
        $table->string('nombre');
    });

    Schema::create('libros', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->foreignId('autor_id')->constrained('autores')->cascadeOnDelete();
    });

    $schema = json_decode(
        file_get_contents($this->storagePath . '/_schema/libros.schema.json'),
        true,
    );

    expect($schema['foreign_keys'])->toHaveCount(1);
    expect($schema['foreign_keys'][0])->toMatchArray([
        'column' => 'autor_id',
        'references_table' => 'autores',
        'references_column' => 'id',
        'on_delete' => 'cascade',
    ]);
});

test('foreignId con constrained sin cascade persiste restrict por default', function () {
    Schema::create('autores', function ($table): void {
        $table->id();
    });

    Schema::create('perfiles', function ($table): void {
        $table->id();
        $table->foreignId('autor_id')->constrained('autores');
    });

    $schema = json_decode(
        file_get_contents($this->storagePath . '/_schema/perfiles.schema.json'),
        true,
    );

    expect($schema['foreign_keys'])->toHaveCount(1);
    expect($schema['foreign_keys'][0]['on_delete'])->toBe('restrict');
});

test('restrictOnDelete explicito persiste restrict', function () {
    Schema::create('autores', function ($table): void {
        $table->id();
    });

    Schema::create('libros', function ($table): void {
        $table->id();
        $table->foreignId('autor_id')->constrained('autores')->restrictOnDelete();
    });

    $schema = json_decode(
        file_get_contents($this->storagePath . '/_schema/libros.schema.json'),
        true,
    );

    expect($schema['foreign_keys'][0]['on_delete'])->toBe('restrict');
});

test('tabla sin FK no tiene bloque foreign_keys', function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
    });

    $schema = json_decode(
        file_get_contents($this->storagePath . '/_schema/notas.schema.json'),
        true,
    );

    expect($schema)->not->toHaveKey('foreign_keys');
});
