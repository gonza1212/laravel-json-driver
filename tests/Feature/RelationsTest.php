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

    Schema::create('perfiles', function ($table): void {
        $table->id();
        $table->string('biografia')->nullable();
        $table->foreignId('autor_id')->constrained('autores')->restrictOnDelete();
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

test('hasOne devuelve el perfil correcto', function () {
    $autor = Autor::create(['nombre' => 'Borges']);
    Perfil::create(['autor_id' => $autor->id, 'biografia' => 'Bio de Borges']);

    $perfil = $autor->perfil;

    expect($perfil)->not->toBeNull();
    expect($perfil->biografia)->toBe('Bio de Borges');
    expect($perfil->autor_id)->toBe($autor->id);
});

test('hasOne devuelve null cuando autor no tiene perfil', function () {
    $autor = Autor::create(['nombre' => 'Borges']);

    expect($autor->perfil)->toBeNull();
});

test('hasMany devuelve la coleccion de libros', function () {
    $autor = Autor::create(['nombre' => 'Borges']);
    Libro::create(['titulo' => 'Ficciones', 'autor_id' => $autor->id]);
    Libro::create(['titulo' => 'El Aleph', 'autor_id' => $autor->id]);

    $libros = $autor->libros;

    expect($libros)->toHaveCount(2);
    expect($libros->pluck('titulo')->sort()->values()->all())
        ->toBe(['El Aleph', 'Ficciones']);
});

test('hasMany devuelve coleccion vacia cuando autor no tiene libros', function () {
    $autor = Autor::create(['nombre' => 'Borges']);

    $libros = $autor->libros;

    expect($libros)->toHaveCount(0);
    expect($libros)->not->toBeNull();
});

test('belongsTo desde perfil devuelve el autor correcto', function () {
    $autor = Autor::create(['nombre' => 'Borges']);
    $perfil = Perfil::create(['autor_id' => $autor->id, 'biografia' => 'Bio']);

    $perfilAutor = $perfil->autor;

    expect($perfilAutor)->not->toBeNull();
    expect($perfilAutor->nombre)->toBe('Borges');
});

test('belongsTo desde libro devuelve el autor correcto', function () {
    $autor = Autor::create(['nombre' => 'Borges']);
    $libro = Libro::create(['titulo' => 'Ficciones', 'autor_id' => $autor->id]);

    $libroAutor = $libro->autor;

    expect($libroAutor)->not->toBeNull();
    expect($libroAutor->nombre)->toBe('Borges');
});

test('belongsToMany devuelve los generos del libro', function () {
    $libro = Libro::create(['titulo' => 'Ficciones']);
    $cuento = Genero::create(['nombre' => 'Cuento']);
    $ensayo = Genero::create(['nombre' => 'Ensayo']);

    $libro->generos()->attach($cuento->id);
    $libro->generos()->attach($ensayo->id);

    $generos = $libro->generos;

    expect($generos)->toHaveCount(2);
    expect($generos->pluck('nombre')->sort()->values()->all())
        ->toBe(['Cuento', 'Ensayo']);
});

test('belongsToMany devuelve los libros del genero', function () {
    $ficciones = Libro::create(['titulo' => 'Ficciones']);
    $aleph = Libro::create(['titulo' => 'El Aleph']);
    $cuento = Genero::create(['nombre' => 'Cuento']);

    $cuento->libros()->attach($ficciones->id);
    $cuento->libros()->attach($aleph->id);

    $libros = $cuento->libros;

    expect($libros)->toHaveCount(2);
    expect($libros->pluck('titulo')->sort()->values()->all())
        ->toBe(['El Aleph', 'Ficciones']);
});

test('belongsToMany con FK a autor inexistente devuelve null', function () {
    $libro = Libro::create(['titulo' => 'Ficciones', 'autor_id' => 999]);

    expect($libro->autor)->toBeNull();
});

test('eager loading con with() no rompe y devuelve datos correctos', function () {
    $borges = Autor::create(['nombre' => 'Borges']);
    $cortazar = Autor::create(['nombre' => 'Cortazar']);

    Libro::create(['titulo' => 'Ficciones', 'autor_id' => $borges->id]);
    Libro::create(['titulo' => 'El Aleph', 'autor_id' => $borges->id]);
    Libro::create(['titulo' => 'Rayuela', 'autor_id' => $cortazar->id]);

    $autores = Autor::with('libros')->get();

    expect($autores)->toHaveCount(2);

    $borgesLoaded = $autores->firstWhere('nombre', 'Borges');
    expect($borgesLoaded->libros)->toHaveCount(2);

    $cortazarLoaded = $autores->firstWhere('nombre', 'Cortazar');
    expect($cortazarLoaded->libros)->toHaveCount(1);
});

test('eager loading con with() anidado', function () {
    $borges = Autor::create(['nombre' => 'Borges']);
    Perfil::create(['autor_id' => $borges->id, 'biografia' => 'Bio Borges']);

    $autores = Autor::with('perfil')->get();

    expect($autores)->toHaveCount(1);
    expect($autores->first()->perfil)->not->toBeNull();
    expect($autores->first()->perfil->biografia)->toBe('Bio Borges');
});
