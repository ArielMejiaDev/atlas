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
| `address` | `string` | Yes | Street address — returns `null` if empty |
| `city` | `string` | No | City name |
| `state` | `string` | No | State/province |
| `zip` | `string` | No | Postal/ZIP code |
| `country` | `string` | No | Country name or ISO code |

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
```

---

## `Geocodable` Interface

Optional interface for host models.

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

Implements `ShouldQueue`. Configuration (queue, delay, tries) comes from `config('atlas.listener')`.

---

## Database Schema

The geocoding SQLite database contains these tables:

### `us_zip`

```sql
CREATE TABLE us_zip (
    zip TEXT PRIMARY KEY,
    city TEXT,
    state TEXT,
    lat REAL,
    lng REAL
);
```

### `us_city_state`

```sql
CREATE TABLE us_city_state (
    city_norm TEXT,
    state TEXT,
    lat REAL,
    lng REAL,
    PRIMARY KEY (city_norm, state)
);
```

### `cities`

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

### `country_aliases`

```sql
CREATE TABLE country_aliases (
    country_name TEXT PRIMARY KEY,
    iso_code TEXT NOT NULL
);
```

### `country_centroids`

```sql
CREATE TABLE country_centroids (
    iso_code TEXT PRIMARY KEY,
    name TEXT,
    lat REAL,
    lng REAL
);
```

### `big_cities_global`

```sql
CREATE TABLE big_cities_global (
    name_norm TEXT PRIMARY KEY,
    country_code TEXT,
    lat REAL,
    lng REAL,
    population INTEGER
);
```
