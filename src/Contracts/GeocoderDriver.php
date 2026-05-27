<?php

namespace ArielMejiaDev\Atlas\Contracts;

use ArielMejiaDev\Atlas\Support\Result;

interface GeocoderDriver
{
    public function geocode(array $input): ?Result;
}
