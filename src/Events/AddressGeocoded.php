<?php

namespace ArielMejiaDev\Atlas\Events;

use ArielMejiaDev\Atlas\Support\Result;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddressGeocoded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly mixed $model,
        public readonly Result $result,
    ) {}
}
