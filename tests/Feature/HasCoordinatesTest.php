<?php

use ArielMejiaDev\Atlas\Concerns\HasCoordinates;
use ArielMejiaDev\Atlas\Models\Coordinate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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

    // Standard columns
    Schema::connection('testing')->create('standard_addresses', function (Blueprint $table) {
        $table->id();
        $table->string('address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip')->nullable();
        $table->string('country')->nullable();
        $table->timestamps();
    });

    // Custom column names
    Schema::connection('testing')->create('stores', function (Blueprint $table) {
        $table->id();
        $table->string('store_address')->nullable();
        $table->string('store_city')->nullable();
        $table->string('province')->nullable();
        $table->string('postal_code')->nullable();
        $table->string('country_name')->nullable();
        $table->timestamps();
    });

    // Single address blob
    Schema::connection('testing')->create('contacts', function (Blueprint $table) {
        $table->id();
        $table->text('full_address')->nullable();
        $table->timestamps();
    });

    // Partial data (city + country only)
    Schema::connection('testing')->create('venues', function (Blueprint $table) {
        $table->id();
        $table->string('venue_city')->nullable();
        $table->string('venue_country')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::connection('testing')->dropIfExists('atlas_coordinates');
    Schema::connection('testing')->dropIfExists('standard_addresses');
    Schema::connection('testing')->dropIfExists('stores');
    Schema::connection('testing')->dropIfExists('contacts');
    Schema::connection('testing')->dropIfExists('venues');
});

test('standard columns work out of the box', function () {
    $model = StandardAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $coord = $model->geocode();

    expect($coord)->not->toBeNull();
    expect($coord)->toBeInstanceOf(Coordinate::class);
    expect($coord->latitude)->toBeFloat();
    expect($coord->longitude)->toBeFloat();
    expect($coord->method)->toBe('us_zip');
});

test('custom column names via geocodableColumns', function () {
    $model = StoreModel::create([
        'store_address' => '123 Main St',
        'store_city' => 'Broken Bow',
        'province' => 'NE',
        'postal_code' => '68815',
        'country_name' => 'US',
    ]);

    $coord = $model->geocode();

    expect($coord)->not->toBeNull();
    expect($coord->latitude)->toBeFloat();
    expect($coord->longitude)->toBeFloat();
});

test('single address blob via toGeocodableArray override', function () {
    $model = ContactModel::create([
        'full_address' => '123 Main Street Mumbai India',
    ]);

    $coord = $model->geocode();

    expect($coord)->not->toBeNull();
    expect($coord->latitude)->toBeFloat();
    expect($coord->longitude)->toBeFloat();
});

test('partial data with only city and country', function () {
    $model = VenueModel::create([
        'venue_city' => 'Paris',
        'venue_country' => 'France',
    ]);

    $coord = $model->geocode();

    expect($coord)->not->toBeNull();
    expect($coord->latitude)->toBeFloat();
    expect($coord->method)->toBe('city_exact');
});

test('geocode returns null when all fields are empty', function () {
    $model = StandardAddress::create([
        'address' => '',
        'city' => '',
    ]);

    expect($model->geocode())->toBeNull();
});

test('geocode does not duplicate coordinates on second call', function () {
    $model = StandardAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $model->geocode();
    $model->geocode();

    expect(Coordinate::where('coordinable_type', StandardAddress::class)->count())->toBe(1);
});

test('scopeGeocoded filters correctly', function () {
    $geocoded = StandardAddress::create(['address' => '123 Main', 'zip' => '68815', 'country' => 'US']);
    $geocoded->geocode();

    StandardAddress::create(['address' => 'no coordinates']);

    expect(StandardAddress::geocoded()->count())->toBe(1);
    expect(StandardAddress::notGeocoded()->count())->toBe(1);
});

test('coordinates relationship is morphOne', function () {
    $model = StandardAddress::create(['address' => '123 Main', 'zip' => '68815', 'country' => 'US']);
    $model->geocode();

    $coord = $model->coordinates;
    expect($coord)->toBeInstanceOf(Coordinate::class);
    expect($coord->coordinable)->toBeInstanceOf(StandardAddress::class);
    expect($coord->coordinable->id)->toBe($model->id);
});

test('multiple models share the coordinates table', function () {
    $address = StandardAddress::create(['address' => '123 Main', 'zip' => '68815', 'country' => 'US']);
    $store = StoreModel::create([
        'store_address' => '123 Main',
        'postal_code' => '68815',
        'country_name' => 'US',
    ]);

    $address->geocode();
    $store->geocode();

    expect(Coordinate::count())->toBe(2);
    expect($address->coordinates->latitude)->toBeFloat();
    expect($store->coordinates->latitude)->toBeFloat();
});

test('addressFieldsChanged returns true when geocodable column changes', function () {
    $model = StandardAddress::create([
        'address' => '123 Main St',
        'city' => 'Broken Bow',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $model->update(['city' => 'Lincoln']);

    expect($model->addressFieldsChanged())->toBeTrue();
});

test('addressFieldsChanged returns false when non-geocodable column changes', function () {
    $model = StandardAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    // updated_at changes but no geocodable columns
    $model->update([]);

    expect($model->addressFieldsChanged())->toBeFalse();
});

test('addressFieldsChanged works with custom column names', function () {
    $model = StoreModel::create([
        'store_address' => '123 Main St',
        'store_city' => 'Broken Bow',
        'postal_code' => '68815',
        'country_name' => 'US',
    ]);

    $model->update(['store_city' => 'Lincoln']);

    expect($model->addressFieldsChanged())->toBeTrue();
});

test('geocode updates coordinates when address changes', function () {
    $model = StandardAddress::create([
        'address' => '123 Main St',
        'city' => 'Broken Bow',
        'state' => 'NE',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $coord1 = $model->geocode();
    $lat1 = $coord1->latitude;

    $model->update(['city' => 'Paris', 'state' => '', 'zip' => '', 'country' => 'France']);
    $coord2 = $model->geocode();

    expect($coord2->latitude)->not->toBe($lat1);
    expect(Coordinate::where('coordinable_type', StandardAddress::class)->count())->toBe(1);
});

test('distanceTo returns distance in kilometers', function () {
    $model = StandardAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $model->geocode();
    $model->load('coordinates');

    $distance = $model->distanceTo(
        $model->coordinates->latitude,
        $model->coordinates->longitude,
    );

    expect($distance)->toBe(0.0);
});

test('distanceTo returns null without coordinates', function () {
    $model = StandardAddress::create(['address' => 'no coords']);

    expect($model->distanceTo(40.0, -99.0))->toBeNull();
});

test('distanceTo returns positive distance for different points', function () {
    $model = StandardAddress::create([
        'address' => '123 Main St',
        'zip' => '68815',
        'country' => 'US',
    ]);

    $model->geocode();
    $model->load('coordinates');

    // Distance to a different point should be > 0
    $distance = $model->distanceTo(0.0, 0.0);

    expect($distance)->toBeGreaterThan(0.0);
});

test('scopeWithinRadius filters models by bounding box', function () {
    $nearby = StandardAddress::create(['address' => '123 Main', 'zip' => '68815', 'country' => 'US']);
    $nearby->geocode();
    $nearby->load('coordinates');

    $far = StandardAddress::create(['city' => 'Paris', 'country' => 'France']);
    $far->geocode();

    $results = StandardAddress::withinRadius(
        $nearby->coordinates->latitude,
        $nearby->coordinates->longitude,
        50,
    )->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($nearby->id);
});

// --- Test model classes ---

class StandardAddress extends Model
{
    use HasCoordinates;

    protected $connection = 'testing';

    protected $table = 'standard_addresses';

    protected $guarded = [];
}

class StoreModel extends Model
{
    use HasCoordinates;

    protected $connection = 'testing';

    protected $table = 'stores';

    protected $guarded = [];

    public function geocodableColumns(): array
    {
        return [
            'address' => 'store_address',
            'city' => 'store_city',
            'state' => 'province',
            'zip' => 'postal_code',
            'country' => 'country_name',
        ];
    }
}

class ContactModel extends Model
{
    use HasCoordinates;

    protected $connection = 'testing';

    protected $table = 'contacts';

    protected $guarded = [];

    public function toGeocodableArray(): array
    {
        return [
            'address' => $this->full_address ?? '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
        ];
    }
}

class VenueModel extends Model
{
    use HasCoordinates;

    protected $connection = 'testing';

    protected $table = 'venues';

    protected $guarded = [];

    public function geocodableColumns(): array
    {
        return [
            'city' => 'venue_city',
            'country' => 'venue_country',
        ];
    }
}
