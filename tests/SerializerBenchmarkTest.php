<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

test('benchmark db operations performance', function () {
    // Reduce count to 100 because our driver does Read+Write on every Insert (slow IO)
    // 100 records is enough to see serializer overhead differences
    Config::set('database.connections.json.database', __DIR__ . '/database/json');

    $count = 100;
    $serializers = ['json', 'msgpack', 'json_gzip', 'yaml'];

    // Generate Data
    $dummyData = [];
    for ($i = 0; $i < $count; $i++) {
        $dummyData[] = [
            'name' => "User " . $i,
            'email' => "user{$i}@example.com",
            'bio' => str_repeat("Benchmark Text Data ", 10),
            'metadata' => [
                'login_count' => rand(1, 1000),
                'history' => array_combine(range(1, 5), array_fill(0, 5, time())),
            ]
        ];
    }

    echo "\n\n📊 DB:: CRUD BENCHMARK ($count Records - Read+Write per Insert)\n";
    echo str_pad("Serializer", 12) . str_pad("Insert(ms)", 12) . str_pad("Select(ms)", 12) . str_pad("Size(KB)", 12) . "\n";
    echo str_repeat("-", 50) . "\n";

    foreach ($serializers as $serializer) {
        $table = 'bench_' . $serializer;

        // Configure & Reconnect
        Config::set('database.connections.json.serializer', $serializer);
        DB::purge('json');

        // Ensure clean state (delete file if exists)
        $dbPath = Config::get('database.connections.json.database');
        if (!file_exists($dbPath)) mkdir($dbPath, 0755, true);

        $ext = match ($serializer) {
            'msgpack' => 'msgpack',
            'json_gzip' => 'json.gz',
            'yaml' => 'yaml',
            default => 'json'
        };
        $file = $dbPath . '/' . $table . '.' . $ext;
        if (file_exists($file)) unlink($file);

        // 2. Measure Insert (Loop)
        $startWrite = microtime(true);
        foreach ($dummyData as $row) {
            DB::table($table)->insert($row);
        }
        $endWrite = microtime(true);

        // 3. Measure Read
        $startRead = microtime(true);
        $results = DB::table($table)->get();
        expect($results->count())->toBe($count);
        $endRead = microtime(true);

        // 4. Measure Size
        $size = file_exists($file) ? filesize($file) / 1024 : 0;

        echo str_pad($serializer, 12)
            . str_pad(number_format(($endWrite - $startWrite) * 1000, 2), 12)
            . str_pad(number_format(($endRead - $startRead) * 1000, 2), 12)
            . str_pad(number_format($size, 2), 12)
            . "\n";
    }

    echo "\n";
});
