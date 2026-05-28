# Backfill Command

The `atlas:backfill` command geocodes existing records in your database in bulk.

## Basic Usage

Specify the model class to backfill:

```bash
php artisan atlas:backfill --model=App\\Models\\Address
```

The model must use the `HasCoordinates` trait. Atlas will:

1. Query all records that don't have a coordinate record yet
2. Geocode each record using the model's `toGeocodableArray()` method
3. Save coordinates to the `atlas_coordinates` table
4. Show a progress bar and method breakdown

## Options

### `--model`

The fully qualified model class to backfill (required):

```bash
php artisan atlas:backfill --model=App\\Models\\Address
```

### `--chunk`

Control how many records are loaded per database query (default: 500):

```bash
php artisan atlas:backfill --model=App\\Models\\Address --chunk=1000
```

### `--id`

Geocode a single record by its primary key:

```bash
php artisan atlas:backfill --model=App\\Models\\Address --id=42
```

### `--force`

Re-geocode all records, even those that already have coordinates:

```bash
php artisan atlas:backfill --model=App\\Models\\Address --force
```

### `--dry-run`

Preview what would be geocoded without saving anything:

```bash
php artisan atlas:backfill --model=App\\Models\\Address --dry-run
```

## Output

The command shows a progress bar and prints a summary:

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

## Multiple Models

Run the command once per model:

```bash
php artisan atlas:backfill --model=App\\Models\\Address
php artisan atlas:backfill --model=App\\Models\\Store
php artisan atlas:backfill --model=App\\Models\\Warehouse
```

Each model uses its own column mapping defined via `geocodableColumns()` or `toGeocodableArray()`.

## Soft Deletes

If the model uses `SoftDeletes`, the backfill command automatically includes soft-deleted records.
