<?php

declare(strict_types=1);

use Gonza1212\JsonDriver\Tests\Models\Nota;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo')->nullable();
        $table->integer('numero')->nullable();
        $table->timestamps();
    });

    Nota::create(['titulo' => 'a', 'numero' => 1]);
    Nota::create(['titulo' => 'b', 'numero' => 3]);
    Nota::create(['titulo' => 'c', 'numero' => 5]);
    Nota::create(['titulo' => 'd', 'numero' => 7]);
    Nota::create(['titulo' => 'e', 'numero' => 9]);
});

test('where igual devuelve registros coincidentes', function () {
    $resultados = Nota::where('titulo', '=', 'a')->get();
    expect($resultados)->toHaveCount(1);
    expect($resultados->first()->titulo)->toBe('a');
});

test('where distinto != devuelve registros no coincidentes', function () {
    $resultados = Nota::where('titulo', '!=', 'a')->get();
    expect($resultados)->toHaveCount(4);
});

test('where distinto <> funciona igual que !=', function () {
    $resultados = Nota::where('titulo', '<>', 'a')->get();
    expect($resultados)->toHaveCount(4);
});

test('where menor que', function () {
    $resultados = Nota::where('numero', '<', 5)->get();
    expect($resultados)->toHaveCount(2);
    expect($resultados->pluck('numero')->toArray())->toBe([1, 3]);
});

test('where mayor que', function () {
    $resultados = Nota::where('numero', '>', 5)->get();
    expect($resultados)->toHaveCount(2);
    expect($resultados->pluck('numero')->toArray())->toBe([7, 9]);
});

test('where menor o igual', function () {
    $resultados = Nota::where('numero', '<=', 5)->get();
    expect($resultados)->toHaveCount(3);
});

test('where mayor o igual', function () {
    $resultados = Nota::where('numero', '>=', 5)->get();
    expect($resultados)->toHaveCount(3);
});

test('whereIn devuelve registros con ids especificados', function () {
    $resultados = Nota::whereIn('id', [1, 2, 3])->get();
    expect($resultados)->toHaveCount(3);
});

test('whereNotIn devuelve registros con ids distintos', function () {
    $resultados = Nota::whereNotIn('id', [1, 2])->get();
    expect($resultados)->toHaveCount(3);
    expect($resultados->pluck('id')->toArray())->not()->toContain(1);
    expect($resultados->pluck('id')->toArray())->not()->toContain(2);
});

test('whereNull devuelve registros donde campo es null', function () {
    Nota::create(['titulo' => null, 'numero' => 10]);

    $resultados = Nota::whereNull('titulo')->get();
    expect($resultados)->toHaveCount(1);
    expect($resultados->first()->titulo)->toBeNull();
});

test('whereNull no devuelve registros con valor', function () {
    $resultados = Nota::whereNull('titulo')->get();
    expect($resultados)->toHaveCount(0);
});

test('whereNotNull devuelve registros donde campo tiene valor', function () {
    $resultados = Nota::whereNotNull('titulo')->get();
    expect($resultados)->toHaveCount(5);
});

test('whereNotNull no devuelve registros con null', function () {
    Nota::create(['titulo' => null, 'numero' => 10]);

    $resultados = Nota::whereNotNull('titulo')->get();
    expect($resultados)->toHaveCount(5);
});

test('orderBy asc', function () {
    $resultados = Nota::orderBy('numero', 'asc')->get();
    $numeros = $resultados->pluck('numero')->toArray();
    expect($numeros)->toBe([1, 3, 5, 7, 9]);
});

test('orderBy desc', function () {
    $resultados = Nota::orderBy('numero', 'desc')->get();
    $numeros = $resultados->pluck('numero')->toArray();
    expect($numeros)->toBe([9, 7, 5, 3, 1]);
});

test('limit limita cantidad de registros', function () {
    $resultados = Nota::limit(2)->get();
    expect($resultados)->toHaveCount(2);
});

test('offset con limit devuelve registros paginados', function () {
    $resultados = Nota::offset(2)->limit(2)->get();
    expect($resultados)->toHaveCount(2);
    expect($resultados->first()->id)->toBe(3);
});
