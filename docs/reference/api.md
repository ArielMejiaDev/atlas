# API Reference

## `OfflineGeocoder`

The main geocoding service. Framework-agnostic — depends only on PDO and the `Normalizer`.

**Namespace:** `ArielMejiaDev\Atlas\OfflineGeocoder`

### Constructor

```php
public function __construct(
    PDO $pdo,
    Normalizer $normalizer,
    array $config = [],
)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$pdo` | `PDO` | Connection to the geocoding SQLite database |
| `$normalizer` | `Normalizer` | Text normalization instance |
| `$config` | `array` | Config array (needs `us_country_names` key) |

::: tip
In Laravel, you don't construct this manually — inject it or use the facade.
:::

### `geocode()`

```php
public function geocode(array $input): ?Result
```

Geocode an address. Returns a `Result` on success, `null` if no method matched.

**Input array keys:**

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `address` | `string` | No | Street address |
| `city` | `string` | No | City name |
| `state` | `string` | No | State/province |
| `zip` | `string` | No | Postal/ZIP code |
| `country` | `string` | No | Country name or ISO code |

All keys are optional. At least one must be non-empty for the geocoder to attempt a lookup.

### `geocodeBatch()`

```php
public function geocodeBatch(array $inputs): array
```

Geocode multiple inputs in a single call. Returns an array of `Result|null` with the same keys as the input array.

Pre-warms the country aliases cache before processing, making batch operations more efficient.

```php
$results = $geocoder->geocodeBatch([
    'a' => ['zip' => '68815', 'country' => 'US'],
    'b' => ['city' => 'Paris', 'country' => 'France'],
]);
// $results['a'] → Result, $results['b'] → Result
```

---

## `Result`

Readonly value object returned by `geocode()`.

**Namespace:** `ArielMejiaDev\Atlas\Support\Result`

### Properties

```php
public readonly float $latitude;
public readonly float $longitude;
public readonly string $method;
public readonly string $note;
```

| Property | Type | Description |
|----------|------|-------------|
| `$latitude` | `float` | Latitude coordinate |
| `$longitude` | `float` | Longitude coordinate |
| `$method` | `string` | Geocoding method that produced this result |
| `$note` | `string` | Optional context (empty by default) |

### `toArray()`

```php
public function toArray(): array
```

Returns the result as an associative array:

```php
[
    'latitude' => 41.4019,
    'longitude' => -99.6393,
    'method' => 'us_zip',
    'note' => '',
]
```

---

## `Coordinate` Model

Eloquent model for the `atlas_coordinates` polymorphic table.

**Namespace:** `ArielMejiaDev\Atlas\Models\Coordinate`

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$latitude` | `float` | Latitude coordinate |
| `$longitude` | `float` | Longitude coordinate |
| `$method` | `?string` | Geocoding method that produced this result |
| `$geocoded_at` | `?Carbon` | When the geocoding was performed |

### Relationships

```php
public function coordinable(): MorphTo
```

Returns the parent model (the model that was geocoded).

---

## `HasCoordinates` Trait

Trait for Eloquent models that need geocoding.

**Namespace:** `ArielMejiaDev\Atlas\Concerns\HasCoordinates`

### `coordinates()`

```php
public function coordinates(): MorphOne
```

Polymorphic relationship to the `Coordinate` model.

### `geocode()`

```php
public function geocode(): ?Coordinate
```

Geocode this model and persist the result. Returns the `Coordinate` on success, `null` if geocoding failed. Calling it again on an already-geocoded model updates the existing coordinate.

### `toGeocodableArray()`

```php
public function toGeocodableArray(): array
```

Build the geocoder input array. Default implementation reads from `geocodableColumns()`. Override for full control.

### `geocodableColumns()`

```php
public function geocodableColumns(): array
```

Map canonical keys to actual column names. Default:

```php
[
    'address' => 'address',
    'city'    => 'city',
    'state'   => 'state',
    'zip'     => 'zip',
    'country' => 'country',
]
```

Override to map to your model's actual column names. Only include the keys your model has.

### `addressFieldsChanged()`

```php
public function addressFieldsChanged(): bool
```

Returns `true` if any geocodable columns were modified on this model (uses Laravel's `wasChanged()`). Used by the auto-geocoding `updated` listener to decide whether to re-geocode.

Override this method if you use `toGeocodableArray()` with non-standard columns that `geocodableColumns()` doesn't cover.

### `distanceTo()`

```php
public function distanceTo(float $latitude, float $longitude): ?float
```

Calculate the distance in kilometers from this model's coordinates to a given point using the Haversine formula. Returns `null` if the model has no coordinates.

### Query Scopes

```php
public function scopeGeocoded($query)
public function scopeNotGeocoded($query)
public function scopeWithinRadius($query, float $latitude, float $longitude, float $radiusKm)
```

- `geocoded()` — models that have a coordinate record
- `notGeocoded()` — models missing coordinates
- `withinRadius()` — models within a bounding box approximation of the given radius (works on all databases: MySQL, PostgreSQL, SQLite). Use `distanceTo()` for exact post-filtering.

---

## `Normalizer`

Shared text normalization used by both the runtime geocoder and the database builder.

**Namespace:** `ArielMejiaDev\Atlas\Support\Normalizer`

### `normalize()`

```php
public function normalize(string $s): string
```

Normalizes a string for geocoding comparison:

1. NFKD decompose + strip combining marks (uses `intl` if available)
2. Lowercase
3. Replace non-alphanumeric characters with spaces
4. Trim and collapse whitespace

**Examples:**

| Input | Output |
|-------|--------|
| `"São Paulo"` | `"sao paulo"` |
| `"München"` | `"munchen"` |
| `"St. John's"` | `"st john s"` |
| `"  HELLO   WORLD  "` | `"hello world"` |
| `""` | `""` |

---

## `Atlas` Facade

**Namespace:** `ArielMejiaDev\Atlas\Facades\Atlas`

Proxies to `OfflineGeocoder`. Available methods:

```php
Atlas::geocode(array $input): ?Result
Atlas::geocodeBatch(array $inputs): array
```

---

## `Geocodable` Interface

Optional interface for models. Satisfied by the `HasCoordinates` trait.

**Namespace:** `ArielMejiaDev\Atlas\Contracts\Geocodable`

```php
interface Geocodable
{
    public function toGeocodableArray(): array;
}
```

Should return an array with keys: `address`, `city`, `state`, `zip`, `country`.

---

## `GeocoderDriver` Interface

Strategy interface for custom geocoder implementations.

**Namespace:** `ArielMejiaDev\Atlas\Contracts\GeocoderDriver`

```php
interface GeocoderDriver
{
    public function geocode(array $input): ?Result;
}
```

`OfflineGeocoder` implements this interface.

---

## `AddressGeocoded` Event

Dispatched after a successful geocode via the auto-geocoding listener.

**Namespace:** `ArielMejiaDev\Atlas\Events\AddressGeocoded`

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$model` | `mixed` | The Eloquent model that was geocoded |
| `$result` | `Result` | The geocoding result |

---

## `GeocodeOnCreated` Job

Queued job dispatched when a model is created (if listener is enabled).

**Namespace:** `ArielMejiaDev\Atlas\Listeners\GeocodeOnCreated`

Implements `ShouldQueue`. Configuration (queue, delay, tries) comes from `config('atlas.listener')`. Skips if coordinates already exist.

---

## `GeocodeOnUpdated` Job

Queued job dispatched when a model's address fields change (if listener is enabled and `on_update` is `true`).

**Namespace:** `ArielMejiaDev\Atlas\Listeners\GeocodeOnUpdated`

Implements `ShouldQueue`. Configuration (queue, delay, tries) comes from `config('atlas.listener')`. Uses `updateOrCreate` to update existing coordinates or create new ones.

The job is only dispatched when `addressFieldsChanged()` returns `true`, which is checked synchronously in the model event before queuing.

---

## Database Tables

### `atlas_coordinates` (Package Table)

Polymorphic table storing coordinates for all models:

```sql
CREATE TABLE atlas_coordinates (
    id BIGINT UNSIGNED PRIMARY KEY,
    coordinable_type VARCHAR NOT NULL,
    coordinable_id BIGINT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    method VARCHAR NULL,
    geocoded_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE (coordinable_type, coordinable_id)
);
```

### Geocoding SQLite Tables

The geocoding SQLite database contains these lookup tables:

#### `us_zip`

```sql
CREATE TABLE us_zip (
    zip TEXT PRIMARY KEY,
    city TEXT,
    state TEXT,
    lat REAL,
    lng REAL
);
```

#### `us_city_state`

```sql
CREATE TABLE us_city_state (
    city_norm TEXT,
    state TEXT,
    lat REAL,
    lng REAL,
    PRIMARY KEY (city_norm, state)
);
```

#### `cities`

```sql
CREATE TABLE cities (
    name_norm TEXT NOT NULL,
    country_code TEXT NOT NULL,
    lat REAL NOT NULL,
    lng REAL NOT NULL,
    population INTEGER DEFAULT 0,
    PRIMARY KEY (name_norm, country_code)
);
CREATE INDEX idx_cities_cc ON cities(country_code);
```

#### `country_aliases`

```sql
CREATE TABLE country_aliases (
    country_name TEXT PRIMARY KEY,
    iso_code TEXT NOT NULL
);
```

#### `country_centroids`

```sql
CREATE TABLE country_centroids (
    iso_code TEXT PRIMARY KEY,
    name TEXT,
    lat REAL,
    lng REAL
);
```

#### `big_cities_global`

```sql
CREATE TABLE big_cities_global (
    name_norm TEXT PRIMARY KEY,
    country_code TEXT,
    lat REAL,
    lng REAL,
    population INTEGER
);
```
