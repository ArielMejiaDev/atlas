<?php

namespace ArielMejiaDev\Atlas\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ArielMejiaDev\Atlas\Support\Result|null geocode(array $input)
 *
 * @see \ArielMejiaDev\Atlas\OfflineGeocoder
 */
class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ArielMejiaDev\Atlas\OfflineGeocoder::class;
    }
}
