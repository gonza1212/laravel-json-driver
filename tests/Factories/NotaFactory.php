<?php

declare(strict_types=1);

namespace Database\Factories;

use Gonza1212\JsonDriver\Tests\Models\Nota;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotaFactory extends Factory
{
    protected $model = Nota::class;

    public function definition(): array
    {
        $faker = fake();

        return [
            'titulo' => $faker->sentence(),
        ];
    }
}
