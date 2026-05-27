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

Backfill latitude/longitude on existing records.

```bash
php artisan atlas:backfill [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--model=CLASS` | Config value | Fully qualified model class |
| `--chunk=N` | `500` | Records per database query |
| `--id=N` | — | Process a single record by ID |
| `--dry-run` | — | Preview without saving |
| `--force` | — | Re-geocode records that already have coordinates |

### Examples

```bash
# Basic backfill using config model
php artisan atlas:backfill

# Specify model
php artisan atlas:backfill --model=App\\Models\\Address

# Larger chunks for faster processing
php artisan atlas:backfill --chunk=2000

# Single record
php artisan atlas:backfill --id=42

# Preview
php artisan atlas:backfill --dry-run

# Re-geocode everything
php artisan atlas:backfill --force
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

- Reads model class from `--model` or `config('atlas.model')`
- Uses the column mapping from config
- Skips records that already have `latitude` and `longitude` (unless `--force`)
- Includes soft-deleted records if the model uses `SoftDeletes`
- Uses `saveQuietly()` to avoid re-triggering the auto-geocoding listener
- Shows a progress bar with method breakdown on completion
