<?php

namespace ArielMejiaDev\Atlas\Contracts;

interface Geocodable
{
    /**
     * Return the address data as an array with canonical keys:
     * address, city, state, zip, country.
     */
    public function toGeocodableArray(): array;
}
