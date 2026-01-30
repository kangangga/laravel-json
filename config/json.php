<?php

// config for Kangangga/Json
return [
    'hybrid_force_default_connection' => false,

    'serializer' => env('DB_JSON_SERIALIZER', 'json'),

    /*
    |--------------------------------------------------------------------------
    | JSON Database Connection
    |--------------------------------------------------------------------------
    |
    | This is the configuration for the JSON database connection.
    | The database path is where all JSON files will be stored.
    |
    */
    'connections' => [
        'json' => [
            'driver' => 'json',
            'database' => env('DB_JSON_DATABASE', database_path('json')),
            'prefix' => env('DB_JSON_PREFIX', ''),
            'strict' => env('DB_JSON_STRICT', false),
            'keyType' => env('DB_JSON_KEY_TYPE', 'string'),

            /*
            |--------------------------------------------------------------------------
            | Serializer
            |--------------------------------------------------------------------------
            |
            | Choose the serialization format for storing data:
            | - 'json'       : Human-readable, slower, larger files
            | - 'msgpack'    : Binary, 2-3x faster, 30-50% smaller (requires: composer require rybakit/msgpack)
            | - 'json_gzip'  : Compressed JSON, smaller files, slightly slower
            | - 'yaml'       : Human-readable, standard YAML format
            |
            */
            'serializer' => env('DB_JSON_SERIALIZER', 'json'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for using JSON files as cache storage.
    | The table name will be used as the JSON filename (e.g., cache.json).
    |
    */
    'cache' => [
        'driver' => 'json',
        'connection' => 'json',
        'table' => env('CACHE_JSON_TABLE', 'cache'),
        'prefix' => env('CACHE_PREFIX', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for using JSON files as session storage.
    | The table name will be used as the JSON filename (e.g., sessions.json).
    |
    */
    'session' => [
        'driver' => 'json',
        'connection' => 'json',
        'table' => env('SESSION_JSON_TABLE', 'sessions'),
        'lifetime' => env('SESSION_LIFETIME', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for using JSON files as queue storage.
    | The table name will be used as the JSON filename (e.g., jobs.json).
    |
    */
    'queue' => [
        'driver' => 'json',
        'connection' => 'json',
        'table' => env('QUEUE_JSON_TABLE', 'jobs'),
        'queue' => env('QUEUE_JSON_NAME', 'default'),
        'retry_after' => env('QUEUE_JSON_RETRY_AFTER', 90),
    ],
];
