# Stage 8 — Google Sheets import

## Purpose
Import the real BubbaHub directory data exposed by the configured Google Apps Script JSON endpoints into the WordPress `bh_group` listing database.

## Behaviour
- Adds **BubbaHub → Import Google Sheets** in WordPress admin.
- Imports from the existing Listings and Term-time endpoints configured under Google Sheets Sync.
- Merges duplicate rows from the two endpoints.
- Matches existing listings by permanent Google `id` first, then organiser `wp_username`.
- Creates new `bh_group` posts when no match exists.
- Updates existing matched listings rather than creating duplicates.
- Imports address, coordinates, timetable, business hours, website, email, social links, price, age range, category, tags, featured state and other established listing fields.
- Records Google ID, WordPress username and last import time in post meta.
- Does not delete WordPress listings merely because they are absent from an import response.
- Does not create demo or sample records.

## Safety
The importer is deliberately manual in this stage. Two-way write-back remains governed by the existing secure Google Sync settings and is the focus of Stage 9.

## Required configuration
Use **BubbaHub → Google Sheets Sync** to confirm the Listings API and Term-time API endpoints. If the endpoint requires signed requests, configure the existing shared secret and secure API settings there.

## Next stage
Stage 9 adds robust two-way synchronisation, change tracking and conflict handling between WordPress and Google Sheets.
