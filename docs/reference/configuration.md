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
    // This database is read-only at runtime, so concurrent queue workers
    // and web requests can safely share the same file without contention.
    'database_path' => database_path('geocoding.sqlite'),

    // Name of the database connection Atlas registers at runtime.
    // The host doesn't need to add anything to config/database.php.
    'connection_name' => 'atlas',

    // Set to true to let Atlas register the database connection automatically.
    // Set to false if you want to define the connection yourself.
    'manage_connection' => true,

    // Listener configuration for auto-geocoding records.
    'listener' => [
        'enabled' => false,           // Set to true to auto-geocode

        // Model classes to auto-geocode.
        // Each must use the HasCoordinates trait.
        'models' => [
            // App\Models\Address::class,
            // App\Models\Store::class,
        ],

        'on_update' => true,          // Re-geocode when address fields change
        'queue'     => null,           // Queue connection name; null = default
        'delay'     => 2,              // Seconds to wait before the job runs
        'tries'     => 3,              // Number of retry attempts
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

### `listener`

- **Type:** `array`

Controls the auto-geocoding listener. See [Auto-Geocoding](/guide/auto-geocoding) for details.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | `bool` | `false` | Enable auto-geocoding on `Model::created` and `Model::updated` |
| `models` | `array` | `[]` | Model classes to auto-geocode (must use `HasCoordinates` trait) |
| `on_update` | `bool` | `true` | Re-geocode when address fields change on update |
| `queue` | `string\|null` | `null` | Queue connection name |
| `delay` | `int` | `2` | Seconds before the job runs |
| `tries` | `int` | `3` | Number of retry attempts |

### `us_country_names`

- **Type:** `array`
- **Default:** Common US name variants

List of country strings that should trigger the US-specific geocoding path (`us_zip` and `us_city_state`). Add your own variants if your data uses non-standard country names.

## Column Mapping

Column mapping is **per-model**, not in the config file. Each model that uses the `HasCoordinates` trait controls its own mapping:

```php
use ArielMejiaDev\Atlas\Concerns\HasCoordinates;

class Store extends Model
{
    use HasCoordinates;

    // Override for custom column names
    public function geocodableColumns(): array
    {
        return [
            'address' => 'store_address',
            'city'    => 'store_city',
            'state'   => 'province',
            'zip'     => 'postal_code',
            'country' => 'country_name',
        ];
    }
}
```

See [Usage — Column Mapping](/guide/usage#column-mapping) for all four mapping patterns.
