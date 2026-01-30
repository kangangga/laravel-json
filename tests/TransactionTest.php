<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;


test('transaction commit saves data', function () {
    $table = 'trx_commit';
    DB::table($table)->truncate();

    // 1. Start Transaction
    DB::beginTransaction();

    // 2. Insert Data (into Buffer)
    DB::table($table)->insert(['name' => 'Buffer User']);

    // Check if data is visible INSIDE transaction (should read from buffer)
    expect(DB::table($table)->count())->toBe(1);
    expect(DB::table($table)->first()->name)->toBe('Buffer User');

    // 3. Commit
    DB::commit();

    // Check if data persists AFTER commit
    // Re-read fresh from disk logic (technically logic is same, but buffer is cleared)
    expect(DB::table($table)->count())->toBe(1);
    expect(DB::table($table)->first()->name)->toBe('Buffer User');
});

test('transaction rollback discards data', function () {
    $table = 'trx_rollback';
    DB::table($table)->truncate();

    // Initial State
    DB::table($table)->insert(['name' => 'Existing User']);
    expect(DB::table($table)->count())->toBe(1);

    // 1. Start Transaction
    DB::beginTransaction();

    // 2. Insert Bad Data
    DB::table($table)->insert(['name' => 'Bad User']);

    // Inside transaction: 2 users
    expect(DB::table($table)->count())->toBe(2);

    // 3. Rollback
    DB::rollBack();

    // Outside transaction: Should back to 1 user
    expect(DB::table($table)->count())->toBe(1);
    expect(DB::table($table)->first()->name)->toBe('Existing User');
});

test('transaction closure auto-rollback on exception', function () {
    $table = 'trx_exception';
    DB::table($table)->truncate();

    try {
        DB::transaction(function () use ($table) {
            DB::table($table)->insert(['name' => 'Ghost User']);
            throw new Exception("Something went wrong!");
        });
    } catch (Exception $e) {
        // Expected exception
    }

    // Should be empty because of rollback
    expect(DB::table($table)->count())->toBe(0);
});
