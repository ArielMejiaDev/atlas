# Usage

## Using the Facade

The simplest way to geocode is through the `Atlas` facade:

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
    echo $result->note;       // '' (optional context)
}
```

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

        if ($result) {
            $request->merge([
                'latitude' => $result->latitude,
                'longitude' => $result->longitude,
            ]);
        }

        // Save the model...
    }
}
```

## Input Format

The `geocode()` method expects an array with these canonical keys:

| Key | Type | Description |
|-----|------|-------------|
| `address` | `string` | Street address (required — returns `null` if empty) |
| `city` | `string` | City name |
| `state` | `string` | State/province/region |
| `zip` | `string` | Postal/ZIP code |
| `country` | `string` | Country name or code |

All keys are optional except `address` (which must be non-empty for the geocoder to attempt any lookup).

::: tip
You don't need to provide all fields. Atlas tries multiple strategies in order and will work with whatever data you have. A ZIP code alone is often enough for US addresses.
:::

## The Result Object

`geocode()` returns a `Result` value object or `null`:

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

## Handling Null Results

`geocode()` returns `null` when:

- The `address` field is empty
- No geocoding strategy could resolve the input

```php
$result = Atlas::geocode($input);

if ($result === null) {
    Log::warning('Could not geocode address', $input);
    return;
}
```

## Column Mapping

Atlas doesn't assume your model uses columns named `address`, `city`, etc. Configure the mapping in `config/atlas.php`:

```php
'columns' => [
    'address'   => 'street_address',  // your column name
    'city'      => 'city_name',
    'state'     => 'region',
    'zip'       => 'postal_code',
    'country'   => 'country_name',
    'latitude'  => 'lat',
    'longitude' => 'lng',
    'deleted_at' => 'deleted_at',
],
```

The backfill command and auto-geocoding listener read these mappings automatically.

## Geocodable Interface

For cleaner model integration, implement the optional `Geocodable` interface:

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

Then in your code:

```php
$result = Atlas::geocode($address->toGeocodableArray());
```
