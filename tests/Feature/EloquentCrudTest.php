<?php

declare(strict_types=1);

use Gonza1212\JsonDriver\Tests\Models\Nota;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->timestamps();
    });
});

test('create persiste registro con id autoincremental', function () {
    $nota = Nota::create(['titulo' => 'hola']);

    expect($nota->id)->toBe(1);
    expect($nota->titulo)->toBe('hola');
    expect($nota->created_at)->not()->toBeNull();
    expect($nota->updated_at)->not()->toBeNull();

    $archivo = json_decode(
        file_get_contents($this->storagePath . '/notas.json'),
        true,
    );
    expect($archivo)->toHaveCount(1);
    expect($archivo[0]['titulo'])->toBe('hola');
});

test('segundo create obtiene id autoincremental siguiente', function () {
    Nota::create(['titulo' => 'primero']);
    $segundo = Nota::create(['titulo' => 'segundo']);

    expect($segundo->id)->toBe(2);
});

test('find devuelve modelo hidratado', function () {
    Nota::create(['titulo' => 'hola']);

    $nota = Nota::find(1);

    expect($nota)->not()->toBeNull();
    expect($nota->titulo)->toBe('hola');
    expect($nota->id)->toBe(1);
});

test('find con id inexistente devuelve null', function () {
    $nota = Nota::find(999);

    expect($nota)->toBeNull();
});

test('all devuelve todos los registros', function () {
    Nota::create(['titulo' => 'a']);
    Nota::create(['titulo' => 'b']);

    $todas = Nota::all();

    expect($todas)->toHaveCount(2);
});

test('where first devuelve modelo correcto', function () {
    Nota::create(['titulo' => 'hola']);
    Nota::create(['titulo' => 'mundo']);

    $nota = Nota::where('titulo', 'hola')->first();

    expect($nota)->not()->toBeNull();
    expect($nota->titulo)->toBe('hola');
});

test('update modifica campo en archivo', function () {
    $nota = Nota::create(['titulo' => 'original']);
    $nota->update(['titulo' => 'modificado']);

    $archivo = json_decode(
        file_get_contents($this->storagePath . '/notas.json'),
        true,
    );
    expect($archivo[0]['titulo'])->toBe('modificado');
});

test('update preserva campos no modificados', function () {
    $nota = Nota::create(['titulo' => 'original']);
    $originalId = $nota->id;

    $nota->update(['titulo' => 'modificado']);

    $archivo = json_decode(
        file_get_contents($this->storagePath . '/notas.json'),
        true,
    );
    expect($archivo[0]['id'])->toBe($originalId);
    expect($archivo[0]['titulo'])->toBe('modificado');
    expect($archivo[0]['created_at'])->not()->toBeNull();
});

test('delete elimina registro del archivo', function () {
    $nota = Nota::create(['titulo' => 'hola']);
    $nota2 = Nota::create(['titulo' => 'mundo']);

    $nota->delete();

    $archivo = json_decode(
        file_get_contents($this->storagePath . '/notas.json'),
        true,
    );
    expect($archivo)->toHaveCount(1);
    expect($archivo[0]['id'])->toBe(2);
});
