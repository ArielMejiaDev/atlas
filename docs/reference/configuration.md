# Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=atlas-config
```

## Full Config Reference

```php
// config/atlas.php

return [
    // Path to the SQLite file the package reads from.
    // Built by `php artisan atlas:install`.
    'database_path' => database_path('geocoding.sqlite'),

    // Name of the database connection Atlas registers at runtime.
    // The host doesn't need to add anything to config/database.php.
    'connection_name' => 'atlas',

    // Set to true to let Atlas register the database connection automatically.
    // Set to false if you want to define the connection yourself.
    'manage_connection' => true,

    // The model class used by atlas:backfill and the auto-geocoding listener.
    // Can also be overridden with --model= on the backfill command.
    'model' => env('ATLAS_MODEL'),

    // Column mapping. Keys are the canonical names Atlas expects.
    // Values are the actual column names on your model.
    'columns' => [
        'address'    => 'address',
        'city'       => 'city',
        'state'      => 'state',
        'zip'        => 'zip',
        'country'    => 'country',
        'latitude'   => 'latitude',
        'longitude'  => 'longitude',
        'deleted_at' => 'deleted_at', // null if model isn't soft-deletable
    ],

    // Listener configuration for auto-geocoding new records.
    'listener' => [
        'enabled' => false,     // Set to true to auto-geocode on Model::created
        'queue'   => null,      // Queue connection name; null = default
        'delay'   => 2,         // Seconds to wait before the job runs
        'tries'   => 3,         // Number of retry attempts
    ],

    // Country strings treated as US for the us_zip and us_city_state methods.
    'us_country_names' => [
        'United States of America (the)',
        'United States of America',
        'United States',
        'USA',
        'US',
    ],
];
```

## Option Details

### `database_path`

- **Type:** `string`
- **Default:** `database_path('geocoding.sqlite')`

Path where the geocoding SQLite database is stored. The `atlas:install` command writes to this path. The runtime geocoder reads from it.

### `connection_name`

- **Type:** `string`
- **Default:** `'atlas'`

The name of the database connection Atlas registers. You can query it directly with `DB::connection('atlas')` if needed.

### `manage_connection`

- **Type:** `bool`
- **Default:** `true`

When `true`, Atlas automatically registers a SQLite database connection using `database_path`. Set to `false` if you want to define the connection manually in `config/database.php`.

### `model`

- **Type:** `string|null`
- **Default:** `env('ATLAS_MODEL')`

Fully qualified class name of the Eloquent model used by the backfill command and auto-geocoding listener. Can be overridden per-command with `--model=`.

### `columns`

- **Type:** `array`

Maps canonical column names to your model's actual column names. Atlas never assumes column names — it always reads from this mapping.

Set `deleted_at` to `null` if your model doesn't use soft deletes.

### `listener`

- **Type:** `array`

Controls the auto-geocoding listener. See [Auto-Geocoding](/guide/auto-geocoding) for details.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | `bool` | `false` | Enable auto-geocoding on `Model::created` |
| `queue` | `string\|null` | `null` | Queue connection name |
| `delay` | `int` | `2` | Seconds before the job runs |
| `tries` | `int` | `3` | Number of retry attempts |

### `us_country_names`

- **Type:** `array`
- **Default:** Common US name variants

List of country strings that should trigger the US-specific geocoding path (`us_zip` and `us_city_state`). Add your own variants if your data uses non-standard country names.

## Environment Variables

| Variable | Config Key | Description |
|----------|-----------|-------------|
| `ATLAS_MODEL` | `atlas.model` | Default model class for backfill |
