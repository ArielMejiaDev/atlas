# What is Atlas?

Atlas is a Laravel package that fills `latitude` and `longitude` on any Eloquent model using a bundled SQLite database. It requires **no API keys, no rate limits, and no network calls at runtime**.

## How It Works

Atlas ships a PHP-only database builder that downloads public data from [GeoNames](https://www.geonames.org/) and compiles it into a ~22 MB SQLite file. At runtime, the geocoder queries this local database to resolve addresses into coordinates.

Coordinates are stored in a **package-owned polymorphic table** (`atlas_coordinates`), so your models don't need latitude/longitude columns. Any model can opt in by adding the `HasCoordinates` trait — each model defines its own column mapping independently.

The geocoding engine is **framework-agnostic** — it depends only on PDO and a shared `Normalizer` class. A thin Laravel service provider wires everything together: the database connection, the facade, and the Artisan commands.

## What It Is

- **Centroid-level geocoding** — resolves to city centers and ZIP code centroids
- **Ideal for** analytics dashboards, clustering, approximate distance calculations, and data enrichment
- **Covers** 100k+ world cities (population 15k+), all US ZIP codes, and country centroids

## What It Is Not

- **Not street-level** — it won't resolve `123 Main St` to a specific building
- **Not a replacement** for Google Maps, Mapbox, or other geocoding APIs when you need precise addresses
- **Not real-time** — the database is built once at install time; it doesn't auto-update

## Architecture

```
┌─────────────────────────────────────────────┐
│  Host Laravel App                           │
│                                             │
│  ┌─────────────────────────┐                │
│  │  Any Model              │                │
│  │  use HasCoordinates;    │                │
│  │  → geocodableColumns()  │                │
│  └──────────┬──────────────┘                │
│             │ $model->geocode()             │
│             ▼                               │
│  ┌──────────────────────┐                   │
│  │  OfflineGeocoder      │ ← Pure PHP       │
│  │  (PDO + Normalizer)   │   No Illuminate  │
│  └──────────┬───────────┘                   │
│             ▼                               │
│  ┌──────────────────────┐                   │
│  │  geocoding.sqlite     │ ← Built once     │
│  │  (~22 MB)             │   via artisan     │
│  └──────────┬───────────┘                   │
│             ▼                               │
│  ┌──────────────────────┐                   │
│  │  atlas_coordinates    │ ← Polymorphic    │
│  │  (package table)      │   per model      │
│  └──────────────────────┘                   │
└─────────────────────────────────────────────┘
```

## Data Source

All geocoding data comes from [GeoNames](https://www.geonames.org/), licensed under [Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/). The database is built from three public files:

| Source | Contents |
|--------|----------|
| `cities15000.zip` | World cities with population 15k+ |
| `US.zip` | US ZIP codes with coordinates |
| `countryInfo.txt` | Country names, aliases, and capitals |

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- PHP Extensions: `pdo_sqlite`, `zip`, `curl`
- Suggested: `intl` (improves Unicode transliteration)
