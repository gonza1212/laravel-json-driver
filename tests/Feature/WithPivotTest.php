<?php

declare(strict_types=1);

use Gonza1212\JsonDriver\Tests\Models\Genero;
use Gonza1212\JsonDriver\Tests\Models\Libro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('autores', function ($table): void {
        $table->id();
        $table->string('nombre');
        $table->timestamps();
    });

    Schema::create('libros', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->foreignId('autor_id')->constrained('autores')->restrictOnDelete();
        $table->timestamps();
    });

    Schema::create('generos', function ($table): void {
        $table->id();
        $table->string('nombre');
        $table->timestamps();
    });

    Schema::create('genero_libro', function ($table): void {
        $table->id();
        $table->foreignId('genero_id')->constrained('generos')->restrictOnDelete();
        $table->foreignId('libro_id')->constrained('libros')->cascadeOnDelete();
        $table->integer('orden')->nullable();
        $table->timestamp('fecha_agregado')->nullable();
        $table->timestamps();
    });
});

test('attach con datos pivot persiste las columnas extra', function () {
    $libro = Libro::create(['titulo' => 'Ficciones']);
    $cuento = Genero::create(['nombre' => 'Cuento']);

    $libro->generos()->attach($cuento->id, [
        'orden' => 1,
        'fecha_agregado' => '2024-01-15 10:00:00',
    ]);

    $row = DB::table('genero_libro')->first();
    expect($row->orden)->toBe(1);
    expect($row->fecha_agregado)->toBe('2024-01-15 10:00:00');
});

test('withPivot expone columnas extras en el modelo pivot', function () {
    $libro = Libro::create(['titulo' => 'Ficciones']);
    $cuento = Genero::create(['nombre' => 'Cuento']);
    $ensayo = Genero::create(['nombre' => 'Ensayo']);

    $libro->generos()->attach($cuento->id, [
        'orden' => 1,
        'fecha_agregado' => '2024-01-15 10:00:00',
    ]);
    $libro->generos()->attach($ensayo->id, [
        'orden' => 2,
        'fecha_agregado' => '2024-01-16 10:00:00',
    ]);

    $libroCargado = Libro::with('generos')->find($libro->id);
    expect($libroCargado->generos->count())->toBe(2);

    $generos = $libroCargado->generos->keyBy('nombre');

    expect($generos['Cuento']->pivot->orden)->toBe(1);
    expect($generos['Cuento']->pivot->fecha_agregado)->toBe('2024-01-15 10:00:00');
    expect($generos['Ensayo']->pivot->orden)->toBe(2);
    expect($generos['Ensayo']->pivot->fecha_agregado)->toBe('2024-01-16 10:00:00');
});

test('withPivot preserva datos al filtrar la relacion', function () {
    $libro = Libro::create(['titulo' => 'Ficciones']);
    $cuento = Genero::create(['nombre' => 'Cuento']);

    $libro->generos()->attach($cuento->id, [
        'orden' => 5,
        'fecha_agregado' => '2024-03-15 10:00:00',
    ]);

    $genero = $libro->generos()->wherePivot('orden', 5)->first();

    expect($genero)->not->toBeNull();
    expect($genero->nombre)->toBe('Cuento');
    expect($genero->pivot->orden)->toBe(5);
    expect($genero->pivot->fecha_agregado)->toBe('2024-03-15 10:00:00');
});
