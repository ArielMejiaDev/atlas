# Auto-Geocoding

Atlas can automatically geocode new records when they're created, using a queued job.

## Enabling the Listener

Set the model and enable the listener in `config/atlas.php`:

```php
'model' => App\Models\Address::class,

'listener' => [
    'enabled' => true,
    'queue'   => 'default',  // null for the default queue
    'delay'   => 2,          // seconds before the job runs
    'tries'   => 3,          // retry attempts
],
```

When enabled, Atlas hooks into `Model::created` on your configured model class and dispatches a `GeocodeOnCreated` queued job.

## How It Works

The `GeocodeOnCreated` job:

1. **Freshes** the model to get the latest data
2. **Skips** if `latitude` and `longitude` are already set
3. **Skips** if the `address` column is empty
4. **Geocodes** using the column mapping from config
5. **Saves** with `forceFill()` + `saveQuietly()` (won't trigger other model events)
6. **Dispatches** an `AddressGeocoded` event on success
7. **Logs** a warning if no result was found
8. **Retries** with exponential backoff on exceptions

## The AddressGeocoded Event

After a successful geocode, Atlas dispatches `ArielMejiaDev\Atlas\Events\AddressGeocoded`:

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
use ArielMejiaDev\Atlas\Facades\Atlas;

class AddressObserver
{
    public function created(Address $address): void
    {
        $result = Atlas::geocode([
            'address' => $address->street,
            'city' => $address->city,
            'state' => $address->state,
            'zip' => $address->postal_code,
            'country' => $address->country,
        ]);

        if ($result) {
            $address->forceFill([
                'latitude' => $result->latitude,
                'longitude' => $result->longitude,
            ])->saveQuietly();
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

## Alternative: Dispatching a Custom Job

For more flexibility (e.g., geocoding on `updated` too):

```php
use ArielMejiaDev\Atlas\Facades\Atlas;

class GeocodeAddress implements ShouldQueue
{
    public function __construct(public Address $address) {}

    public function handle(): void
    {
        $address = $this->address->fresh();

        if ($address->latitude !== null) {
            return;
        }

        $result = Atlas::geocode([
            'address' => $address->street,
            'city' => $address->city,
            'state' => $address->state,
            'zip' => $address->postal_code,
            'country' => $address->country,
        ]);

        if ($result) {
            $address->forceFill([
                'latitude' => $result->latitude,
                'longitude' => $result->longitude,
            ])->saveQuietly();
        }
    }
}
```

::: tip
Always use `saveQuietly()` when saving from a listener or job to prevent infinite loops.
:::
