# Getting Started

## Installation

Install Atlas via Composer:

```bash
composer require arielmejiadev/atlas
```

The service provider is auto-discovered — no manual registration needed.

## Run Migrations

Atlas ships with a migration for its `atlas_coordinates` table. Run it alongside your app migrations:

```bash
php artisan migrate
```

This creates the `atlas_coordinates` polymorphic table where coordinates are stored for all your models.

::: tip Customizing the Migration
If you need to modify the migration, publish it first:

```bash
php artisan vendor:publish --tag=atlas-migrations
```
:::

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

This creates `config/atlas.php` where you can customize the database path, connection settings, and auto-geocoding listener. See the [Configuration Reference](/reference/configuration) for all options.

## Add the Trait to Your Model

Add `HasCoordinates` to any model that holds address data:

```php
use ArielMejiaDev\Atlas\Concerns\HasCoordinates;

class Address extends Model
{
    use HasCoordinates;
}
```

If your model uses the standard column names (`address`, `city`, `state`, `zip`, `country`), it works out of the box. For custom column names, see [Usage](/guide/usage).

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

Or geocode directly on a model:

```php
$address = Address::find(1);
$coordinate = $address->geocode();

$coordinate->latitude;  // 41.4019
$coordinate->longitude; // -99.6393
```

If you see coordinates, Atlas is working. Head to [Basic Usage](/guide/basic-usage) for common patterns and real-world examples.
