# Geocoding Methods

Atlas uses a cascading strategy — it tries methods in a fixed order and returns the first hit. This page explains each method and when it fires.

## Method Priority

| # | Method | Condition | Precision |
|---|--------|-----------|-----------|
| 1 | `us_zip` | US + valid ZIP code | ZIP centroid |
| 2 | `us_city_state` | US + city + state | City centroid |
| 3 | `city_exact` | International + exact city match | City centroid |
| 4 | `state_as_city` | International + state matches a city | City centroid |
| 5 | `city_partial` | International + substring city match | City centroid |
| 6 | `country_centroid` | Country resolved but no city match | Capital/largest city |
| 7 | `text_extract_city` | Country detected from text + city found | City centroid |
| 8 | `text_extract_country_centroid` | Country detected but city not found | Capital/largest city |
| 9 | `global_big_city_match` | No country — matches big city names | City centroid |

## US Path (Methods 1–2)

These only run when the `country` field matches one of the configured US country names (e.g., `"US"`, `"USA"`, `"United States"`).

### `us_zip`

Splits the ZIP on `-`, left-pads to 5 digits, and looks it up in the `us_zip` table. Handles formats like `68815`, `068815`, and `68815-1234`.

### `us_city_state`

Normalizes the city name and uppercases the state, then queries the `us_city_state` table. Falls through if ZIP was provided but didn't match (mistyped ZIP).

## International Path (Methods 3–6)

These run for any country that can be resolved to an ISO code via the `country_aliases` table. Also runs as a fallback when the US path misses.

### `city_exact`

Normalizes the city name and queries the `cities` table for an exact match within the resolved country. Returns the highest-population match.

### `state_as_city`

Same as `city_exact`, but uses the `state` field as the city name. Handles messy data where the city is in the state field (e.g., `state="Phnom Penh"`).

### `city_partial`

Runs a `LIKE` query in both directions:
- Cities whose name **contains** the query
- Cities whose name **is contained in** the query

Fetches up to 50 candidates ordered by population, then picks the one whose name length is closest to the query length.

### `country_centroid`

Falls back to the country's centroid — typically the capital city's coordinates, or the largest city if the capital isn't in the database.

## Text Mining (Methods 7–8)

These run when the `country` field is empty or can't be resolved. Atlas concatenates all input fields into a single text blob and tries to detect the country.

### Country Detection

1. Normalizes the blob and searches for known country names (longest first, whole-word match)
2. **India heuristic:** if the blob contains a 6-digit number (`\b\d{6}\b`), assumes India (Indian postal codes are 6 digits)

### `text_extract_city`

After detecting a country, queries that country's cities ordered by name length (longest first) and population. Returns the first city whose name appears as a whole word in the blob.

### `text_extract_country_centroid`

Falls back to the detected country's centroid when no city is found in the text.

## Last Resort (Method 9)

### `global_big_city_match`

Tokenizes the entire input blob (tokens with 3+ characters) and queries the `big_cities_global` table (cities with population 50k+). Returns the highest-population match. This is the absolute last resort when no country can be determined.

## Understanding the `method` Field

The `method` field in the `Result` object tells you which strategy resolved the address. Use it for:

- **Quality assessment** — `us_zip` and `city_exact` are high confidence; `country_centroid` and `global_big_city_match` are low
- **Analytics** — track which methods fire most often to understand your data quality
- **Debugging** — when results look wrong, the method tells you where the algorithm landed

```php
$result = Atlas::geocode($input);

match ($result?->method) {
    'us_zip', 'us_city_state', 'city_exact' => 'high confidence',
    'state_as_city', 'city_partial', 'text_extract_city' => 'medium confidence',
    'country_centroid', 'text_extract_country_centroid', 'global_big_city_match' => 'low confidence',
    default => 'no result',
};
```
