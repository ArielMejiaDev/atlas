---
layout: home

hero:
  name: Atlas
  text: Offline Geocoder for Laravel
  tagline: Fill latitude and longitude on any Eloquent model from a bundled SQLite database. No API keys, no rate limits, no network calls.
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/arielmejiadev/atlas
  image:
    src: /logo.svg
    alt: Atlas

features:
  - icon: "\U0001F50C"
    title: Fully Offline
    details: All geocoding data is stored in a local SQLite file. No external API calls at runtime — works in air-gapped environments.
  - icon: "\u26A1"
    title: Fast
    details: SQLite lookups are sub-millisecond. Backfill thousands of records in seconds with the built-in Artisan command.
  - icon: "\U0001F527"
    title: Multi-Model
    details: Geocode any number of models with different schemas. Each model defines its own column mapping via a simple trait.
  - icon: "\U0001F30D"
    title: Global Coverage
    details: Ships with world cities (population 15k+), US ZIP codes, and country centroids sourced from GeoNames.
  - icon: "\U0001F4E6"
    title: Lightweight
    details: Only ~22 MB on disk. The database is built at install time — Composer ships just the builder and a tiny test fixture.
  - icon: "\U0001F9E9"
    title: Framework-Agnostic Core
    details: The geocoding engine uses only PDO — no Laravel imports. The package provides a thin Laravel binding layer on top.
---
