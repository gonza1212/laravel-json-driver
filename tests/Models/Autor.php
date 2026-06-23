<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Autor extends Model
{
    protected $guarded = [];

    protected $table = 'autores';

    public function perfil(): HasOne
    {
        return $this->hasOne(Perfil::class);
    }

    public function libros(): HasMany
    {
        return $this->hasMany(Libro::class);
    }
}
