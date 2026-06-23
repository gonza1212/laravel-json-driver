<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genero_libro', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('genero_id')->constrained('generos')->restrictOnDelete();
            $table->foreignId('libro_id')->constrained('libros')->cascadeOnDelete();
            $table->integer('orden')->nullable();
            $table->timestamp('fecha_agregado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genero_libro');
    }
};
