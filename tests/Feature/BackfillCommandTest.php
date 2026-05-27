<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('testing')->create('addresses', function (Blueprint $table) {
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

    config(['atlas.model' => TestAddress::class]);
});

afterEach(function () {
    Schema::connection('testing')->dropIfExists('addresses');
});

test('backfill command geocodes records', function () {
    TestAddress::create([
        'address' => '123 Main St',
        'city' => 'Broken Bow',
        'state' => 'NE',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $this->artisan('atlas:backfill')
        ->assertExitCode(0);

    $address = TestAddress::first();
    expect($address->latitude)->not->toBeNull();
    expect($address->longitude)->not->toBeNull();
});

test('backfill skips already geocoded records', function () {
    TestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
        'latitude' => 1.0,
        'longitude' => 2.0,
    ]);

    $this->artisan('atlas:backfill')
        ->assertExitCode(0);

    $address = TestAddress::first();
    expect((float) $address->latitude)->toBe(1.0);
    expect((float) $address->longitude)->toBe(2.0);
});

test('backfill with force re-geocodes', function () {
    TestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
        'latitude' => 1.0,
        'longitude' => 2.0,
    ]);

    $this->artisan('atlas:backfill', ['--force' => true])
        ->assertExitCode(0);

    $address = TestAddress::first();
    expect((float) $address->latitude)->not->toBe(1.0);
});

test('backfill dry run does not save', function () {
    TestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $this->artisan('atlas:backfill', ['--dry-run' => true])
        ->assertExitCode(0);

    $address = TestAddress::first();
    expect($address->latitude)->toBeNull();
});

test('backfill fails without model', function () {
    config(['atlas.model' => null]);

    $this->artisan('atlas:backfill')
        ->assertExitCode(1);
});

test('backfill accepts model option', function () {
    config(['atlas.model' => null]);

    TestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $this->artisan('atlas:backfill', ['--model' => TestAddress::class])
        ->assertExitCode(0);

    $address = TestAddress::first();
    expect($address->latitude)->not->toBeNull();
});

// Test model class
class TestAddress extends Model
{
    protected $connection = 'testing';

    protected $table = 'addresses';

    protected $guarded = [];
}
