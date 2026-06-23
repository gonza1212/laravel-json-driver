<?php

declare(strict_types=1);

use Gonza1212\JsonDriver\Tests\Models\Nota;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('notas', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->integer('numero')->nullable();
        $table->timestamps();
    });

    Nota::create([
        'titulo' => 'Hola mundo',
        'numero' => 10,
        'created_at' => '2024-01-15 10:00:00',
        'updated_at' => '2024-01-15 10:00:00',
    ]);
    Nota::create([
        'titulo' => 'hola laravel',
        'numero' => 25,
        'created_at' => '2024-03-20 14:00:00',
        'updated_at' => '2024-03-20 14:00:00',
    ]);
    Nota::create([
        'titulo' => 'Adiós mundo',
        'numero' => 50,
        'created_at' => '2024-06-05 09:00:00',
        'updated_at' => '2024-06-05 09:00:00',
    ]);
    Nota::create([
        'titulo' => 'Laravel rocks',
        'numero' => 75,
        'created_at' => '2025-02-10 16:00:00',
        'updated_at' => '2025-02-10 16:00:00',
    ]);
    Nota::create([
        'titulo' => 'PHP moderno',
        'numero' => 90,
        'created_at' => '2025-11-30 08:00:00',
        'updated_at' => '2025-11-30 08:00:00',
    ]);
});

test('whereBetween numerico', function () {
    $resultados = Nota::whereBetween('numero', [20, 60])->count();
    expect($resultados)->toBe(2);
});

test('whereNotBetween numerico', function () {
    $resultados = Nota::whereNotBetween('numero', [20, 60])->count();
    expect($resultados)->toBe(3);
});

test('whereBetween fechas', function () {
    $resultados = Nota::whereBetween('created_at', ['2024-01-01', '2024-12-31'])->count();
    expect($resultados)->toBe(3);
});

test('whereNotBetween fechas', function () {
    $resultados = Nota::whereNotBetween('created_at', ['2024-01-01', '2024-12-31'])->count();
    expect($resultados)->toBe(2);
});

test('whereLike con porcentaje en ambos extremos es case-insensitive', function () {
    $resultados = Nota::where('titulo', 'like', '%hola%')->count();
    expect($resultados)->toBe(2);
});

test('whereLike con porcentaje al final', function () {
    $resultados = Nota::where('titulo', 'like', 'hola%')->count();
    expect($resultados)->toBe(2);
});

test('whereLike con porcentaje al inicio', function () {
    $resultados = Nota::where('titulo', 'like', '%mundo')->count();
    expect($resultados)->toBe(2);
});

test('whereLike sin coincidencias', function () {
    $resultados = Nota::where('titulo', 'like', '%inexistente%')->count();
    expect($resultados)->toBe(0);
});

test('whereDate con igual', function () {
    $resultados = Nota::whereDate('created_at', '2024-01-15')->count();
    expect($resultados)->toBe(1);
});

test('whereDate con mayor que', function () {
    $resultados = Nota::whereDate('created_at', '>', '2024-06-01')->count();
    expect($resultados)->toBe(3);
});

test('whereYear con igual', function () {
    $resultados = Nota::whereYear('created_at', 2024)->count();
    expect($resultados)->toBe(3);
});

test('whereYear con mayor que', function () {
    $resultados = Nota::whereYear('created_at', '>', 2024)->count();
    expect($resultados)->toBe(2);
});

test('whereMonth con igual', function () {
    $resultados = Nota::whereMonth('created_at', 3)->count();
    expect($resultados)->toBe(1);
});

test('whereMonth con menor que', function () {
    $resultados = Nota::whereMonth('created_at', '<', 6)->count();
    expect($resultados)->toBe(3);
});
