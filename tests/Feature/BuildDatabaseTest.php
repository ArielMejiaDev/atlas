<?php

use ArielMejiaDev\Atlas\Console\BuildDatabase;
use ArielMejiaDev\Atlas\Support\Normalizer;

test('builds database from fixture files', function () {
    $outputPath = sys_get_temp_dir().'/atlas_test_'.uniqid().'.sqlite';

    $builder = new BuildDatabase(new Normalizer);
    $builder->buildFromData(
        $outputPath,
        __DIR__.'/../fixtures/geonames/US.txt',
        __DIR__.'/../fixtures/geonames/cities15000.txt',
        __DIR__.'/../fixtures/geonames/countryInfo.txt',
    );

    expect(file_exists($outputPath))->toBeTrue();

    $pdo = new PDO('sqlite:'.$outputPath);

    // Check tables exist and have rows
    $tables = [
        'us_zip' => 5,
        'us_city_state' => 5,
        'cities' => 1,  // at least 1
        'country_aliases' => 1,
        'country_centroids' => 1,
        'big_cities_global' => 1,
    ];

    foreach ($tables as $table => $minRows) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        expect($count)->toBeGreaterThanOrEqual($minRows, "Table $table should have at least $minRows rows");
    }

    // Verify specific lookups
    $stmt = $pdo->prepare('SELECT lat, lng FROM us_zip WHERE zip = ?');
    $stmt->execute(['68815']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    expect($row)->not->toBeFalse();
    expect((float) $row['lat'])->toBeGreaterThan(40);

    // Verify normalizer was applied to cities
    $stmt = $pdo->prepare('SELECT lat FROM cities WHERE name_norm = ? AND country_code = ?');
    $stmt->execute(['paris', 'FR']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    expect($row)->not->toBeFalse();

    // Clean up
    @unlink($outputPath);
});

test('builds database with atomic swap', function () {
    $outputPath = sys_get_temp_dir().'/atlas_test_'.uniqid().'.sqlite';
    $tmpPath = $outputPath.'.tmp';

    $builder = new BuildDatabase(new Normalizer);
    $builder->buildFromData(
        $outputPath,
        __DIR__.'/../fixtures/geonames/US.txt',
        __DIR__.'/../fixtures/geonames/cities15000.txt',
        __DIR__.'/../fixtures/geonames/countryInfo.txt',
    );

    // Final file exists, temp does not
    expect(file_exists($outputPath))->toBeTrue();
    expect(file_exists($tmpPath))->toBeFalse();

    @unlink($outputPath);
});

test('build database populates country aliases including overrides', function () {
    $outputPath = sys_get_temp_dir().'/atlas_test_'.uniqid().'.sqlite';

    $builder = new BuildDatabase(new Normalizer);
    $builder->buildFromData(
        $outputPath,
        __DIR__.'/../fixtures/geonames/US.txt',
        __DIR__.'/../fixtures/geonames/cities15000.txt',
        __DIR__.'/../fixtures/geonames/countryInfo.txt',
    );

    $pdo = new PDO('sqlite:'.$outputPath);

    // Check manual overrides
    $stmt = $pdo->prepare('SELECT iso_code FROM country_aliases WHERE country_name = ?');

    $stmt->execute(['USA']);
    expect($stmt->fetchColumn())->toBe('US');

    $stmt->execute(['UK']);
    expect($stmt->fetchColumn())->toBe('GB');

    $stmt->execute(['United States of America (the)']);
    expect($stmt->fetchColumn())->toBe('US');

    @unlink($outputPath);
});
