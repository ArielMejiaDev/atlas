# Auto-Geocoding

Atlas can automatically geocode records when they're created or updated, using queued jobs.

## Enabling the Listener

List your models and enable the listener in `config/atlas.php`:

```php
'listener' => [
    'enabled' => true,

    'models' => [
        App\Models\Address::class,
        App\Models\Store::class,
    ],

    'on_update' => true,     // re-geocode when address fields change
    'queue'     => 'default', // null for the default queue
    'delay'     => 2,         // seconds before the job runs
    'tries'     => 3,         // retry attempts
],
```

Each model in the `models` array must use the `HasCoordinates` trait.

## How It Works

### On Create

The `GeocodeOnCreated` job:

1. **Freshes** the model to get the latest data
2. **Skips** if coordinates already exist for this model
3. **Skips** if all geocodable fields are empty
4. **Geocodes** using the model's `toGeocodableArray()` method
5. **Creates** a `Coordinate` record via the polymorphic relationship
6. **Dispatches** an `AddressGeocoded` event on success
7. **Logs** a warning if no result was found
8. **Retries** with backoff on exceptions

### On Update

When `on_update` is `true` (the default), Atlas also listens for the `updated` event. The `GeocodeOnUpdated` job:

1. **Checks** if any geocodable columns actually changed (via `addressFieldsChanged()`) — if not, no job is dispatched
2. **Freshes** the model to get the latest data
3. **Skips** if all geocodable fields are now empty
4. **Re-geocodes** using the model's `toGeocodableArray()` method
5. **Updates or creates** the `Coordinate` record (uses `updateOrCreate`)
6. **Dispatches** an `AddressGeocoded` event on success

This ensures coordinates stay in sync when a customer changes their address, a store relocates, or any address field is modified.

### Disabling Update Listening

If you only want auto-geocoding on creation (not on updates), set `on_update` to `false`:

```php
'listener' => [
    'enabled'   => true,
    'on_update' => false,
    // ...
],
```

## Detecting Address Changes

The `addressFieldsChanged()` method on the `HasCoordinates` trait checks if any geocodable columns were modified using Laravel's `wasChanged()`. It runs synchronously in the model event — only when it returns `true` is the queued job dispatched.

For models using `geocodableColumns()`, this works automatically. If you override `toGeocodableArray()` with non-standard columns, override `addressFieldsChanged()` too:

```php
class Contact extends Model
{
    use HasCoordinates;

    public function toGeocodableArray(): array
    {
        return [
            'address' => $this->full_address ?? '',
            'city'    => '',
            'state'   => '',
            'zip'     => '',
            'country' => '',
        ];
    }

    public function addressFieldsChanged(): bool
    {
        return $this->wasChanged('full_address');
    }
}
```

## The AddressGeocoded Event

After a successful geocode (on both create and update), Atlas dispatches `ArielMejiaDev\Atlas\Events\AddressGeocoded`:

```php
use ArielMejiaDev\Atlas\Events\AddressGeocoded;

class GeocodeListener
{
    public function handle(AddressGeocoded $event): void
    {
        // $event->model  — the Eloquent model
        // $event->result — the Result value object

        Log::info('Geocoded', [
            'model' => get_class($event->model),
            'id' => $event->model->getKey(),
            'method' => $event->result->method,
        ]);
    }
}
```

## Alternative: Manual Observer

If you prefer more control, skip the built-in listener and wire your own Observer:

```php
// app/Observers/AddressObserver.php
namespace App\Observers;

use App\Models\Address;

class AddressObserver
{
    public function created(Address $address): void
    {
        $address->geocode();
    }

    public function updated(Address $address): void
    {
        if ($address->addressFieldsChanged()) {
            $address->geocode();
        }
    }
}
```

Register it in your `AppServiceProvider`:

```php
use App\Models\Address;
use App\Observers\AddressObserver;

public function boot(): void
{
    Address::observe(AddressObserver::class);
}
```

::: tip
The `geocode()` method on the model handles everything — geocoding and persisting — in a single call. It uses `updateOrCreate` so it's safe to call on models that already have coordinates.
:::
