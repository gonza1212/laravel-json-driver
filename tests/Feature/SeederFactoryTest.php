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

test('factory create persiste registro en archivo', function () {
    $nota = Nota::factory()->create(['titulo' => 'hola']);

    expect($nota->id)->toBe(1);
    expect($nota->titulo)->toBe('hola');

    $archivo = json_decode(
        file_get_contents($this->storagePath . '/notas.json'),
        true,
    );
    expect($archivo)->toHaveCount(1);
});

test('factory count 3 crea tres registros con ids correlativos', function () {
    $notas = Nota::factory()->count(3)->create();

    expect($notas)->toHaveCount(3);
    expect($notas->pluck('id')->toArray())->toBe([1, 2, 3]);

    $archivo = json_decode(
        file_get_contents($this->storagePath . '/notas.json'),
        true,
    );
    expect($archivo)->toHaveCount(3);
});

test('insercion manual multiple persiste registros', function () {
    Nota::create(['titulo' => 'uno']);
    Nota::create(['titulo' => 'dos']);
    Nota::create(['titulo' => 'tres']);

    $archivo = json_decode(
        file_get_contents($this->storagePath . '/notas.json'),
        true,
    );
    expect($archivo)->toHaveCount(3);
});

test('seeder crea registros via Nota::create', function () {
    $this->seed(\Gonza1212\JsonDriver\Tests\Seeders\NotaSeeder::class);

    expect(Nota::all())->toHaveCount(3);
});
