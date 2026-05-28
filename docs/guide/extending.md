# Extending Atlas

Atlas is designed to be extended. The core geocoder implements the `GeocoderDriver` interface, making it easy to wrap, decorate, or replace.

## The GeocoderDriver Interface

```php
namespace ArielMejiaDev\Atlas\Contracts;

use ArielMejiaDev\Atlas\Support\Result;

interface GeocoderDriver
{
    public function geocode(array $input): ?Result;
}
```

## Adding an Online Fallback

A common pattern is to wrap Atlas with an online geocoder that handles `country_centroid` or `null` results:

```php
namespace App\Geocoding;

use ArielMejiaDev\Atlas\Contracts\GeocoderDriver;
use ArielMejiaDev\Atlas\Support\Result;

class HybridGeocoder implements GeocoderDriver
{
    public function __construct(
        private GeocoderDriver $offline,
        private OnlineGeocoderService $online,
    ) {}

    public function geocode(array $input): ?Result
    {
        $result = $this->offline->geocode($input);

        // If the offline result is low-confidence, try online
        if ($result === null || in_array($result->method, [
            'country_centroid',
            'text_extract_country_centroid',
            'global_big_city_match',
        ])) {
            $onlineResult = $this->online->geocode($input);

            if ($onlineResult !== null) {
                return $onlineResult;
            }
        }

        return $result;
    }
}
```

Register it in your service provider:

```php
use ArielMejiaDev\Atlas\Contracts\GeocoderDriver;
use ArielMejiaDev\Atlas\OfflineGeocoder;

$this->app->singleton(GeocoderDriver::class, function ($app) {
    return new HybridGeocoder(
        $app->make(OfflineGeocoder::class),
        $app->make(OnlineGeocoderService::class),
    );
});
```

## Custom Normalizer

The `Normalizer` is a regular class that you can extend:

```php
namespace App\Geocoding;

use ArielMejiaDev\Atlas\Support\Normalizer;

class CustomNormalizer extends Normalizer
{
    public function normalize(string $s): string
    {
        // Pre-process: expand known abbreviations
        $s = str_replace(['St.', 'Ave.', 'Blvd.'], ['Street', 'Avenue', 'Boulevard'], $s);

        return parent::normalize($s);
    }
}
```

Bind it in your service provider:

```php
$this->app->singleton(Normalizer::class, CustomNormalizer::class);
```

::: warning
If you customize the normalizer, you must rebuild the database so that the `name_norm` values in SQLite match your runtime normalization. The builder uses the same `Normalizer` class.
:::

## Customizing the Column Mapping

Each model controls its own mapping. For advanced use cases, you can override `toGeocodableArray()` to pull data from relationships, accessors, or external sources:

```php
class Order extends Model
{
    use HasCoordinates;

    public function toGeocodableArray(): array
    {
        // Pull address from a related model
        $shipping = $this->shippingAddress;

        return [
            'address' => $shipping?->line_1 ?? '',
            'city'    => $shipping?->city ?? '',
            'state'   => $shipping?->state ?? '',
            'zip'     => $shipping?->postal_code ?? '',
            'country' => $shipping?->country ?? '',
        ];
    }
}
```

## Listening to Geocode Events

Subscribe to `AddressGeocoded` to run side effects:

```php
use ArielMejiaDev\Atlas\Events\AddressGeocoded;

// In EventServiceProvider
protected $listen = [
    AddressGeocoded::class => [
        NotifyGeocodeComplete::class,
        UpdateSearchIndex::class,
    ],
];
```

## Managing the Database Connection

By default, Atlas registers its own SQLite connection. If you need custom settings:

```php
// config/atlas.php
'manage_connection' => false, // Disable auto-registration
'connection_name' => 'my_geocoder',
```

Then add the connection yourself in `config/database.php`:

```php
'connections' => [
    'my_geocoder' => [
        'driver' => 'sqlite',
        'database' => database_path('geocoding.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => false,
    ],
],
```
