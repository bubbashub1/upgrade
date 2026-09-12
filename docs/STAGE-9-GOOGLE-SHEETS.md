# Stage 9 — Google Sheets live data integration

## Purpose
Connect the Figma-inspired front end to the real BubbaHub WordPress data layer without importing or shipping demo data.

## Data flow

Google Sheets / Google Apps Script JSON endpoint
→ `BubbaHubGoogleSync`
→ `bh_group` WordPress listings
→ REST API `/wp-json/bubbahub/v1/listings`
→ Figma-inspired front end

WordPress is the runtime source of truth. The Google endpoint is an import/synchronisation source, not a second front-end database.

## Included

- Existing Google Sheets listing and term-time endpoints remain configurable in **BubbaHub → Google Sheets Sync**.
- Existing permanent Google `id` and organiser `wp_username` matching is preserved.
- Existing secure signed API and optional write-back settings are preserved.
- New public listing endpoint exposes only published `bh_group` records.
- New single-listing endpoint exposes one published listing by WordPress ID.
- New authenticated Google sync endpoint allows a BubbaHub manager to trigger a sync from an authenticated WordPress session.
- Listing response includes the real directory fields used by the Figma design: address, city/region/postcode, age range, price/free, days/timetable/business hours, term-time, category/tags, social links and lat/long.
- OpenStreetMap/Leaflet remains the map layer; no Google Maps key is introduced.

## Endpoints

`GET /wp-json/bubbahub/v1/listings?page=1&per_page=24&search=...`

`GET /wp-json/bubbahub/v1/listings/{id}`

`POST /wp-json/bubbahub/v1/google-sync` — authenticated users with `manage_bubbahub` only.

## No demo data rule

The Figma Make export was treated as a UI reference only. Sample parents, children, leaders, bookings, analytics, avatars and listings are not imported. Empty WordPress records remain empty until real data exists.

## Important operational note

Before enabling automatic Google write-back on production, test the configured Apps Script secure endpoint on staging. Import/sync should be run after the Stage 8 branch is installed and the Google endpoint settings have been checked.
