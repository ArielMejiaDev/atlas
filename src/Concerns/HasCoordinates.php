<?php

namespace ArielMejiaDev\Atlas\Concerns;

use ArielMejiaDev\Atlas\Models\Coordinate;
use ArielMejiaDev\Atlas\OfflineGeocoder;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasCoordinates
{
    public function coordinates(): MorphOne
    {
        return $this->morphOne(Coordinate::class, 'coordinable');
    }

    /**
     * Build the geocoder input array using canonical keys:
     * address, city, state, zip, country.
     *
     * Override this method for full control over the input
     * (e.g. single-column addresses, computed values, accessors).
     */
    public function toGeocodableArray(): array
    {
        $columns = $this->geocodableColumns();
        $input = [];

        foreach (['address', 'city', 'state', 'zip', 'country'] as $key) {
            $input[$key] = isset($columns[$key])
                ? (string) ($this->{$columns[$key]} ?? '')
                : '';
        }

        return $input;
    }

    /**
     * Map canonical geocoder keys to actual model column names.
     *
     * Override this method when your columns have different names.
     * Only include the keys your model actually has.
     */
    public function geocodableColumns(): array
    {
        return [
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'zip',
            'country' => 'country',
        ];
    }

    /**
     * Geocode this model and persist the result to the coordinates table.
     */
    public function geocode(): ?Coordinate
    {
        $input = $this->toGeocodableArray();

        if (empty(array_filter($input, fn ($v) => $v !== ''))) {
            return null;
        }

        $result = app(OfflineGeocoder::class)->geocode($input);

        if ($result === null) {
            return null;
        }

        return $this->coordinates()->updateOrCreate([], [
            'latitude' => $result->latitude,
            'longitude' => $result->longitude,
            'method' => $result->method,
            'geocoded_at' => now(),
        ]);
    }

    /**
     * Check if any geocodable address fields were changed on this model.
     *
     * Override this method if you use toGeocodableArray() with
     * non-standard columns that geocodableColumns() doesn't cover.
     */
    public function addressFieldsChanged(): bool
    {
        return $this->wasChanged(array_values($this->geocodableColumns()));
    }

    /**
     * Calculate the distance in kilometers from this model's coordinates
     * to a given point using the Haversine formula.
     */
    public function distanceTo(float $latitude, float $longitude): ?float
    {
        $coords = $this->coordinates;

        if (! $coords) {
            return null;
        }

        $earthRadius = 6371.0;

        $latFrom = deg2rad($coords->latitude);
        $lonFrom = deg2rad($coords->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function scopeGeocoded($query)
    {
        return $query->whereHas('coordinates');
    }

    public function scopeNotGeocoded($query)
    {
        return $query->whereDoesntHave('coordinates');
    }

    /**
     * Filter models within a bounding box approximation of the given radius.
     *
     * Uses latitude/longitude range filtering which works on all databases
     * (MySQL, PostgreSQL, SQLite). The bounding box is rectangular, so
     * corner results may be slightly outside the true circular radius.
     * Use distanceTo() for exact post-filtering when needed.
     */
    public function scopeWithinRadius($query, float $latitude, float $longitude, float $radiusKm)
    {
        $latDelta = $radiusKm / 111.32;
        $lngDelta = $radiusKm / (111.32 * cos(deg2rad($latitude)));

        $minLat = $latitude - $latDelta;
        $maxLat = $latitude + $latDelta;
        $minLng = $longitude - $lngDelta;
        $maxLng = $longitude + $lngDelta;

        return $query->whereHas('coordinates', function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
            $q->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng]);
        });
    }
}
