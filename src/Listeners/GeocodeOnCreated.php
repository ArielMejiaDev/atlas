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

class GeocodeOnCreated implements ShouldQueue
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

        if (! $model) {
            return;
        }

        $columns = config('atlas.columns', []);
        $latCol = $columns['latitude'] ?? 'latitude';
        $lngCol = $columns['longitude'] ?? 'longitude';
        $addressCol = $columns['address'] ?? 'address';

        // Skip if already geocoded
        if ($model->{$latCol} !== null && $model->{$lngCol} !== null) {
            return;
        }

        // Skip if address is empty
        if (empty($model->{$addressCol})) {
            return;
        }

        $input = [];
        foreach (['address', 'city', 'state', 'zip', 'country'] as $key) {
            $col = $columns[$key] ?? $key;
            $input[$key] = (string) ($model->{$col} ?? '');
        }

        try {
            $result = $geocoder->geocode($input);

            if ($result !== null) {
                $model->forceFill([
                    $latCol => $result->latitude,
                    $lngCol => $result->longitude,
                ])->saveQuietly();

                AddressGeocoded::dispatch($model, $result);
            } else {
                Log::warning('Atlas: geocode returned null', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]);
            }
        } catch (\Throwable $e) {
            $retryDelay = ($config['delay'] ?? 2) * 15;
            $this->release($retryDelay);
        }
    }
}
