# Artisan Commands

## `atlas:install`

Build the geocoding database from GeoNames data.

```bash
php artisan atlas:install [--force] [--from=URL]
```

### Options

| Option | Description |
|--------|-------------|
| `--force` | Overwrite the existing database file |
| `--from=URL` | Download a prebuilt SQLite file from a URL instead of building |

### Default Mode

Downloads three files from `download.geonames.org`, parses them with PHP, and writes a SQLite database at the configured `database_path`.

```bash
php artisan atlas:install
```

```
Building Atlas geocoding database from GeoNames...
This downloads ~25 MB and takes 30-90 seconds.

  Downloading cities15000.zip...
  Extracting cities15000.zip...
  Downloading countryInfo.txt...
  Downloading US.zip...
  Extracting US.zip...
  Building SQLite database...
  Importing US ZIP codes...
  Importing US city/state data...
  Importing world cities...
  Importing country data...
  Building country centroids...
  Building big cities index...
  Compacting database...
  Done! Database built successfully.

Database built successfully at: /path/to/database/geocoding.sqlite
File size: 22.4 MB
```

### Prebuilt Mode

Downloads a prebuilt SQLite file from a URL. Validates the file before replacing the existing database.

```bash
php artisan atlas:install --from=https://internal-host.com/geocoding.sqlite
```

The command verifies the downloaded file is a valid SQLite database with data before swapping it into place.

### Rebuild

```bash
php artisan atlas:install --force
```

---

## `atlas:backfill`

Backfill coordinates on existing records using the `atlas_coordinates` table.

```bash
php artisan atlas:backfill --model=CLASS [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--model=CLASS` | — | Fully qualified model class (required, must use `HasCoordinates` trait) |
| `--chunk=N` | `500` | Records per database query |
| `--id=N` | — | Process a single record by ID |
| `--dry-run` | — | Preview without saving |
| `--force` | — | Re-geocode records that already have coordinates |

### Examples

```bash
# Backfill a model
php artisan atlas:backfill --model=App\\Models\\Address

# Multiple models
php artisan atlas:backfill --model=App\\Models\\Address
php artisan atlas:backfill --model=App\\Models\\Store

# Larger chunks for faster processing
php artisan atlas:backfill --model=App\\Models\\Address --chunk=2000

# Single record
php artisan atlas:backfill --model=App\\Models\\Address --id=42

# Preview
php artisan atlas:backfill --model=App\\Models\\Address --dry-run

# Re-geocode everything
php artisan atlas:backfill --model=App\\Models\\Address --force
```

### Output

```
Processing 1250 records...
 1250/1250 [============================] 100%

Geocoded: 1187 | Missed: 63

Method breakdown:
  us_zip: 892
  us_city_state: 198
  city_exact: 54
  country_centroid: 28
  city_partial: 15
```

### Behavior

- Requires `--model` flag — the model must use the `HasCoordinates` trait
- Uses the model's `toGeocodableArray()` method for input
- Writes coordinates to the `atlas_coordinates` polymorphic table
- Skips records that already have a coordinate record (unless `--force`)
- Includes soft-deleted records if the model uses `SoftDeletes`
- Shows a progress bar with method breakdown on completion
