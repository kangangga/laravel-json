<?php

use Illuminate\Support\Facades\DB;

test('full crud lifecycle', function () {
    $table = 'users_crud';

    // Ensure clean state
    DB::table($table)->truncate();

    // 1. CREATE (Insert)
    // ---------------------------------------------------------
    DB::table($table)->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30, 'meta' => ['role' => 'admin']],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25, 'meta' => ['role' => 'user']],
        ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'age' => 45, 'meta' => ['role' => 'editor']],
    ]);

    expect(DB::table($table)->count())->toBe(3);


    // 2. READ (Select)
    // ---------------------------------------------------------

    // Basic Select
    $jane = DB::table($table)->where('email', 'jane@example.com')->first();
    expect($jane->name)->toBe('Jane Doe');

    // Filter with Operator
    $olderUsers = DB::table($table)->where('age', '>', 28)->get();
    expect($olderUsers)->toHaveCount(2); // John & Bob

    // Nested JSON Key Select (meta->role)
    // Note: Data is inserted as array, but stored as JSON string likely if not handled by Model casting? 
    // DB::table insert puts array -> json_encode -> string in file. So nested query logic is needed.
    $admins = DB::table($table)->where('meta->role', 'admin')->get();
    expect($admins)->toHaveCount(1);
    expect($admins->first()->name)->toBe('John Doe');


    // 3. UPDATE
    // ---------------------------------------------------------

    // Update simple field
    DB::table($table)->where('email', 'john@example.com')->update(['age' => 31]);

    $john = DB::table($table)->where('email', 'john@example.com')->first();
    expect($john->age)->toBe(31);

    // Update with multiple where
    $affected = DB::table($table)
        ->where('name', 'Jane Doe')
        ->update(['name' => 'Jane Smith']);

    expect($affected)->toBe(1);
    expect(DB::table($table)->where('email', 'jane@example.com')->first()->name)->toBe('Jane Smith');


    // 4. DELETE
    // ---------------------------------------------------------

    // Delete specific record
    DB::table($table)->where('curr_does_not_exist', 'true')->delete(); // Should delete nothing
    expect(DB::table($table)->count())->toBe(3);

    DB::table($table)->where('email', 'bob@example.com')->delete();
    expect(DB::table($table)->count())->toBe(2);

    // Delete by ID (common in Queue)
    // Assuming ID is auto-incremented by insert logic (which uses insertGetId logic we fixed)
    $firstUser = DB::table($table)->first();
    DB::table($table)->delete($firstUser->id);
    expect(DB::table($table)->count())->toBe(1);


    // 5. TRUNCATE
    // ---------------------------------------------------------
    DB::table($table)->truncate();
    expect(DB::table($table)->count())->toBe(0);
});
