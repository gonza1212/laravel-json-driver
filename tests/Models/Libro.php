<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Libro extends Model
{
    protected $guarded = [];

    protected $table = 'libros';

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Autor::class);
    }

    public function generos(): BelongsToMany
    {
        return $this->belongsToMany(Genero::class, 'genero_libro')
            ->withPivot(['orden', 'fecha_agregado']);
    }
}
