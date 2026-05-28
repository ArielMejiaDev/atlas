<?php

use ArielMejiaDev\Atlas\Concerns\HasCoordinates;
use ArielMejiaDev\Atlas\Listeners\GeocodeOnCreated;
use ArielMejiaDev\Atlas\Listeners\GeocodeOnUpdated;
use ArielMejiaDev\Atlas\OfflineGeocoder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('testing')->create('listener_addresses', function (Blueprint $table) {
        $table->id();
        $table->string('address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip')->nullable();
        $table->string('country')->nullable();
        $table->timestamps();
    });

    Schema::connection('testing')->create('atlas_coordinates', function (Blueprint $table) {
        $table->id();
        $table->morphs('coordinable');
        $table->decimal('latitude', 10, 7);
        $table->decimal('longitude', 10, 7);
        $table->string('method')->nullable();
        $table->timestamp('geocoded_at')->nullable();
        $table->timestamps();
        $table->unique(['coordinable_type', 'coordinable_id']);
    });

    config([
        'atlas.listener.enabled' => false, // We test dispatching manually
    ]);
});

afterEach(function () {
    Schema::connection('testing')->dropIfExists('atlas_coordinates');
    Schema::connection('testing')->dropIfExists('listener_addresses');
});

test('geocode job geocodes model on handle', function () {
    $model = ListenerTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $job = new GeocodeOnCreated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->load('coordinates');
    expect($model->coordinates)->not->toBeNull();
    expect($model->coordinates->latitude)->not->toBeNull();
    expect($model->coordinates->longitude)->not->toBeNull();
    expect($model->coordinates->method)->not->toBeNull();
    expect($model->coordinates->geocoded_at)->not->toBeNull();
});

test('geocode job skips when already geocoded', function () {
    $model = ListenerTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $model->coordinates()->create([
        'latitude' => 99.0,
        'longitude' => 99.0,
        'method' => 'manual',
        'geocoded_at' => now(),
    ]);

    $job = new GeocodeOnCreated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->load('coordinates');
    expect($model->coordinates->latitude)->toBe(99.0);
    expect($model->coordinates->longitude)->toBe(99.0);
});

test('geocode job skips when all input is empty', function () {
    $model = ListenerTestAddress::create([
        'address' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => '',
    ]);

    $job = new GeocodeOnCreated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->load('coordinates');
    expect($model->coordinates)->toBeNull();
});

test('update job re-geocodes model with new address', function () {
    $model = ListenerTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    // First geocode
    $model->coordinates()->create([
        'latitude' => 41.4,
        'longitude' => -99.6,
        'method' => 'us_zip',
        'geocoded_at' => now(),
    ]);

    // Simulate address change
    $model->update(['city' => 'Paris', 'state' => '', 'zip' => '', 'country' => 'France']);

    $job = new GeocodeOnUpdated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->load('coordinates');
    expect($model->coordinates->latitude)->not->toBe(41.4);
    expect($model->coordinates->method)->toBe('city_exact');
});

test('update job creates coordinates when model had none', function () {
    $model = ListenerTestAddress::create([
        'address' => '',
        'city' => '',
        'zip' => '',
        'country' => '',
    ]);

    // Update with valid address
    $model->update(['zip' => '68815', 'country' => 'US']);

    $job = new GeocodeOnUpdated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->load('coordinates');
    expect($model->coordinates)->not->toBeNull();
    expect($model->coordinates->method)->toBe('us_zip');
});

test('update job skips when all fields are empty', function () {
    $model = ListenerTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $model->update([
        'address' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => '',
    ]);

    $job = new GeocodeOnUpdated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->load('coordinates');
    expect($model->coordinates)->toBeNull();
});

// Test model class
class ListenerTestAddress extends Model
{
    use HasCoordinates;

    protected $connection = 'testing';

    protected $table = 'listener_addresses';

    protected $guarded = [];
}
