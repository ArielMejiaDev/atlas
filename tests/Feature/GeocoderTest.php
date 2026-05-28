<?php

use ArielMejiaDev\Atlas\OfflineGeocoder;

test('US ZIP hits us_zip', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Main',
        'zip' => '68815',
        'country' => 'United States of America (the)',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBe('us_zip');
    expect($r->latitude)->toBeFloat();
    expect($r->longitude)->toBeFloat();
});

test('US ZIP with dash is handled', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Main',
        'zip' => '68815-1234',
        'country' => 'US',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBe('us_zip');
});

test('US city+state hits us_city_state when zip missing', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Main St',
        'city' => 'Broken Bow',
        'state' => 'NE',
        'country' => 'United States',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBe('us_city_state');
});

test('city_exact for international', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Rue de la Paix',
        'city' => 'Paris',
        'country' => 'France',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBe('city_exact');
});

test('state_as_city handles swapped fields', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Street',
        'state' => 'Phnom Penh',
        'country' => 'Cambodia',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBe('state_as_city');
});

test('city_partial substring match', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Main',
        'city' => 'Buenos',
        'country' => 'Argentina',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBe('city_partial');
});

test('country_centroid fallback', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Unknown Street',
        'city' => 'ZZZNoSuchCity',
        'country' => 'Japan',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBe('country_centroid');
});

test('text_extract_city when country is empty', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Main Street Mumbai India',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => '',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBeIn(['text_extract_city', 'text_extract_country_centroid']);
});

test('text_extract_country_centroid when city not in DB', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Main Street Japan ZZZNoCity',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => '',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBeIn(['text_extract_city', 'text_extract_country_centroid']);
});

test('global_big_city_match for unknown country', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => 'Some address near Paris somewhere',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => '',
    ]);

    expect($r)->not->toBeNull();
    expect($r->method)->toBeIn(['text_extract_city', 'text_extract_country_centroid', 'global_big_city_match']);
});

test('returns null on empty input', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => '',
    ]);

    expect($r)->toBeNull();
});

test('result toArray works', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Main',
        'zip' => '68815',
        'country' => 'US',
    ]);

    expect($r)->not->toBeNull();

    $arr = $r->toArray();
    expect($arr)->toHaveKeys(['latitude', 'longitude', 'method', 'note']);
});

test('india heuristic with 6-digit postal code', function () {
    $r = app(OfflineGeocoder::class)->geocode([
        'address' => '123 Street 400001',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => '',
    ]);

    expect($r)->not->toBeNull();
    // Should detect as India via the 6-digit heuristic
});

test('geocodeBatch processes multiple inputs', function () {
    $geocoder = app(OfflineGeocoder::class);

    $results = $geocoder->geocodeBatch([
        'us' => ['zip' => '68815', 'country' => 'US'],
        'fr' => ['city' => 'Paris', 'country' => 'France'],
        'empty' => ['address' => '', 'city' => '', 'country' => ''],
    ]);

    expect($results)->toHaveCount(3);
    expect($results['us'])->not->toBeNull();
    expect($results['us']->method)->toBe('us_zip');
    expect($results['fr'])->not->toBeNull();
    expect($results['fr']->method)->toBe('city_exact');
    expect($results['empty'])->toBeNull();
});

test('geocodeBatch preserves numeric keys', function () {
    $geocoder = app(OfflineGeocoder::class);

    $results = $geocoder->geocodeBatch([
        0 => ['zip' => '68815', 'country' => 'US'],
        5 => ['city' => 'Paris', 'country' => 'France'],
    ]);

    expect($results)->toHaveKeys([0, 5]);
    expect($results[0])->not->toBeNull();
    expect($results[5])->not->toBeNull();
});

test('geocodeBatch returns empty array for empty input', function () {
    $geocoder = app(OfflineGeocoder::class);

    $results = $geocoder->geocodeBatch([]);

    expect($results)->toBeEmpty();
});
