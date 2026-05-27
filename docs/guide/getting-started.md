# Getting Started

## Installation

Install Atlas via Composer:

```bash
composer require arielmejiadev/atlas
```

The service provider is auto-discovered — no manual registration needed.

## Build the Database

Atlas ships without the ~22 MB geocoding database to keep the Composer package small. Build it with:

```bash
php artisan atlas:install
```

This downloads ~25 MB of public data from [GeoNames](https://www.geonames.org/) and compiles a SQLite file at `database/geocoding.sqlite`. Takes 30–90 seconds depending on your connection.

::: tip Air-Gapped Environments
If your server can't reach `download.geonames.org`, you can download a prebuilt database from a custom URL:

```bash
php artisan atlas:install --from=https://your-internal-host.com/geocoding.sqlite
```
:::

::: warning
The `atlas:install` command will refuse to overwrite an existing database. Use `--force` to rebuild:

```bash
php artisan atlas:install --force
```
:::

## Publish the Config

```bash
php artisan vendor:publish --tag=atlas-config
```

This creates `config/atlas.php` where you can customize:

- Database path and connection name
- Model class for backfilling
- Column name mappings
- Auto-geocoding listener settings

See the [Configuration Reference](/reference/configuration) for all options.

## Quick Test

Open Tinker and geocode an address:

```bash
php artisan tinker
```

```php
use ArielMejiaDev\Atlas\Facades\Atlas;

$result = Atlas::geocode([
    'address' => '123 Main St',
    'zip' => '68815',
    'country' => 'US',
]);

$result->latitude;  // 41.4019
$result->longitude; // -99.6393
$result->method;    // 'us_zip'
```

If you see coordinates, Atlas is working. Head to [Usage](/guide/usage) to learn more.
