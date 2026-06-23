<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    use HasFactory;

    protected static $factory = \Database\Factories\NotaFactory::class;

    protected $guarded = [];
}
