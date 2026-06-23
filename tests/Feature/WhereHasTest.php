<?php

declare(strict_types=1);

use Gonza1212\JsonDriver\Tests\Models\Autor;
use Gonza1212\JsonDriver\Tests\Models\Genero;
use Gonza1212\JsonDriver\Tests\Models\Libro;
use Gonza1212\JsonDriver\Tests\Models\Perfil;
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
        $table->timestamps();
    });
});

test('whereHas filtra autores con libros que cumplen condicion', function () {
    $borges = Autor::create(['nombre' => 'Borges']);
    $cortazar = Autor::create(['nombre' => 'Cortazar']);

    Libro::create(['titulo' => 'Ficciones', 'autor_id' => $borges->id]);
    Libro::create(['titulo' => 'El Aleph', 'autor_id' => $borges->id]);
    Libro::create(['titulo' => 'Rayuela', 'autor_id' => $cortazar->id]);

    $autores = Autor::whereHas('libros', fn ($q) => $q->where('titulo', 'Ficciones'))->get();

    expect($autores)->toHaveCount(1);
    expect($autores->first()->nombre)->toBe('Borges');
});

test('has filtra autores con al menos un libro', function () {
    $borges = Autor::create(['nombre' => 'Borges']);
    $cortazar = Autor::create(['nombre' => 'Cortazar']);

    Libro::create(['titulo' => 'Ficciones', 'autor_id' => $borges->id]);

    $autoresConLibros = Autor::has('libros')->get();

    expect($autoresConLibros)->toHaveCount(1);
    expect($autoresConLibros->first()->nombre)->toBe('Borges');
});

test('withCount agrega columna libros_count con el valor correcto', function () {
    $borges = Autor::create(['nombre' => 'Borges']);
    $cortazar = Autor::create(['nombre' => 'Cortazar']);

    Libro::create(['titulo' => 'Ficciones', 'autor_id' => $borges->id]);
    Libro::create(['titulo' => 'El Aleph', 'autor_id' => $borges->id]);
    Libro::create(['titulo' => 'Rayuela', 'autor_id' => $cortazar->id]);

    $autores = Autor::withCount('libros')->get()->keyBy('nombre');

    expect($autores['Borges']->libros_count)->toBe(2);
    expect($autores['Cortazar']->libros_count)->toBe(1);
});

test('withCount incluye 0 para autores sin libros', function () {
    Autor::create(['nombre' => 'Borges']);
    Autor::create(['nombre' => 'Cortazar']);
    $borges = Autor::where('nombre', 'Borges')->first();
    Libro::create(['titulo' => 'Ficciones', 'autor_id' => $borges->id]);

    $autores = Autor::withCount('libros')->get()->keyBy('nombre');

    expect($autores['Borges']->libros_count)->toBe(1);
    expect($autores['Cortazar']->libros_count)->toBe(0);
});

test('whereHas combinado con where normal', function () {
    $borges = Autor::create(['nombre' => 'Borges']);
    $cortazar = Autor::create(['nombre' => 'Cortazar']);

    Libro::create(['titulo' => 'Ficciones', 'autor_id' => $borges->id]);
    Libro::create(['titulo' => 'Rayuela', 'autor_id' => $cortazar->id]);

    $autores = Autor::where('nombre', 'Borges')
        ->whereHas('libros')
        ->get();

    expect($autores)->toHaveCount(1);
    expect($autores->first()->nombre)->toBe('Borges');
});

test('whereHas con belongsToMany via genero_libro', function () {
    $borges = Autor::create(['nombre' => 'Borges']);
    $cortazar = Autor::create(['nombre' => 'Cortazar']);

    $libroBorges = Libro::create(['titulo' => 'Ficciones', 'autor_id' => $borges->id]);
    $libroCortazar = Libro::create(['titulo' => 'Rayuela', 'autor_id' => $cortazar->id]);

    $cuento = Genero::create(['nombre' => 'Cuento']);
    $novela = Genero::create(['nombre' => 'Novela']);

    $libroBorges->generos()->attach($cuento->id);
    $libroCortazar->generos()->attach($novela->id);

    $autores = Autor::whereHas('libros.generos', fn ($q) => $q->where('nombre', 'Cuento'))->get();

    expect($autores)->toHaveCount(1);
    expect($autores->first()->nombre)->toBe('Borges');
});
