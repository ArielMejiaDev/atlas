# Atlas — Offline Geocoder for Laravel

[![run-tests](https://github.com/arielmejiadev/atlas/actions/workflows/run-tests.yml/badge.svg)](https://github.com/arielmejiadev/atlas/actions/workflows/run-tests.yml)

Fills `latitude` and `longitude` on any Eloquent model from a bundled SQLite database of world cities and US ZIP codes. **No API keys, no rate limits, no network calls at runtime.**

> **Precision note:** Atlas provides centroid-level precision (city/ZIP center), not street-level geocoding. It's ideal for analytics, clustering, and approximate distance calculations.

## Installation

```bash
composer require arielmejiadev/atlas
```

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
    'model' => env('ATLAS_MODEL'), // Your address model class
    'columns' => [
        'address' => 'address',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip',
        'country' => 'country',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'deleted_at' => 'deleted_at',
    ],
    'listener' => [
        'enabled' => false,
        'queue' => null,
        'delay' => 2,
        'tries' => 3,
    ],
];
```

## Usage

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

if ($result) {
    echo $result->latitude;   // 41.4019
    echo $result->longitude;  // -99.6393
    echo $result->method;     // 'us_zip'
}
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

## Backfill Command

Geocode existing records in bulk:

```bash
php artisan atlas:backfill
php artisan atlas:backfill --model=App\\Models\\Address
php artisan atlas:backfill --chunk=1000
php artisan atlas:backfill --force      # re-geocode all
php artisan atlas:backfill --dry-run    # preview without saving
php artisan atlas:backfill --id=42      # single record
```

## Auto-Geocode on Create (Listener)

Opt in via config:

```php
// config/atlas.php
'model' => App\Models\Address::class,
'listener' => [
    'enabled' => true,
    'queue' => 'default',
    'delay' => 2,
    'tries' => 3,
],
```

New records are automatically geocoded via a queued job.

### Alternative: Manual Observer

```php
// app/Observers/AddressObserver.php
use ArielMejiaDev\Atlas\Facades\Atlas;

public function created(Address $address): void
{
    $result = Atlas::geocode($address->only('address', 'city', 'state', 'zip', 'country'));

    if ($result) {
        $address->forceFill([
            'latitude' => $result->latitude,
            'longitude' => $result->longitude,
        ])->saveQuietly();
    }
}
```

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

## Geocodable Interface (Optional)

```php
use ArielMejiaDev\Atlas\Contracts\Geocodable;

class Address extends Model implements Geocodable
{
    public function toGeocodableArray(): array
    {
        return [
            'address' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->postal_code,
            'country' => $this->country_name,
        ];
    }
}
```

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
