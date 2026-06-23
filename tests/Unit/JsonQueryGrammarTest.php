<?php

declare(strict_types=1);

namespace Gonza1212\JsonDriver\Tests;

use Gonza1212\JsonDriver\JsonConnection;
use Gonza1212\JsonDriver\JsonQueryGrammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Connection;

test('compileInsert normaliza flat array a array envuelto', function () {
    $pdo = new \Gonza1212\JsonDriver\JsonPdo();
    $connection = new JsonConnection($pdo, __DIR__ . '/../storage/test');
    $grammar = new JsonQueryGrammar($connection);

    $query = new Builder(
        $connection,
        $grammar,
        $connection->getPostProcessor(),
    );
    $query->from = 'notas';

    $result = $grammar->compileInsert($query, ['titulo' => 'hola']);
    $decoded = json_decode($result, true);

    expect($decoded['values'])->toBe([['titulo' => 'hola']]);
});

test('compileInsert no envuelve doblemente array ya envuelto', function () {
    $pdo = new \Gonza1212\JsonDriver\JsonPdo();
    $connection = new JsonConnection($pdo, __DIR__ . '/../storage/test2');
    $grammar = new JsonQueryGrammar($connection);

    $query = new Builder(
        $connection,
        $grammar,
        $connection->getPostProcessor(),
    );
    $query->from = 'notas';

    $result = $grammar->compileInsert($query, [['titulo' => 'a'], ['titulo' => 'b']]);
    $decoded = json_decode($result, true);

    expect($decoded['values'])->toBe([['titulo' => 'a'], ['titulo' => 'b']]);
});

test('compileSelect serializa componentes del query builder', function () {
    $pdo = new \Gonza1212\JsonDriver\JsonPdo();
    $connection = new JsonConnection($pdo, __DIR__ . '/../storage/test3');
    $grammar = new JsonQueryGrammar($connection);

    $query = new Builder(
        $connection,
        $grammar,
        $connection->getPostProcessor(),
    );
    $query->from = 'notas';
    $query->wheres = [
        ['type' => 'Basic', 'column' => 'titulo', 'operator' => '=', 'value' => 'hola', 'boolean' => 'and'],
    ];
    $query->orders = [
        ['column' => 'id', 'direction' => 'desc'],
    ];
    $query->limit = 10;
    $query->offset = 5;

    $result = $grammar->compileSelect($query);
    $decoded = json_decode($result, true);

    expect($decoded['type'])->toBe('select');
    expect($decoded['table'])->toBe('notas');
    expect($decoded['columns'])->toBe(['*']);
    expect($decoded['wheres'])->toHaveCount(1);
    expect($decoded['orders'])->toHaveCount(1);
    expect($decoded['limit'])->toBe(10);
    expect($decoded['offset'])->toBe(5);
});

test('compileUpdate serializa tabla valores y wheres', function () {
    $pdo = new \Gonza1212\JsonDriver\JsonPdo();
    $connection = new JsonConnection($pdo, __DIR__ . '/../storage/test4');
    $grammar = new JsonQueryGrammar($connection);

    $query = new Builder(
        $connection,
        $grammar,
        $connection->getPostProcessor(),
    );
    $query->from = 'notas';

    $result = $grammar->compileUpdate($query, ['titulo' => 'mundo']);
    $decoded = json_decode($result, true);

    expect($decoded['type'])->toBe('update');
    expect($decoded['table'])->toBe('notas');
    expect($decoded['values'])->toBe(['titulo' => 'mundo']);
});

test('compileDelete serializa tabla y wheres', function () {
    $pdo = new \Gonza1212\JsonDriver\JsonPdo();
    $connection = new JsonConnection($pdo, __DIR__ . '/../storage/test5');
    $grammar = new JsonQueryGrammar($connection);

    $query = new Builder(
        $connection,
        $grammar,
        $connection->getPostProcessor(),
    );
    $query->from = 'notas';

    $result = $grammar->compileDelete($query);
    $decoded = json_decode($result, true);

    expect($decoded['type'])->toBe('delete');
    expect($decoded['table'])->toBe('notas');
    expect($decoded['wheres'])->toBe([]);
});
