<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Perfil extends Model
{
    protected $guarded = [];

    protected $table = 'perfiles';

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Autor::class);
    }
}
