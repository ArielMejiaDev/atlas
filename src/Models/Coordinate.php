<?php

namespace ArielMejiaDev\Atlas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Coordinate extends Model
{
    protected $table = 'atlas_coordinates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geocoded_at' => 'datetime',
        ];
    }

    public function coordinable(): MorphTo
    {
        return $this->morphTo();
    }
}
