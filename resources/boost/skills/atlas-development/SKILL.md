---
name: atlas-development
description: Build and work with Atlas offline geocoding features, including model setup, geocoding, distance queries, and backfilling.
---

# Atlas Development

## When to use this skill

Use this skill when working with Atlas offline geocoding — adding coordinates to models, querying by distance/radius, backfilling existing records, or configuring auto-geocoding listeners.

## Setup

1. Install the geocoding database: `php artisan atlas:install`
2. Publish config (optional): `php artisan vendor:publish --tag=atlas-config`

## Adding Geocoding to a Model

Add the `HasCoordinates` trait and implement the `Geocodable` interface:

```php
use ArielMejiaDev\Atlas\Concerns\HasCoordinates;
use ArielMejiaDev\Atlas\Contracts\Geocodable;

class Store extends Model implements Geocodable
{
    use HasCoordinates;
}
```

### Custom Column Mapping

Override `geocodableColumns()` when your columns differ from the defaults (`address`, `city`, `state`, `zip`, `country`):

```php
public function geocodableColumns(): array
{
    return [
        'address' => 'street_line',
        'city'    => 'town',
        'state'   => 'region',
        'zip'     => 'postal_code',
        'country' => 'country_name',
    ];
}
```

### Full Input Control

Override `toGeocodableArray()` for computed or single-column addresses:

```php
public function toGeocodableArray(): array
{
    return [
        'address' => $this->full_address,
        'city'    => '',
        'state'   => '',
        'zip'     => $this->extractZip(),
        'country' => 'US',
    ];
}
```

## Geocoding

### Single Model

```php
$coordinate = $model->geocode(); // Returns Coordinate|null
```

### Direct Geocoder

```php
use ArielMejiaDev\Atlas\OfflineGeocoder;

$result = app(OfflineGeocoder::class)->geocode([
    'city'    => 'Berlin',
    'country' => 'Germany',
]);

// Result DTO: $result->latitude, $result->longitude, $result->method
```

### Batch Geocoding

```php
$results = app(OfflineGeocoder::class)->geocodeBatch([
    'a' => ['city' => 'Paris', 'country' => 'France'],
    'b' => ['zip' => '10001', 'country' => 'US'],
]);
// Array keys are preserved: $results['a'], $results['b']
```

## Geocoding Methods (Fallback Chain)

The geocoder tries these strategies in order and returns the first match:

1. `us_zip` — US ZIP code lookup
2. `us_city_state` — US city + state combination
3. `city_exact` — Exact city name + country match
4. `state_as_city` — State name treated as city lookup
5. `city_partial` — Partial/fuzzy city name match
6. `country_centroid` — Country center coordinates
7. `text_extract_city` — City name extracted from all text fields
8. `text_extract_country_centroid` — Country detected from text, returns centroid
9. `global_big_city_match` — Matches against major world cities

The `method` field on the result indicates which strategy succeeded.

## Distance and Radius Queries

```php
// Haversine distance in kilometers
$km = $model->distanceTo(48.8566, 2.3522);

// Bounding-box radius filter (all databases supported)
Store::withinRadius(40.7128, -74.0060, 25)->get();

// Combine with other scopes
Store::geocoded()->withinRadius($lat, $lng, 10)->where('active', true)->get();
```

## Query Scopes

```php
Store::geocoded()->get();      // Only models with coordinates
Store::notGeocoded()->get();   // Only models without coordinates
```

## Backfilling Existing Records

```bash
# Backfill all un-geocoded records
php artisan atlas:backfill --model="App\Models\Store"

# Force re-geocode all records
php artisan atlas:backfill --model="App\Models\Store" --force

# Preview without saving
php artisan atlas:backfill --model="App\Models\Store" --dry-run

# Process a single record
php artisan atlas:backfill --model="App\Models\Store" --id=42

# Custom chunk size
php artisan atlas:backfill --model="App\Models\Store" --chunk=1000
```

## Auto-Geocoding Listener

Configure in `config/atlas.php` to automatically geocode on model create/update:

```php
'listener' => [
    'enabled'   => env('ATLAS_LISTENER_ENABLED', false),
    'models'    => [App\Models\Store::class],
    'on_update' => true,  // Re-geocode when address fields change
    'queue'     => null,   // Queue connection (null = default)
    'delay'     => 2,      // Seconds delay before job runs
    'tries'     => 3,      // Retry attempts
],
```

The listener dispatches queued jobs (`GeocodeOnCreated`, `GeocodeOnUpdated`) and fires `AddressGeocoded` events on success.

## Events

Listen for `ArielMejiaDev\Atlas\Events\AddressGeocoded` to react after geocoding:

```php
use ArielMejiaDev\Atlas\Events\AddressGeocoded;

Event::listen(AddressGeocoded::class, function ($event) {
    // $event->model — the geocoded model
    // $event->result — the Result DTO
});
```

## Requirements

- `ext-pdo_sqlite` — required for the SQLite geocoding database
- `ext-zip` and `ext-curl` — required for database installation
- `ext-intl` — recommended for better Unicode transliteration
