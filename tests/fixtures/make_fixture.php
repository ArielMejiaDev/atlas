<?php

/**
 * Regenerate the tiny test fixture database.
 *
 * Usage: php tests/fixtures/make_fixture.php
 */
require_once __DIR__.'/../../vendor/autoload.php';

use ArielMejiaDev\Atlas\Console\BuildDatabase;
use ArielMejiaDev\Atlas\Support\Normalizer;

$outputPath = __DIR__.'/geocoding.sqlite';
$usZipFile = __DIR__.'/geonames/US.txt';
$citiesFile = __DIR__.'/geonames/cities15000.txt';
$countriesFile = __DIR__.'/geonames/countryInfo.txt';

if (file_exists($outputPath)) {
    unlink($outputPath);
}

$builder = new BuildDatabase(new Normalizer);
$builder->buildFromData($outputPath, $usZipFile, $citiesFile, $countriesFile, function (string $msg) {
    echo $msg.PHP_EOL;
});

echo "Fixture database created at: $outputPath".PHP_EOL;

// Verify
$pdo = new PDO('sqlite:'.$outputPath);
$tables = ['us_zip', 'us_city_state', 'cities', 'country_aliases', 'country_centroids', 'big_cities_global'];

foreach ($tables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "  $table: $count rows".PHP_EOL;
}
