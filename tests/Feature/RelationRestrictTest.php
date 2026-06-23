<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('autores', function ($table): void {
        $table->id();
        $table->string('nombre');
    });

    Schema::create('libros', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->foreignId('autor_id')->constrained('autores')->restrictOnDelete();
    });

    Schema::create('autores_cascade', function ($table): void {
        $table->id();
        $table->string('nombre');
    });

    Schema::create('libros_cascade', function ($table): void {
        $table->id();
        $table->string('titulo');
        $table->foreignId('autor_id')->constrained('autores_cascade')->cascadeOnDelete();
    });
});

test('borrar autor con libros asociados lanza RuntimeException por RESTRICT', function () {
    \DB::table('autores')->insert(['nombre' => 'Borges']);
    \DB::table('libros')->insert(['titulo' => 'Ficciones', 'autor_id' => 1]);

    expect(fn () => \DB::table('autores')->where('id', 1)->delete())
        ->toThrow(\RuntimeException::class);
});

test('borrar autor sin libros asociados se elimina normalmente', function () {
    \DB::table('autores')->insert(['nombre' => 'Borges']);
    \DB::table('autores')->insert(['nombre' => 'Cortazar']);

    \DB::table('autores')->where('id', 1)->delete();

    expect(\DB::table('autores')->count())->toBe(1);
    expect(\DB::table('autores')->first()->nombre)->toBe('Cortazar');
});

test('borrar autor con libros cascade elimina libros asociados automaticamente', function () {
    \DB::table('autores_cascade')->insert(['nombre' => 'Borges']);
    \DB::table('libros_cascade')->insert(['titulo' => 'Ficciones', 'autor_id' => 1]);
    \DB::table('libros_cascade')->insert(['titulo' => 'El Aleph', 'autor_id' => 1]);

    expect(\DB::table('libros_cascade')->count())->toBe(2);

    \DB::table('autores_cascade')->where('id', 1)->delete();

    expect(\DB::table('autores_cascade')->count())->toBe(0);
    expect(\DB::table('libros_cascade')->count())->toBe(0);
});

test('RESTRICT deja intactas las filas de la tabla relacionada cuando falla el delete', function () {
    \DB::table('autores')->insert(['nombre' => 'Borges']);
    \DB::table('libros')->insert(['titulo' => 'Ficciones', 'autor_id' => 1]);

    try {
        \DB::table('autores')->where('id', 1)->delete();
    } catch (\RuntimeException) {
    }

    expect(\DB::table('autores')->count())->toBe(1);
    expect(\DB::table('libros')->count())->toBe(1);
});

test('RESTRICT no afecta deletes de filas que no tienen relacionados', function () {
    \DB::table('autores')->insert(['nombre' => 'A']);
    \DB::table('autores')->insert(['nombre' => 'B']);
    \DB::table('libros')->insert(['titulo' => 'Ficciones', 'autor_id' => 1]);

    \DB::table('autores')->where('id', 2)->delete();

    expect(\DB::table('autores')->count())->toBe(1);
    expect(\DB::table('libros')->count())->toBe(1);
});
