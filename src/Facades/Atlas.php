<?php

namespace ArielMejiaDev\Atlas\Facades;

use ArielMejiaDev\Atlas\OfflineGeocoder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \ArielMejiaDev\Atlas\Support\Result|null geocode(array $input)
 * @method static array<int|string, \ArielMejiaDev\Atlas\Support\Result|null> geocodeBatch(array $inputs)
 *
 * @see OfflineGeocoder
 */
class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OfflineGeocoder::class;
    }
}
