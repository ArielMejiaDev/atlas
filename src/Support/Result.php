<?php

namespace ArielMejiaDev\Atlas\Support;

final class Result
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $method,
        public readonly string $note = '',
    ) {}

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'method' => $this->method,
            'note' => $this->note,
        ];
    }
}
