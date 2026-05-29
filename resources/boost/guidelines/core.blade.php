## Atlas — Offline Geocoder for Laravel

Atlas fills latitude and longitude on Eloquent models from a bundled SQLite database. No API keys, no rate limits, no network calls. Precision is centroid-level (city/ZIP center), suitable for analytics, clustering, and approximate distance calculations.

### Installation

After requiring the package, build the local SQLite geocoding database:

@verbatim
<code-snippet name="Install the geocoding database" lang="bash">
php artisan atlas:install
</code-snippet>
@endverbatim

Publish the configuration file to customize database path, connection name, and listener settings:

@verbatim
<code-snippet name="Publish configuration" lang="bash">
php artisan vendor:publish --tag=atlas-config
</code-snippet>
@endverbatim

### Adding Geocoding to a Model

Add the `HasCoordinates` trait to any Eloquent model. The trait provides a polymorphic `coordinates()` relationship, geocoding methods, distance calculations, and query scopes.

@verbatim
<code-snippet name="Model setup with HasCoordinates" lang="php">
use ArielMejiaDev\Atlas\Concerns\HasCoordinates;
use ArielMejiaDev\Atlas\Contracts\Geocodable;

class Address extends Model implements Geocodable
{
    use HasCoordinates;
}
</code-snippet>
@endverbatim

### Column Mapping

By default the trait maps `address`, `city`, `state`, `zip`, and `country` columns. Override `geocodableColumns()` when your model uses different column names:

@verbatim
<code-snippet name="Custom column mapping" lang="php">
public function geocodableColumns(): array
{
    return [
        'address' => 'street_address',
        'city'    => 'municipality',
        'state'   => 'province',
        'zip'     => 'postal_code',
        'country' => 'country_name',
    ];
}
</code-snippet>
@endverbatim

### Geocoding

- **Single model:** `$model->geocode()` returns a `Coordinate` or `null`.
- **Direct geocoder:** `app(OfflineGeocoder::class)->geocode(['city' => 'Paris', 'country' => 'France'])` returns a `Result` DTO.
- **Batch:** `app(OfflineGeocoder::class)->geocodeBatch($arrayOfInputs)` preserves array keys.

### Backfilling Existing Records

@verbatim
<code-snippet name="Backfill command" lang="bash">
php artisan atlas:backfill --model="App\Models\Address"
php artisan atlas:backfill --model="App\Models\Address" --force --chunk=1000
php artisan atlas:backfill --model="App\Models\Address" --dry-run
</code-snippet>
@endverbatim

### Auto-Geocoding via Listener

Enable automatic geocoding on model create/update in `config/atlas.php`:

@verbatim
<code-snippet name="Listener configuration" lang="php">
'listener' => [
    'enabled' => env('ATLAS_LISTENER_ENABLED', false),
    'models'  => [App\Models\Address::class],
    'on_update' => true,
    'queue' => null,
    'delay' => 2,
    'tries' => 3,
],
</code-snippet>
@endverbatim

### Distance and Radius Queries

@verbatim
<code-snippet name="Distance and radius queries" lang="php">
// Haversine distance in kilometers
$km = $model->distanceTo(48.8566, 2.3522);

// Find models within a radius
Address::withinRadius(40.7128, -74.0060, 50)->get();

// Filter geocoded / not geocoded
Address::geocoded()->get();
Address::notGeocoded()->get();
</code-snippet>
@endverbatim

### Key Conventions

- Always run `php artisan atlas:install` before geocoding — the SQLite database must exist.
- The `atlas_coordinates` table uses a polymorphic relationship — never create it manually; use the package migration.
- Geocoding results include a `method` field indicating which strategy matched (e.g. `us_zip`, `city_exact`, `country_centroid`).
- The `AddressGeocoded` event fires after successful geocoding via listeners.
- The package requires `ext-pdo_sqlite`, `ext-zip`, and `ext-curl`. The `ext-intl` extension is recommended for better Unicode handling.
