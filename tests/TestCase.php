<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests;

use Gonza1212\JsonDriver\JsonServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = __DIR__ . '/../storage/json-db-test';

        $this->app['config']->set('database.connections.json', [
            'driver' => 'json',
            'database' => $this->storagePath,
        ]);
        $this->app['config']->set('database.default', 'json');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->storagePath);
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [JsonServiceProvider::class];
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($path);
    }
}
