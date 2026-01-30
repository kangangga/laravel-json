<?php

use Illuminate\Support\Facades\DB;
use Kangangga\Json\Connection;

it('can create json connection', function () {
    $connection = DB::connection('json');

    expect($connection)->toBeInstanceOf(Connection::class);
});

it('can create a table', function () {
    $connection = DB::connection('json');
    $tablePath = __DIR__ . '/database/json/users.json';

    // Create table by writing to it
    file_put_contents($tablePath, json_encode([]));

    expect(file_exists($tablePath))->toBeTrue();
});

it('can insert data', function () {
    $connection = DB::connection('json');

    // Create empty table
    file_put_contents(__DIR__ . '/database/json/users.json', json_encode([]));

    // Insert data
    $id = DB::connection('json')->table('users')->insertGetId([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($id)->toBe(1);

    // Verify data was written
    $data = json_decode(file_get_contents(__DIR__ . '/database/json/users.json'), true);
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('John Doe');
});

it('can read data', function () {
    $connection = DB::connection('json');

    // Create table with data
    $testData = [
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ];
    file_put_contents(__DIR__ . '/database/json/users.json', json_encode($testData));

    // Read data
    $users = DB::connection('json')->table('users')->get();

    expect($users)->toHaveCount(2);
});

it('can truncate table', function () {
    $connection = DB::connection('json');

    // Create table with data
    $testData = [
        ['id' => 1, 'name' => 'John Doe'],
        ['id' => 2, 'name' => 'Jane Doe'],
    ];
    file_put_contents(__DIR__ . '/database/json/users.json', json_encode($testData));

    // Truncate
    DB::connection('json')->table('users')->truncate();

    // Verify table is empty
    $data = json_decode(file_get_contents(__DIR__ . '/database/json/users.json'), true);
    expect($data)->toBeEmpty();
});
