<?php

namespace ArielMejiaDev\Atlas;

use ArielMejiaDev\Atlas\Contracts\GeocoderDriver;
use ArielMejiaDev\Atlas\Support\Normalizer;
use ArielMejiaDev\Atlas\Support\Result;
use PDO;

class OfflineGeocoder implements GeocoderDriver
{
    private ?array $countryAliases = null;

    public function __construct(
        private PDO $pdo,
        private Normalizer $normalizer,
        private array $config = [],
    ) {}

    public function geocode(array $input): ?Result
    {
        $address = trim($input['address'] ?? '');

        if ($address === '') {
            return null;
        }

        $city = trim($input['city'] ?? '');
        $state = trim($input['state'] ?? '');
        $zip = trim($input['zip'] ?? '');
        $country = trim($input['country'] ?? '');

        $usCountryNames = $this->config['us_country_names'] ?? [
            'United States of America (the)',
            'United States of America',
            'United States',
            'USA',
            'US',
        ];

        $isUs = in_array($country, $usCountryNames, true);

        // 1. US path
        if ($isUs) {
            // us_zip
            if ($zip !== '') {
                $result = $this->lookupUsZip($zip);
                if ($result !== null) {
                    return $result;
                }
            }

            // us_city_state
            if ($city !== '' && $state !== '') {
                $result = $this->lookupUsCityState($city, $state);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        // 2. International path (also runs if US path missed)
        $isoCode = $this->resolveCountryIso($country);

        if ($isoCode !== null) {
            // city_exact
            if ($city !== '') {
                $result = $this->lookupCityExact($city, $isoCode);
                if ($result !== null) {
                    return $result;
                }
            }

            // state_as_city
            if ($state !== '') {
                $result = $this->lookupCityExact($state, $isoCode);
                if ($result !== null) {
                    return new Result($result->latitude, $result->longitude, 'state_as_city', $result->note);
                }
            }

            // city_partial
            if ($city !== '') {
                $result = $this->lookupCityPartial($city, $isoCode);
                if ($result !== null) {
                    return $result;
                }
            }

            // country_centroid
            $result = $this->lookupCountryCentroid($isoCode);
            if ($result !== null) {
                return $result;
            }
        }

        // 3. Text mining (when country is missing/unresolvable)
        $blob = implode(' ', [$address, $city, $state, $zip, $country]);
        $normalizedBlob = $this->normalizer->normalize($blob);

        if ($normalizedBlob !== '') {
            $detectedIso = $this->detectCountryFromText($normalizedBlob);

            if ($detectedIso !== null) {
                // text_extract_city
                $result = $this->lookupCityFromText($normalizedBlob, $detectedIso);
                if ($result !== null) {
                    return $result;
                }

                // text_extract_country_centroid
                $result = $this->lookupCountryCentroid($detectedIso);
                if ($result !== null) {
                    return new Result($result->latitude, $result->longitude, 'text_extract_country_centroid', $result->note);
                }
            }
        }

        // 4. global_big_city_match
        return $this->lookupGlobalBigCity($normalizedBlob);
    }

    private function lookupUsZip(string $zip): ?Result
    {
        // Split on dash, left-pad to 5, keep first 5
        $parts = explode('-', $zip);
        $zipCode = str_pad($parts[0], 5, '0', STR_PAD_LEFT);
        $zipCode = substr($zipCode, 0, 5);

        // Only proceed if digits-only
        if (! ctype_digit($zipCode)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT lat, lng FROM us_zip WHERE zip = :zip LIMIT 1');
        $stmt->execute(['zip' => $zipCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new Result((float) $row['lat'], (float) $row['lng'], 'us_zip');
    }

    private function lookupUsCityState(string $city, string $state): ?Result
    {
        $cityNorm = $this->normalizer->normalize($city);
        $stateUpper = strtoupper($state);

        $stmt = $this->pdo->prepare('SELECT lat, lng FROM us_city_state WHERE city_norm = :city AND state = :state LIMIT 1');
        $stmt->execute(['city' => $cityNorm, 'state' => $stateUpper]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new Result((float) $row['lat'], (float) $row['lng'], 'us_city_state');
    }

    private function resolveCountryIso(string $country): ?string
    {
        if ($country === '') {
            return null;
        }

        $aliases = $this->getCountryAliases();
        $normalized = $this->normalizer->normalize($country);

        return $aliases[$normalized] ?? null;
    }

    private function getCountryAliases(): array
    {
        if ($this->countryAliases !== null) {
            return $this->countryAliases;
        }

        $stmt = $this->pdo->query('SELECT country_name, iso_code FROM country_aliases');
        $this->countryAliases = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $this->normalizer->normalize($row['country_name']);
            $this->countryAliases[$key] = $row['iso_code'];
        }

        return $this->countryAliases;
    }

    private function lookupCityExact(string $city, string $countryCode): ?Result
    {
        $cityNorm = $this->normalizer->normalize($city);

        if ($cityNorm === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT lat, lng FROM cities WHERE name_norm = :name AND country_code = :cc ORDER BY population DESC LIMIT 1'
        );
        $stmt->execute(['name' => $cityNorm, 'cc' => $countryCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new Result((float) $row['lat'], (float) $row['lng'], 'city_exact');
    }

    private function lookupCityPartial(string $city, string $countryCode): ?Result
    {
        $cityNorm = $this->normalizer->normalize($city);

        if ($cityNorm === '') {
            return null;
        }

        // LIKE both directions: city contains query OR query contains city
        $stmt = $this->pdo->prepare(
            'SELECT name_norm, lat, lng FROM cities
             WHERE country_code = :cc AND (name_norm LIKE :pattern1 OR :query LIKE \'%\' || name_norm || \'%\')
             ORDER BY population DESC LIMIT 50'
        );
        $stmt->execute([
            'cc' => $countryCode,
            'pattern1' => '%'.$cityNorm.'%',
            'query' => $cityNorm,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return null;
        }

        // Pick the row whose LENGTH(name_norm) is closest to the query length
        $queryLen = mb_strlen($cityNorm);
        $best = null;
        $bestDiff = PHP_INT_MAX;

        foreach ($rows as $row) {
            $diff = abs(mb_strlen($row['name_norm']) - $queryLen);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $row;
            }
        }

        if ($best === null) {
            return null;
        }

        return new Result((float) $best['lat'], (float) $best['lng'], 'city_partial');
    }

    private function lookupCountryCentroid(string $isoCode): ?Result
    {
        $stmt = $this->pdo->prepare('SELECT lat, lng FROM country_centroids WHERE iso_code = :iso LIMIT 1');
        $stmt->execute(['iso' => $isoCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new Result((float) $row['lat'], (float) $row['lng'], 'country_centroid');
    }

    private function detectCountryFromText(string $normalizedBlob): ?string
    {
        $aliases = $this->getCountryAliases();

        // Sort by key length descending (longest needle first)
        $sortedAliases = $aliases;
        uksort($sortedAliases, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $paddedBlob = ' '.$normalizedBlob.' ';

        foreach ($sortedAliases as $name => $iso) {
            if ($name === '') {
                continue;
            }
            if (str_contains($paddedBlob, ' '.$name.' ')) {
                return $iso;
            }
        }

        // Heuristic: if blob contains a 6-digit number, treat as India
        if (preg_match('/\b\d{6}\b/', $normalizedBlob)) {
            return 'IN';
        }

        return null;
    }

    private function lookupCityFromText(string $normalizedBlob, string $countryCode): ?Result
    {
        $stmt = $this->pdo->prepare(
            'SELECT name_norm, lat, lng FROM cities
             WHERE country_code = :cc AND LENGTH(name_norm) >= 3
             ORDER BY LENGTH(name_norm) DESC, population DESC
             LIMIT 20000'
        );
        $stmt->execute(['cc' => $countryCode]);

        $paddedBlob = ' '.$normalizedBlob.' ';

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Whole-word match
            if (str_contains($paddedBlob, ' '.$row['name_norm'].' ')) {
                return new Result((float) $row['lat'], (float) $row['lng'], 'text_extract_city');
            }
        }

        return null;
    }

    private function lookupGlobalBigCity(string $normalizedBlob): ?Result
    {
        if ($normalizedBlob === '') {
            return null;
        }

        // Tokenize the blob (tokens >= 3 chars)
        $tokens = preg_split('/\s+/', $normalizedBlob);
        $tokens = array_filter($tokens, fn (string $t) => mb_strlen($t) >= 3);
        $tokens = array_values($tokens);

        if (empty($tokens)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($tokens), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT name_norm, lat, lng FROM big_cities_global WHERE name_norm IN ($placeholders) ORDER BY population DESC LIMIT 1"
        );
        $stmt->execute($tokens);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new Result((float) $row['lat'], (float) $row['lng'], 'global_big_city_match');
    }
}
