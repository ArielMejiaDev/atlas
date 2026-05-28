# Atlas — Offline Geocoder for Laravel

[![run-tests](https://github.com/arielmejiadev/atlas/actions/workflows/run-tests.yml/badge.svg)](https://github.com/arielmejiadev/atlas/actions/workflows/run-tests.yml)

Fills `latitude` and `longitude` on any Eloquent model from a bundled SQLite database of world cities and US ZIP codes. **No API keys, no rate limits, no network calls at runtime.**

**[Read the full documentation](https://arielmejia.dev/atlas-docs/)**

> **Precision note:** Atlas provides centroid-level precision (city/ZIP center), not street-level geocoding. It's ideal for analytics, clustering, and approximate distance calculations.

## Installation

```bash
composer require arielmejiadev/atlas
```

## Run Migrations

```bash
php artisan migrate
```

This creates the `atlas_coordinates` polymorphic table where coordinates are stored.

## Build the Database

Atlas ships without the ~22 MB geocoding database. Build it at install time:

```bash
php artisan atlas:install
```

This downloads ~25 MB of public data from [GeoNames](https://www.geonames.org/) (CC BY 4.0) and builds a local SQLite file. Takes 30–90 seconds.

For air-gapped environments, download a prebuilt file:

```bash
php artisan atlas:install --from=https://your-host.com/geocoding.sqlite
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=atlas-config
```

Key options in `config/atlas.php`:

```php
return [
    'database_path' => database_path('geocoding.sqlite'),
    'connection_name' => 'atlas',
    'manage_connection' => true,
    'listener' => [
        'enabled' => false,
        'models' => [
            // App\Models\Address::class,
        ],
        'queue' => null,
        'delay' => 2,
        'tries' => 3,
    ],
];
```

## Usage

### Add the Trait

```php
use ArielMejiaDev\Atlas\Concerns\HasCoordinates;

class Address extends Model
{
    use HasCoordinates;
}
```

### Geocode a Model

```php
$address = Address::find(1);
$coordinate = $address->geocode();

$coordinate->latitude;   // 41.4019
$coordinate->longitude;  // -99.6393
$coordinate->method;     // 'us_zip'
```

### Facade

```php
use ArielMejiaDev\Atlas\Facades\Atlas;

$result = Atlas::geocode([
    'address' => '123 Main St',
    'city' => 'Broken Bow',
    'state' => 'NE',
    'zip' => '68815',
    'country' => 'US',
]);
```

### Dependency Injection

```php
use ArielMejiaDev\Atlas\OfflineGeocoder;

public function store(Request $request, OfflineGeocoder $geocoder)
{
    $result = $geocoder->geocode($request->only('address', 'city', 'state', 'zip', 'country'));
    // ...
}
```

## Column Mapping

Each model controls its own mapping. Four patterns are supported:

### Standard Columns (Zero Config)

```php
class Address extends Model
{
    use HasCoordinates;
    // Works if you have: address, city, state, zip, country columns
}
```

### Custom Column Names

```php
class Store extends Model
{
    use HasCoordinates;

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

### Partial Data

```php
class Venue extends Model
{
    use HasCoordinates;

    public function geocodableColumns(): array
    {
        return [
            'city'    => 'venue_city',
            'country' => 'venue_country',
        ];
    }
}
```

### Single Column / Full Control

```php
class Contact extends Model
{
    use HasCoordinates;

    public function toGeocodableArray(): array
    {
        return [
            'address' => $this->full_address ?? '',
            'city'    => '',
            'state'   => '',
            'zip'     => '',
            'country' => '',
        ];
    }
}
```

## Backfill Command

Geocode existing records in bulk:

```bash
php artisan atlas:backfill --model=App\\Models\\Address
php artisan atlas:backfill --model=App\\Models\\Store
php artisan atlas:backfill --model=App\\Models\\Address --chunk=1000
php artisan atlas:backfill --model=App\\Models\\Address --force      # re-geocode all
php artisan atlas:backfill --model=App\\Models\\Address --dry-run    # preview without saving
php artisan atlas:backfill --model=App\\Models\\Address --id=42      # single record
```

## Auto-Geocode on Create (Listener)

Opt in via config:

```php
// config/atlas.php
'listener' => [
    'enabled' => true,
    'models' => [
        App\Models\Address::class,
        App\Models\Store::class,
    ],
    'queue' => 'default',
    'delay' => 2,
    'tries' => 3,
],
```

New records are automatically geocoded via a queued job.

## Geocoding Methods

Atlas tries methods in order and returns on the first hit:

| # | Method | Condition |
|---|--------|-----------|
| 1 | `us_zip` | US country + valid ZIP code |
| 2 | `us_city_state` | US country + city + state |
| 3 | `city_exact` | International exact city match |
| 4 | `state_as_city` | State field used as city name |
| 5 | `city_partial` | Partial/substring city match |
| 6 | `country_centroid` | Falls back to country center |
| 7 | `text_extract_city` | Detects country from text, finds city |
| 8 | `text_extract_country_centroid` | Detected country's center |
| 9 | `global_big_city_match` | Last resort: matches big cities globally |

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- Extensions: `pdo_sqlite`, `zip`, `curl`
- Suggested: `intl` (better Unicode transliteration)

## Testing

```bash
composer test
```

## Data Attribution

Geocoding data sourced from [GeoNames](https://www.geonames.org/), licensed under [Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/).

## License

MIT. See [LICENSE.md](LICENSE.md).
