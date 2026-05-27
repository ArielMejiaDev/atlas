<?php

use ArielMejiaDev\Atlas\Support\Normalizer;

beforeEach(function () {
    $this->normalizer = new Normalizer;
});

test('normalizes basic lowercase and whitespace', function () {
    expect($this->normalizer->normalize('  HELLO   WORLD  '))->toBe('hello world');
});

test('normalizes diacritics in São Paulo', function () {
    expect($this->normalizer->normalize('São Paulo'))->toBe('sao paulo');
});

test('normalizes apostrophes', function () {
    expect($this->normalizer->normalize("St. John's"))->toBe('st john s');
});

test('normalizes German umlaut', function () {
    expect($this->normalizer->normalize('München'))->toBe('munchen');
});

test('normalizes empty string', function () {
    expect($this->normalizer->normalize(''))->toBe('');
});

test('normalizes mixed punctuation', function () {
    expect($this->normalizer->normalize('New-York, USA!'))->toBe('new york usa');
});

test('normalizes unicode whitespace', function () {
    // Non-breaking space
    expect($this->normalizer->normalize("hello\xC2\xA0world"))->toBe('hello world');
});

test('normalizes accented characters', function () {
    expect($this->normalizer->normalize('café résumé'))->toBe('cafe resume');
});

test('normalizes numbers pass through', function () {
    expect($this->normalizer->normalize('Route 66'))->toBe('route 66');
});

test('normalizes multiple spaces collapse', function () {
    expect($this->normalizer->normalize('a    b     c'))->toBe('a b c');
});

test('normalizes Scandinavian characters', function () {
    expect($this->normalizer->normalize('Malmö'))->toBe('malmo');
});

test('normalizes Polish characters', function () {
    expect($this->normalizer->normalize('Łódź'))->toBe('lodz');
});
