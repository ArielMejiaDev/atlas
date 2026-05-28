# Basic Usage

Now that Atlas is installed, here's how to use it in your day-to-day workflow.

## Geocode a Model

Add the `HasCoordinates` trait and call `geocode()`:

```php
use ArielMejiaDev\Atlas\Concerns\HasCoordinates;

class Address extends Model
{
    use HasCoordinates;
}
```

```php
$address = Address::find(1);
$coordinate = $address->geocode();

$coordinate->latitude;   // 41.4019
$coordinate->longitude;  // -99.6393
$coordinate->method;     // 'us_zip'
```

The `geocode()` method reads the model's address fields, looks them up in the local SQLite database, and persists the result to the `atlas_coordinates` table. Calling it again updates the existing coordinate — no duplicates.

## Access Coordinates

Coordinates are stored via a polymorphic `morphOne` relationship:

```php
// Single model
$address->coordinates->latitude;
$address->coordinates->longitude;
$address->coordinates->method;
$address->coordinates->geocoded_at;

// Eager load to avoid N+1
$addresses = Address::with('coordinates')->get();

foreach ($addresses as $address) {
    $address->coordinates?->latitude;
    $address->coordinates?->longitude;
}
```

## Query Scopes

Filter models by their geocoding status:

```php
// Models that have coordinates
Address::geocoded()->get();

// Models still missing coordinates
Address::notGeocoded()->get();
```

## Find Nearby Models

Use `withinRadius` to find models near a point, and `distanceTo` for exact distances:

```php
// Find stores within 25 km of the user
$stores = Store::withinRadius($userLat, $userLng, 25)
    ->with('coordinates')
    ->get();

// Calculate exact distance for each result
foreach ($stores as $store) {
    $km = $store->distanceTo($userLat, $userLng);
    echo "{$store->name}: {$km} km away";
}
```

`withinRadius` uses a fast bounding-box filter at the database level (works on MySQL, PostgreSQL, and SQLite). `distanceTo` applies the Haversine formula in PHP for precise kilometer distances. Combine them for efficient "nearest" queries:

```php
$origin = [48.8566, 2.3522]; // Paris

$nearby = Store::withinRadius($origin[0], $origin[1], 100)
    ->with('coordinates')
    ->get()
    ->map(fn ($store) => [
        'store'    => $store,
        'distance' => $store->distanceTo($origin[0], $origin[1]),
    ])
    ->sortBy('distance');
```

## Auto-Geocode on Create and Update

Enable the listener to geocode models automatically via queued jobs:

```php
// config/atlas.php
'listener' => [
    'enabled' => true,
    'models'  => [
        App\Models\Address::class,
        App\Models\Store::class,
    ],
],
```

When a listed model is **created**, a queued job geocodes it and stores the coordinates.

When address fields **change on update**, the coordinates are refreshed automatically — no stale data.

```php
// This triggers auto-geocoding on create
$address = Address::create([
    'city'    => 'Paris',
    'country' => 'France',
]);

// This triggers re-geocoding on update (address fields changed)
$address->update(['city' => 'Lyon']);
```

See [Auto-Geocoding](/guide/auto-geocoding) for queue configuration, events, and custom observers.

## Use the Facade Directly

For one-off lookups without a model:

```php
use ArielMejiaDev\Atlas\Facades\Atlas;

$result = Atlas::geocode([
    'city'    => 'Paris',
    'country' => 'France',
]);

$result->latitude;  // 48.8534
$result->longitude; // 2.3488
$result->method;    // 'city_exact'
```

You don't need all five fields — Atlas cascades through [9 strategies](/guide/methods) and works with whatever data you have. A ZIP code alone is enough for US addresses. A city + country is enough internationally.

## Custom Column Names

If your model's columns don't match the defaults (`address`, `city`, `state`, `zip`, `country`), map them:

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

See [Usage — Column Mapping](/guide/usage#column-mapping) for all four mapping patterns including partial data and single-column addresses.

## Next Steps

- [Usage](/guide/usage) — Column mapping patterns, multiple models, batch geocoding, dependency injection
- [Backfill Command](/guide/backfill) — Geocode existing records in bulk
- [Auto-Geocoding](/guide/auto-geocoding) — Queued listeners, events, observers, and update handling
- [Geocoding Methods](/guide/methods) — How the 9-method cascade works
- [Extending Atlas](/guide/extending) — Custom geocoders, normalizers, and hybrid setups
