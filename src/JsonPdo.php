<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver;

use PDO;

class JsonPdo extends PDO
{
    public string|false $lastInsertedId = false;

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertedId;
    }
}
