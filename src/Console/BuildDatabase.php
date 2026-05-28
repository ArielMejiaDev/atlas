<?php

namespace ArielMejiaDev\Atlas\Console;

use ArielMejiaDev\Atlas\Support\Normalizer;
use Illuminate\Support\Facades\Http;
use PDO;
use ZipArchive;

class BuildDatabase
{
    public const SOURCES = [
        'cities' => 'https://download.geonames.org/export/dump/cities15000.zip',
        'countries' => 'https://download.geonames.org/export/dump/countryInfo.txt',
        'us_zip' => 'https://download.geonames.org/export/zip/US.zip',
    ];

    public function __construct(private Normalizer $normalizer) {}

    /**
     * @param  callable|null  $progress  Receives short status strings ("Downloading…").
     */
    public function build(string $outputPath, ?callable $progress = null): void
    {
        $tmpPath = $outputPath.'.tmp';
        $tempDir = sys_get_temp_dir().'/atlas_build_'.uniqid();

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            $this->report($progress, 'Downloading GeoNames data...');

            $citiesFile = $this->downloadAndExtract(self::SOURCES['cities'], $tempDir, 'cities15000.txt', $progress);
            $countriesFile = $this->download(self::SOURCES['countries'], $tempDir, 'countryInfo.txt', $progress);
            $usZipFile = $this->downloadAndExtract(self::SOURCES['us_zip'], $tempDir, 'US.txt', $progress);

            $this->report($progress, 'Building SQLite database...');

            $pdo = new PDO('sqlite:'.$tmpPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA synchronous=OFF');
            $pdo->exec('PRAGMA journal_mode=MEMORY');

            $this->createSchema($pdo);

            $pdo->beginTransaction();

            $this->report($progress, 'Importing US ZIP codes...');
            $usCityStateData = $this->importUsZip($pdo, $usZipFile);

            $this->report($progress, 'Importing US city/state data...');
            $this->importUsCityState($pdo, $usCityStateData);

            $this->report($progress, 'Importing world cities...');
            $this->importCities($pdo, $citiesFile);

            $this->report($progress, 'Importing country data...');
            $this->importCountries($pdo, $countriesFile);

            $this->report($progress, 'Building country centroids...');
            $this->buildCountryCentroids($pdo, $countriesFile);

            $this->report($progress, 'Building big cities index...');
            $this->importBigCities($pdo, $citiesFile);

            $pdo->commit();

            $this->report($progress, 'Compacting database...');
            $pdo->exec('VACUUM');

            // Close connection before rename
            $pdo = null;

            // Atomic swap
            $dir = dirname($outputPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            rename($tmpPath, $outputPath);

            $this->report($progress, 'Done! Database built successfully.');
        } catch (\Throwable $e) {
            // Clean up temp file on failure
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            throw $e;
        } finally {
            $this->cleanTempDir($tempDir);
        }
    }

    /**
     * Build from parsed data arrays (for testing).
     */
    public function buildFromData(string $outputPath, string $usZipFile, string $citiesFile, string $countriesFile, ?callable $progress = null): void
    {
        $tmpPath = $outputPath.'.tmp';

        try {
            $pdo = new PDO('sqlite:'.$tmpPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA synchronous=OFF');
            $pdo->exec('PRAGMA journal_mode=MEMORY');

            $this->createSchema($pdo);

            $pdo->beginTransaction();

            $usCityStateData = $this->importUsZip($pdo, $usZipFile);
            $this->importUsCityState($pdo, $usCityStateData);
            $this->importCities($pdo, $citiesFile);
            $this->importCountries($pdo, $countriesFile);
            $this->buildCountryCentroids($pdo, $countriesFile);
            $this->importBigCities($pdo, $citiesFile);

            $pdo->commit();

            $pdo->exec('VACUUM');
            $pdo = null;

            $dir = dirname($outputPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            rename($tmpPath, $outputPath);
        } catch (\Throwable $e) {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            throw $e;
        }
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE us_zip (zip TEXT PRIMARY KEY, city TEXT, state TEXT, lat REAL, lng REAL)');
        $pdo->exec('CREATE TABLE us_city_state (city_norm TEXT, state TEXT, lat REAL, lng REAL, PRIMARY KEY (city_norm, state))');
        $pdo->exec('CREATE TABLE cities (name_norm TEXT NOT NULL, country_code TEXT NOT NULL, lat REAL NOT NULL, lng REAL NOT NULL, population INTEGER DEFAULT 0, PRIMARY KEY (name_norm, country_code))');
        $pdo->exec('CREATE INDEX idx_cities_cc ON cities(country_code)');
        $pdo->exec('CREATE TABLE country_aliases (country_name TEXT PRIMARY KEY, iso_code TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE country_centroids (iso_code TEXT PRIMARY KEY, name TEXT, lat REAL, lng REAL)');
        $pdo->exec('CREATE TABLE big_cities_global (name_norm TEXT PRIMARY KEY, country_code TEXT, lat REAL, lng REAL, population INTEGER)');
    }

    private function download(string $url, string $tempDir, string $filename, ?callable $progress = null): string
    {
        $this->report($progress, "Downloading $filename...");

        $path = $tempDir.'/'.$filename;
        $response = Http::timeout(60)->sink($path)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download $url: HTTP {$response->status()}");
        }

        return $path;
    }

    private function downloadAndExtract(string $url, string $tempDir, string $innerFile, ?callable $progress = null): string
    {
        $zipFile = $tempDir.'/'.basename($url);
        $this->report($progress, 'Downloading '.basename($url).'...');

        $response = Http::timeout(120)->sink($zipFile)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download $url: HTTP {$response->status()}");
        }

        $this->report($progress, 'Extracting '.basename($url).'...');

        $zip = new ZipArchive;
        $result = $zip->open($zipFile);

        if ($result !== true) {
            throw new \RuntimeException("Failed to open ZIP file $zipFile: error code $result");
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $extractedPath = $tempDir.'/'.$innerFile;

        if (! file_exists($extractedPath)) {
            // Try to find the file in subdirectories
            $files = glob($tempDir.'/**/'.$innerFile);
            if (! empty($files)) {
                $extractedPath = $files[0];
            } else {
                throw new \RuntimeException("Expected file $innerFile not found in ZIP archive");
            }
        }

        return $extractedPath;
    }

    /**
     * @return array<array{city: string, state: string, lat: float, lng: float}>
     */
    private function importUsZip(PDO $pdo, string $filePath): array
    {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO us_zip (zip, city, state, lat, lng) VALUES (:zip, :city, :state, :lat, :lng)');

        $usCityStateData = [];
        $fp = fopen($filePath, 'r');

        if ($fp === false) {
            throw new \RuntimeException("Cannot open file: $filePath");
        }

        while (($row = fgetcsv($fp, 0, "\t", '"', '')) !== false) {
            if (count($row) < 12) {
                continue;
            }

            // Columns: country, postal_code, place_name, admin_name1, admin_code1,
            //          admin_name2, admin_code2, admin_name3, admin_code3, latitude, longitude, accuracy
            $zip = $row[1];
            $city = $row[2];
            $state = $row[4]; // admin_code1
            $lat = (float) $row[9];
            $lng = (float) $row[10];

            $stmt->execute([
                'zip' => $zip,
                'city' => $city,
                'state' => $state,
                'lat' => $lat,
                'lng' => $lng,
            ]);

            $usCityStateData[] = [
                'city' => $city,
                'state' => $state,
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        fclose($fp);

        return $usCityStateData;
    }

    /**
     * @param  array<array{city: string, state: string, lat: float, lng: float}>  $data
     */
    private function importUsCityState(PDO $pdo, array $data): void
    {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO us_city_state (city_norm, state, lat, lng) VALUES (:city_norm, :state, :lat, :lng)');

        foreach ($data as $row) {
            $cityNorm = $this->normalizer->normalize($row['city']);

            if ($cityNorm === '') {
                continue;
            }

            $stmt->execute([
                'city_norm' => $cityNorm,
                'state' => strtoupper($row['state']),
                'lat' => $row['lat'],
                'lng' => $row['lng'],
            ]);
        }
    }

    private function importCities(PDO $pdo, string $filePath): void
    {
        // We use INSERT OR REPLACE with a subquery to keep highest population
        $insertStmt = $pdo->prepare(
            'INSERT INTO cities (name_norm, country_code, lat, lng, population)
             VALUES (:name_norm, :cc, :lat, :lng, :pop)
             ON CONFLICT(name_norm, country_code)
             DO UPDATE SET lat = CASE WHEN excluded.population > cities.population THEN excluded.lat ELSE cities.lat END,
                          lng = CASE WHEN excluded.population > cities.population THEN excluded.lng ELSE cities.lng END,
                          population = MAX(cities.population, excluded.population)'
        );

        $fp = fopen($filePath, 'r');

        if ($fp === false) {
            throw new \RuntimeException("Cannot open file: $filePath");
        }

        while (($row = fgetcsv($fp, 0, "\t", '"', '')) !== false) {
            if (count($row) < 19) {
                continue;
            }

            // Columns: geonameid(0), name(1), asciiname(2), alternatenames(3),
            //          latitude(4), longitude(5), feature_class(6), feature_code(7),
            //          country_code(8), cc2(9), admin1(10), admin2(11), admin3(12),
            //          admin4(13), population(14), elevation(15), dem(16),
            //          timezone(17), modification_date(18)
            $name = $row[1];
            $asciiname = $row[2];
            $alternatenames = $row[3];
            $lat = (float) $row[4];
            $lng = (float) $row[5];
            $countryCode = $row[8];
            $population = (int) $row[14];

            // Collect all names
            $names = [$name, $asciiname];

            if ($alternatenames !== '') {
                $names = array_merge($names, explode(',', $alternatenames));
            }

            $seen = [];
            foreach ($names as $n) {
                $normalized = $this->normalizer->normalize($n);

                if ($normalized === '' || isset($seen[$normalized])) {
                    continue;
                }
                $seen[$normalized] = true;

                $insertStmt->execute([
                    'name_norm' => $normalized,
                    'cc' => $countryCode,
                    'lat' => $lat,
                    'lng' => $lng,
                    'pop' => $population,
                ]);
            }
        }

        fclose($fp);
    }

    private function importCountries(PDO $pdo, string $filePath): void
    {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO country_aliases (country_name, iso_code) VALUES (:name, :iso)');

        $fp = fopen($filePath, 'r');

        if ($fp === false) {
            throw new \RuntimeException("Cannot open file: $filePath");
        }

        while (($line = fgets($fp)) !== false) {
            $line = trim($line);

            // Skip comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < 5) {
                continue;
            }

            // Columns: ISO(0), ISO3(1), ISO-Numeric(2), fips(3), Country(4),
            //          Capital(5), Area(6), Population(7), Continent(8), ...
            $iso = $cols[0];
            $country = $cols[4];

            if ($iso === '' || $country === '') {
                continue;
            }

            // Insert Country → ISO
            $stmt->execute(['name' => $country, 'iso' => $iso]);

            // Insert ISO code itself as an alias
            $stmt->execute(['name' => $iso, 'iso' => $iso]);

            // Insert ISO3 as alias
            if (! empty($cols[1])) {
                $stmt->execute(['name' => $cols[1], 'iso' => $iso]);
            }
        }

        fclose($fp);

        // Manual overrides for common names and ISO long forms
        $overrides = [
            // US variants
            'United States of America (the)' => 'US',
            'United States of America' => 'US',
            'United States' => 'US',
            'USA' => 'US',
            // UK variants
            'United Kingdom' => 'GB',
            'UK' => 'GB',
            'Great Britain' => 'GB',
            'England' => 'GB',
            'Scotland' => 'GB',
            'Wales' => 'GB',
            // Common aliases
            'Russia' => 'RU',
            'Russian Federation' => 'RU',
            'Russian Federation (the)' => 'RU',
            'South Korea' => 'KR',
            'Korea (the Republic of)' => 'KR',
            'Republic of Korea' => 'KR',
            'North Korea' => 'KP',
            "Korea (the Democratic People's Republic of)" => 'KP',
            'Taiwan' => 'TW',
            'Taiwan (Province of China)' => 'TW',
            'Vietnam' => 'VN',
            'Viet Nam' => 'VN',
            'India' => 'IN',
            'China' => 'CN',
            "China (the People's Republic of)" => 'CN',
            'Iran' => 'IR',
            'Iran (Islamic Republic of)' => 'IR',
            'Syria' => 'SY',
            'Syrian Arab Republic' => 'SY',
            'Venezuela' => 'VE',
            'Venezuela (Bolivarian Republic of)' => 'VE',
            'Bolivia' => 'BO',
            'Bolivia (Plurinational State of)' => 'BO',
            'Tanzania' => 'TZ',
            'Tanzania, United Republic of' => 'TZ',
            'Czech Republic' => 'CZ',
            'Czechia' => 'CZ',
            'Sri Lanka' => 'LK',
            'Srilanka' => 'LK',
            'Burma' => 'MM',
            'Myanmar' => 'MM',
            'Ivory Coast' => 'CI',
            "Cote d'Ivoire" => 'CI',
            'Congo' => 'CD',
            'Democratic Republic of the Congo' => 'CD',
            'DR Congo' => 'CD',
            'DRC' => 'CD',
            'UAE' => 'AE',
            'United Arab Emirates' => 'AE',
            'United Arab Emirates (the)' => 'AE',
            'Philippines' => 'PH',
            'Philippines (the)' => 'PH',
            'Netherlands' => 'NL',
            'Netherlands (the)' => 'NL',
            'Holland' => 'NL',
            'Moldova' => 'MD',
            'Moldova (the Republic of)' => 'MD',
            'Laos' => 'LA',
            "Lao People's Democratic Republic" => 'LA',
            'Palestine' => 'PS',
            'State of Palestine' => 'PS',
            'Macau' => 'MO',
            'Macao' => 'MO',
            'Hong Kong' => 'HK',
            'Brunei' => 'BN',
            'Brunei Darussalam' => 'BN',
            'Eswatini' => 'SZ',
            'Swaziland' => 'SZ',
            'Timor-Leste' => 'TL',
            'East Timor' => 'TL',
            'Cabo Verde' => 'CV',
            'Cape Verde' => 'CV',
            'Micronesia' => 'FM',
            'Micronesia (Federated States of)' => 'FM',
        ];

        foreach ($overrides as $name => $iso) {
            $stmt->execute(['name' => $name, 'iso' => $iso]);
        }
    }

    private function buildCountryCentroids(PDO $pdo, string $countriesFile): void
    {
        $centroidStmt = $pdo->prepare('INSERT OR IGNORE INTO country_centroids (iso_code, name, lat, lng) VALUES (:iso, :name, :lat, :lng)');

        $fp = fopen($countriesFile, 'r');

        if ($fp === false) {
            throw new \RuntimeException("Cannot open file: $countriesFile");
        }

        while (($line = fgets($fp)) !== false) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < 6) {
                continue;
            }

            $iso = $cols[0];
            $country = $cols[4];
            $capital = $cols[5];

            if ($iso === '') {
                continue;
            }

            // Try to find capital city in cities table
            $lat = null;
            $lng = null;

            if ($capital !== '') {
                $capitalNorm = $this->normalizer->normalize($capital);
                $cityStmt = $pdo->prepare('SELECT lat, lng FROM cities WHERE name_norm = :name AND country_code = :cc ORDER BY population DESC LIMIT 1');
                $cityStmt->execute(['name' => $capitalNorm, 'cc' => $iso]);
                $row = $cityStmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $lat = (float) $row['lat'];
                    $lng = (float) $row['lng'];
                }
            }

            // Fall back to highest-population city in country
            if ($lat === null) {
                $fallbackStmt = $pdo->prepare('SELECT lat, lng FROM cities WHERE country_code = :cc ORDER BY population DESC LIMIT 1');
                $fallbackStmt->execute(['cc' => $iso]);
                $row = $fallbackStmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $lat = (float) $row['lat'];
                    $lng = (float) $row['lng'];
                }
            }

            if ($lat !== null && $lng !== null) {
                $centroidStmt->execute([
                    'iso' => $iso,
                    'name' => $country,
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
            }
        }

        fclose($fp);
    }

    private function importBigCities(PDO $pdo, string $filePath): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO big_cities_global (name_norm, country_code, lat, lng, population)
             VALUES (:name_norm, :cc, :lat, :lng, :pop)
             ON CONFLICT(name_norm)
             DO UPDATE SET country_code = CASE WHEN excluded.population > big_cities_global.population THEN excluded.country_code ELSE big_cities_global.country_code END,
                          lat = CASE WHEN excluded.population > big_cities_global.population THEN excluded.lat ELSE big_cities_global.lat END,
                          lng = CASE WHEN excluded.population > big_cities_global.population THEN excluded.lng ELSE big_cities_global.lng END,
                          population = MAX(big_cities_global.population, excluded.population)'
        );

        $fp = fopen($filePath, 'r');

        if ($fp === false) {
            throw new \RuntimeException("Cannot open file: $filePath");
        }

        while (($row = fgetcsv($fp, 0, "\t", '"', '')) !== false) {
            if (count($row) < 19) {
                continue;
            }

            $population = (int) $row[14];

            if ($population < 50000) {
                continue;
            }

            $name = $row[1];
            $lat = (float) $row[4];
            $lng = (float) $row[5];
            $countryCode = $row[8];

            $normalized = $this->normalizer->normalize($name);

            if ($normalized === '') {
                continue;
            }

            $stmt->execute([
                'name_norm' => $normalized,
                'cc' => $countryCode,
                'lat' => $lat,
                'lng' => $lng,
                'pop' => $population,
            ]);
        }

        fclose($fp);
    }

    private function report(?callable $progress, string $message): void
    {
        if ($progress !== null) {
            $progress($message);
        }
    }

    private function cleanTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir.'/*');

        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        @rmdir($dir);
    }
}
