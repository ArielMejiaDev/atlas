<?php

return [
    // Path to the SQLite file the package reads from. Built by the install command.
    'database_path' => database_path('geocoding.sqlite'),

    // Name of the database connection the package will register at runtime.
    // The host doesn't need to add anything to config/database.php — the
    // package registers it dynamically pointing at `database_path` above.
    'connection_name' => 'atlas',

    // Set to true to let the package register the database connection automatically.
    'manage_connection' => true,

    // The model class to backfill. Used by atlas:backfill.
    // The class doesn't need to implement Geocodable — the package will read
    // the column mapping below.
    'model' => env('ATLAS_MODEL'),

    // Column mapping. Keys are the canonical input names the geocoder needs.
    'columns' => [
        'address' => 'address',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip',
        'country' => 'country',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'deleted_at' => 'deleted_at', // set to null if model isn't soft-deletable
    ],

    // Listener configuration for auto-geocoding new records.
    'listener' => [
        'enabled' => false,           // host opts in
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
