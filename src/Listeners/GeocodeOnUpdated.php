<?php

namespace ArielMejiaDev\Atlas\Listeners;

use ArielMejiaDev\Atlas\Events\AddressGeocoded;
use ArielMejiaDev\Atlas\OfflineGeocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeocodeOnUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Model $model,
    ) {
        $config = config('atlas.listener', []);

        $this->tries = $config['tries'] ?? 3;
        $this->onQueue($config['queue'] ?? null);
        $this->delay($config['delay'] ?? 2);
    }

    public function handle(OfflineGeocoder $geocoder): void
    {
        $model = $this->model->fresh();

        if (! $model || ! method_exists($model, 'toGeocodableArray')) {
            return;
        }

        $input = $model->toGeocodableArray();

        // Skip if all input values are empty
        if (empty(array_filter($input, fn ($v) => $v !== ''))) {
            return;
        }

        try {
            $result = $geocoder->geocode($input);

            if ($result !== null) {
                $model->coordinates()->updateOrCreate([], [
                    'latitude' => $result->latitude,
                    'longitude' => $result->longitude,
                    'method' => $result->method,
                    'geocoded_at' => now(),
                ]);

                AddressGeocoded::dispatch($model, $result);
            } else {
                Log::warning('Atlas: re-geocode on update returned null', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->release(config('atlas.listener.delay', 2) * 15);
        }
    }
}
