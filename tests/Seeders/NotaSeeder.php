<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests\Seeders;

use Gonza1212\JsonDriver\Tests\Models\Nota;
use Illuminate\Database\Seeder;

class NotaSeeder extends Seeder
{
    public function run(): void
    {
        Nota::create(['titulo' => 'Primer post']);
        Nota::create(['titulo' => 'Segundo post']);
        Nota::create(['titulo' => 'Tercer post']);
    }
}
