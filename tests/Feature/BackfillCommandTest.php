<?php

use ArielMejiaDev\Atlas\Concerns\HasCoordinates;
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
});

afterEach(function () {
    Schema::connection('testing')->dropIfExists('atlas_coordinates');
    Schema::connection('testing')->dropIfExists('addresses');
});

test('backfill command geocodes records', function () {
    BackfillTestAddress::create([
        'address' => '123 Main St',
        'city' => 'Broken Bow',
        'state' => 'NE',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $this->artisan('atlas:backfill', ['--model' => BackfillTestAddress::class])
        ->assertExitCode(0);

    $address = BackfillTestAddress::with('coordinates')->first();
    expect($address->coordinates)->not->toBeNull();
    expect($address->coordinates->latitude)->not->toBeNull();
    expect($address->coordinates->longitude)->not->toBeNull();
});

test('backfill skips already geocoded records', function () {
    $address = BackfillTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $address->coordinates()->create([
        'latitude' => 1.0,
        'longitude' => 2.0,
        'method' => 'manual',
        'geocoded_at' => now(),
    ]);

    $this->artisan('atlas:backfill', ['--model' => BackfillTestAddress::class])
        ->assertExitCode(0);

    $address->load('coordinates');
    expect($address->coordinates->latitude)->toBe(1.0);
    expect($address->coordinates->longitude)->toBe(2.0);
});

test('backfill with force re-geocodes', function () {
    $address = BackfillTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $address->coordinates()->create([
        'latitude' => 1.0,
        'longitude' => 2.0,
        'method' => 'manual',
        'geocoded_at' => now(),
    ]);

    $this->artisan('atlas:backfill', ['--model' => BackfillTestAddress::class, '--force' => true])
        ->assertExitCode(0);

    $address->load('coordinates');
    expect($address->coordinates->latitude)->not->toBe(1.0);
});

test('backfill dry run does not save', function () {
    BackfillTestAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $this->artisan('atlas:backfill', ['--model' => BackfillTestAddress::class, '--dry-run' => true])
        ->assertExitCode(0);

    $address = BackfillTestAddress::with('coordinates')->first();
    expect($address->coordinates)->toBeNull();
});

test('backfill fails without model', function () {
    $this->artisan('atlas:backfill')
        ->assertExitCode(1);
});

test('backfill fails if model lacks HasCoordinates trait', function () {
    $this->artisan('atlas:backfill', ['--model' => BackfillTestPlainModel::class])
        ->assertExitCode(1);
});

// Test model with HasCoordinates
class BackfillTestAddress extends Model
{
    use HasCoordinates;

    protected $connection = 'testing';

    protected $table = 'addresses';

    protected $guarded = [];
}

// Test model without trait
class BackfillTestPlainModel extends Model
{
    protected $connection = 'testing';

    protected $table = 'addresses';

    protected $guarded = [];
}
