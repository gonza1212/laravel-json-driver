<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genero extends Model
{
    protected $guarded = [];

    protected $table = 'generos';

    public function libros(): BelongsToMany
    {
        return $this->belongsToMany(Libro::class, 'genero_libro');
    }
}
