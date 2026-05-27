# Backfill Command

The `atlas:backfill` command geocodes existing records in your database in bulk.

## Basic Usage

Set your model class in `config/atlas.php`:

```php
'model' => App\Models\Address::class,
```

Then run:

```bash
php artisan atlas:backfill
```

Atlas will:
1. Query all records where `latitude` or `longitude` is `null`
2. Geocode each record using the column mapping from config
3. Save the results with `saveQuietly()` (won't re-fire the listener)
4. Show a progress bar and method breakdown

## Options

### `--model`

Override the model class from config:

```bash
php artisan atlas:backfill --model=App\\Models\\Location
```

### `--chunk`

Control how many records are loaded per database query (default: 500):

```bash
php artisan atlas:backfill --chunk=1000
```

### `--id`

Geocode a single record by its primary key:

```bash
php artisan atlas:backfill --id=42
```

### `--force`

Re-geocode all records, even those that already have coordinates:

```bash
php artisan atlas:backfill --force
```

### `--dry-run`

Preview what would be geocoded without saving anything:

```bash
php artisan atlas:backfill --dry-run
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

## Column Mapping

The backfill command reads column names from `config('atlas.columns')`. It never assumes your model uses columns named `address`, `city`, etc.

```php
'columns' => [
    'address'   => 'street_address',
    'city'      => 'city_name',
    'state'     => 'region',
    'zip'       => 'postal_code',
    'country'   => 'country_name',
    'latitude'  => 'lat',
    'longitude' => 'lng',
    'deleted_at' => null, // set to null if not soft-deletable
],
```

## Soft Deletes

If the configured `deleted_at` column exists and the model uses `SoftDeletes`, the backfill command will include soft-deleted records.

Set `deleted_at` to `null` in config if your model doesn't use soft deletes:

```php
'columns' => [
    // ...
    'deleted_at' => null,
],
```
