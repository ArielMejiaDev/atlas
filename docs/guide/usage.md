# Usage

This page covers column mapping patterns, multiple models, batch geocoding, and other usage details. For basic geocoding, scopes, and distance queries, see [Basic Usage](/guide/basic-usage).

## Column Mapping

Atlas supports four patterns for mapping your model's columns to geocoder input. Choose the one that fits your schema.

### Pattern 1: Standard Columns (Zero Config)

If your model has columns named `address`, `city`, `state`, `zip`, and `country`, it works automatically:

```php
class Address extends Model
{
    use HasCoordinates;
    // That's it — default mapping matches standard column names
}
```

### Pattern 2: Custom Column Names

Override `geocodableColumns()` to map canonical keys to your actual column names:

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

### Pattern 3: Partial Data

Only map the keys your model has. Unmapped keys default to empty strings:

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

### Pattern 4: Single Column / Full Control

Override `toGeocodableArray()` for complete control over the input. Use this when your data doesn't fit the split-column model:

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

You can also use accessors, computed values, or combine multiple columns:

```php
public function toGeocodableArray(): array
{
    return [
        'address' => "{$this->street_line_1} {$this->street_line_2}",
        'city'    => $this->city,
        'state'   => $this->state,
        'zip'     => $this->zip,
        'country' => $this->country_code === 'US'
            ? 'United States'
            : $this->country_name,
    ];
}
```

## Multiple Models

Atlas stores coordinates in a shared polymorphic table, so any number of models can be geocoded independently:

```php
class Address extends Model
{
    use HasCoordinates;
}

class Store extends Model
{
    use HasCoordinates;

    public function geocodableColumns(): array
    {
        return [
            'address' => 'location_address',
            'city'    => 'location_city',
            'state'   => 'location_state',
            'zip'     => 'location_zip',
            'country' => 'location_country',
        ];
    }
}

class Warehouse extends Model
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

Each model manages its own mapping. Geocode them all the same way:

```php
$address->geocode();
$store->geocode();
$warehouse->geocode();
```

## Using the Facade

For one-off geocoding without a model, use the `Atlas` facade directly:

```php
use ArielMejiaDev\Atlas\Facades\Atlas;

$result = Atlas::geocode([
    'address' => '123 Main St',
    'city'    => 'Broken Bow',
    'state'   => 'NE',
    'zip'     => '68815',
    'country' => 'US',
]);

if ($result) {
    echo $result->latitude;   // 41.4019
    echo $result->longitude;  // -99.6393
    echo $result->method;     // 'us_zip'
}
```

## Batch Geocoding

Geocode multiple inputs in a single call. Array keys are preserved so you can map results back to inputs:

```php
use ArielMejiaDev\Atlas\Facades\Atlas;

$results = Atlas::geocodeBatch([
    'order_123' => ['zip' => '68815', 'country' => 'US'],
    'order_456' => ['city' => 'Paris', 'country' => 'France'],
    'order_789' => ['city' => 'Tokyo', 'country' => 'Japan'],
]);

foreach ($results as $key => $result) {
    if ($result) {
        echo "$key: {$result->latitude}, {$result->longitude}\n";
    }
}
```

The country aliases are pre-loaded once before the batch, so this is more efficient than calling `geocode()` in a loop when processing the first batch of the application lifecycle.

## Using Dependency Injection

You can also inject `OfflineGeocoder` directly:

```php
use ArielMejiaDev\Atlas\OfflineGeocoder;

class AddressController extends Controller
{
    public function store(Request $request, OfflineGeocoder $geocoder)
    {
        $result = $geocoder->geocode(
            $request->only('address', 'city', 'state', 'zip', 'country')
        );

        // Use $result->latitude, $result->longitude...
    }
}
```

## Input Format

The `geocode()` method expects an array with these canonical keys:

| Key | Type | Description |
|-----|------|-------------|
| `address` | `string` | Street address |
| `city` | `string` | City name |
| `state` | `string` | State/province/region |
| `zip` | `string` | Postal/ZIP code |
| `country` | `string` | Country name or code |

All keys are optional. Atlas needs at least one non-empty field to attempt a lookup.

::: tip
You don't need to provide all fields. Atlas tries multiple strategies in order and will work with whatever data you have. A ZIP code alone is often enough for US addresses. A city + country is enough for international.
:::

## The Result Object

The facade and DI return a `Result` value object or `null`:

```php
ArielMejiaDev\Atlas\Support\Result {
    public readonly float $latitude;
    public readonly float $longitude;
    public readonly string $method;    // Which strategy matched
    public readonly string $note;      // Optional context
}
```

Convert to array:

```php
$result->toArray();
// ['latitude' => 41.4019, 'longitude' => -99.6393, 'method' => 'us_zip', 'note' => '']
```

## The Coordinate Model

The `$model->geocode()` method returns a `Coordinate` model (or `null`):

```php
ArielMejiaDev\Atlas\Models\Coordinate {
    public float $latitude;
    public float $longitude;
    public ?string $method;
    public ?Carbon $geocoded_at;
}
```

Access it through the relationship:

```php
$address->coordinates->latitude;
$address->coordinates->longitude;
$address->coordinates->method;
$address->coordinates->geocoded_at;
```

## Handling Null Results

`geocode()` returns `null` when no geocoding strategy could resolve the input:

```php
$result = Atlas::geocode($input);

if ($result === null) {
    Log::warning('Could not geocode address', $input);
}
```
