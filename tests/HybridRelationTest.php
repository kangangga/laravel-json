<?php

use Kangangga\Json\Eloquent\Model as JsonModel;
use Illuminate\Database\Eloquent\Model as SqlModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


// Define SQL Model (Standard Laravel Model)
class SqlUser extends SqlModel
{
    protected $connection = 'sqlite';
    protected $table = 'sql_users';
    protected $guarded = [];
}

// Define JSON Model
class JsonPost extends JsonModel
{
    protected $table = 'json_posts';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(SqlUser::class);
    }
}

test('hybrid belongs to relation works', function () {
    // Setup sqlite connection explicitly
    config()->set('database.connections.sqlite', [
        'driver'   => 'sqlite',
        'database' => './tests/database/database.sqlite',
        'prefix'   => '',
    ]);

    // 1. Setup SQL Table (InMemory SQLite)
    // Ensure we are using default connection (sqlite memory usually in tests)
    Schema::connection('sqlite')->dropIfExists('sql_users');
    Schema::connection('sqlite')->create('sql_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $user = SqlUser::create(['name' => 'SQL Guy']);
    $userId = $user->id;

    // 2. Setup JSON Data
    $post = JsonPost::create(['title' => 'My Hybrid Post', 'user_id' => $userId]);

    // 3. Test Relation Access
    $loadedUser = $post->user;
    expect($loadedUser)->not->toBeNull()
        ->and($loadedUser->id)->toBe($userId)
        ->and($loadedUser->name)->toBe('SQL Guy');

    // 4. Test Eager Loading
    $postWithUser = JsonPost::with('user')->find($post->id);
    expect($postWithUser->relationLoaded('user'))->toBeTrue();
    expect($postWithUser->user)->not->toBeNull();
});
