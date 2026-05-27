<?php

use ArielMejiaDev\Atlas\Listeners\GeocodeOnCreated;
use ArielMejiaDev\Atlas\OfflineGeocoder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('testing')->create('listener_addresses', function (Blueprint $table) {
        $table->id();
        $table->string('address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip')->nullable();
        $table->string('country')->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
        $table->timestamps();
    });

    config([
        'atlas.model' => ListenerTestAddress::class,
        'atlas.listener.enabled' => false, // We test dispatching manually
    ]);
});

afterEach(function () {
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

    $model->refresh();
    expect($model->latitude)->not->toBeNull();
    expect($model->longitude)->not->toBeNull();
});

test('geocode job skips when already geocoded', function () {
    $model = ListenerTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
        'latitude' => 99.0,
        'longitude' => 99.0,
    ]);

    $job = new GeocodeOnCreated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->refresh();
    expect((float) $model->latitude)->toBe(99.0);
    expect((float) $model->longitude)->toBe(99.0);
});

test('geocode job skips when address is empty', function () {
    $model = ListenerTestAddress::create([
        'address' => '',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $job = new GeocodeOnCreated($model);
    $job->handle(app(OfflineGeocoder::class));

    $model->refresh();
    expect($model->latitude)->toBeNull();
});

// Test model class
class ListenerTestAddress extends Model
{
    protected $connection = 'testing';

    protected $table = 'listener_addresses';

    protected $guarded = [];
}
