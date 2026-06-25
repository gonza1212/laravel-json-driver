<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

class JsonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (empty(config('database.connections.json'))) {
            config([
                'database.connections.json' => [
                    'driver'   => 'json',
                    'database' => env('DB_DATABASE', storage_path('app/json-db')),
                    'prefix'   => '',
                ],
            ]);
        }

        $this->app->singleton('db', function ($app) {
            $manager = new DatabaseManager($app, $app['db.factory']);

            $manager->extend('json', function (array $config, string $name) {
                $config['name'] = $name;

                $pdo = new JsonPdo();

                return new JsonConnection(
                    $pdo,
                    $config['database'],
                    $config['prefix'] ?? '',
                    $config,
                );
            });

            return $manager;
        });
    }
}
