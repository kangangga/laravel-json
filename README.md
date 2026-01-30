# Laravel JSON Database

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kangangga/laravel-json.svg?style=flat-square)](https://packagist.org/packages/kangangga/laravel-json)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/kangangga/laravel-json/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/kangangga/laravel-json/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/kangangga/laravel-json.svg?style=flat-square)](https://packagist.org/packages/kangangga/laravel-json)

A modern, robust, and feature-rich JSON Database driver for Laravel. Perfect for small applications, prototyping, or hybrid setups where you need the flexibility of NoSQL with the power of Laravel Eloquent.

## Features

- 🚀 **Zero Config Setup** - Works out of the box with auto-injected configurations.
- Eloquent ORM Support - Full support for Models, Relationships, and Query Scopes.
- 💡 **Hybrid Relations** - Seamlessly relate JSON models with SQL models (MySQL, PostgreSQL, SQLite).
- 🔍 **Powerful Query Builder** - Supports `where`, `orderBy`, `limit`, `offset`, and more.
- 📦 **Cache Driver** - Use JSON files as a high-performance cache store.
- 📨 **Queue & Batching** - Full support for Laravel Queues and Job Batching backed by JSON.
- 💾 **Session Driver** - Store user sessions in JSON files.
- 🔄 **Transactions** - Supports database transactions (commit/rollback).
- �️ **Soft Deletes** - Native support for soft deleting records.

## Installation

You can install the package via composer:

```bash
composer require kangangga/laravel-json
```

That's it! The package automatically registers itself and injects the necessary configurations into Laravel.

## Configuration

### Zero Configuration

By default, the package assumes sensible defaults. You can start using it immediately by setting your environment variables in `.env`:

```env
# Use JSON as the default database connection
DB_CONNECTION=json

# Optional: Use JSON for other services
CACHE_DRIVER=json
QUEUE_CONNECTION=json
SESSION_DRIVER=json
```

### Advanced Configuration (Optional)

If you need to customize storage paths or behavior, you can publish the configuration file:

```bash
php artisan vendor:publish --tag="json-config"
```

This will create `config/laravel-json.php`. Commonly used options:

```php
return [
    'connections' => [
        'json' => [
            'driver' => 'json',
            'database' => env('DB_JSON_DATABASE', database_path('json')), // Storage path
            'prefix' => env('DB_JSON_PREFIX', ''),
        ],
    ],
    // ...
];
```

## Usage

### 1. Eloquent Models

Create a model that extends `Kangangga\Json\Eloquent\Model`. This model will automatically use the `json` connection.

```php
namespace App\Models;

use Kangangga\Json\Eloquent\Model;

class Post extends Model
{
    // The table name corresponds to the JSON filename (e.g., storage/laravel-json/posts.json)
    protected $table = 'posts';

    protected $fillable = ['title', 'content', 'views'];
}
```

Now you can use standard Eloquent method:

```php
// Create
$post = Post::create(['title' => 'Hello World', 'views' => 0]);

// Update
$post->update(['views' => 100]);

// Query
$popularPosts = Post::where('views', '>', 50)->orderBy('title')->get();

// Delete
$post->delete();
```

### 2. Query Builder

You can use the `DB` facade to interact with JSON data directly.

```php
use Illuminate\Support\Facades\DB;

// Insert
DB::connection('json')->table('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Select
$users = DB::connection('json')->table('users')
    ->where('name', 'like', 'Jo%')
    ->get();
```

### 3. Hybrid Relationships (SQL <-> JSON)

One of the most powerful features is the ability to define relationships between JSON models and standard SQL models.

**Example: A JSON `Log` model belonging to a MySQL `User` model.**

```php
// App/Models/Log.php (JSON Model)
use Kangangga\Json\Eloquent\Model;

class Log extends Model {
    protected $connection = 'json';

    public function user() {
        // BelongsTo relation to a SQL model
        return $this->belongsTo(User::class);
    }
}

// App/Models/User.php (MySQL Model)
use Illuminate\Database\Eloquent\Model;

class User extends Model {
    protected $connection = 'mysql';

    public function logs() {
        // HasMany relation to a JSON model
        return $this->hasMany(Log::class);
    }
}
```

Usage is seamless:

```php
$user = User::find(1);
// Automatically queries the JSON database
$logs = $user->logs;

$log = Log::first();
// Automatically queries the MySQL database
echo $log->user->name;
```

### 4. Queue & Job Batching

To use JSON as your queue driver, update your `.env`:

```env
QUEUE_CONNECTION=json
```

This supports standard queue features including **Job Batching** (which usually requires database migrations). With this package, batching works out of the box using `job_batches.json`.

```php
Bus::batch([
    new ProcessPodcast($podcast),
    new OptimizePodcast($podcast),
])->dispatch();
```

### 5. Cache Driver

To use JSON files for caching (great for persistence across restarts without Redis):

```env
CACHE_DRIVER=json
```

```php
Cache::put('key', 'value', 600);
$value = Cache::get('key');
```

## Testing

You can use the provided test suite to verify the package behavior.

```bash
composer test
```

## Credits

- [Angga Saputra](https://github.com/kangangga)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
