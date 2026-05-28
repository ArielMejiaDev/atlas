<?php

return [
    // Path to the SQLite file the package reads from. Built by the install command.
    // This database is read-only at runtime, so concurrent queue workers
    // and web requests can safely share the same file without contention.
    'database_path' => database_path('geocoding.sqlite'),

    // Name of the database connection the package will register at runtime.
    // The host doesn't need to add anything to config/database.php — the
    // package registers it dynamically pointing at `database_path` above.
    'connection_name' => 'atlas',

    // Set to true to let the package register the database connection automatically.
    'manage_connection' => true,

    // Listener configuration for auto-geocoding records.
    'listener' => [
        'enabled' => env('ATLAS_LISTENER_ENABLED', false), // host opts in

        // Model classes to auto-geocode.
        // Each must use the HasCoordinates trait.
        'models' => [
            // App\Models\Address::class,
            // App\Models\Store::class,
        ],

        // Re-geocode when address fields change on update.
        // Only triggers when geocodable columns actually changed.
        'on_update' => true,

        'queue' => null,              // queue connection; null = default
        'delay' => 2,                 // seconds to wait before running
        'tries' => 3,
    ],

    // Country strings that should be treated as US.
    'us_country_names' => [
        'United States of America (the)',
        'United States of America',
        'United States',
        'USA',
        'US',
    ],
];
