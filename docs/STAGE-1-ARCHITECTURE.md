# BubbaHub Stage 1 — Architecture Baseline

## Purpose

This document records the safe baseline for converting the BubbaHub WordPress application into the live implementation of the Figma site while preserving existing production functionality.

## Repository baseline

- Repository: `bubbashub1/upgrade`
- Base branch: `main`
- Working branch: `stage-1-architecture`
- Current plugin header observed on `main`: BubbaHub Production 4.1.4
- Minimum WordPress: 6.4
- Minimum PHP: 8.0
- CI PHP lint currently targets PHP 8.2.

## Current architecture observed

The plugin is already a modular WordPress application rather than a blank theme/site shell. The bootstrap currently loads:

- `class-bubbahub.php` — core application, post types/taxonomies, pages, shortcodes and activation
- `class-rest.php` — REST API
- `class-admin.php` — WordPress admin functionality
- `class-finance.php` and provider/security modules — finance and payment functionality
- `class-account-types.php` — account types
- `class-google-sync.php` — Google Sheets integration
- `class-listing-migration.php` — listing migration
- REST/shortcode compatibility modules
- `assests/css/*` and `assests/js/*` — existing front-end assets
- `templates/app.php` — application template

The repository therefore needs an incremental redesign/integration, not a replacement plugin.

## Stage 1 design rules

1. Preserve the existing data and business logic unless a later stage explicitly replaces it.
2. Do not introduce duplicate global functions, classes, hooks or constants. Existing fatal errors have occurred from duplicate declarations, so all new code must use the existing class/module architecture or uniquely namespaced BubbaHub classes.
3. No demo or seeded business/listing records should be introduced for the live site. Empty states must be used when real data is unavailable.
4. WordPress remains the source of truth for editable listing records. Google Sheets is an integration/synchronisation source, not a replacement database.
5. Listing/location fields must support the existing real data model and map `lat`, `latitude`, `long`, `lng` and `longitude` consistently to geographic coordinates.
6. OpenStreetMap/Leaflet will be used for mapping in the later mapping stage; no paid Google Maps dependency should be introduced.
7. Front-end presentation should be implemented as a layer over the existing backend/data services so the Figma visual design does not require duplicating data logic.
8. Every Figma navigation item will map to a real WordPress route/page/section backed by real data or a clearly defined empty state.
9. Existing parent/leader account and subscription functionality must remain intact while the visual layer is redesigned.
10. Every stage should remain independently installable and testable, with GitHub Actions PHP lint/package checks retained.

## Target layering

```text
WordPress core
  ├─ Users / roles / capabilities
  ├─ Posts / custom post types / taxonomies
  └─ Options / metadata
        ↓
BubbaHub domain layer
  ├─ Listings / groups / events
  ├─ Parent + leader accounts
  ├─ Bookings / finance / wallet
  ├─ Google Sheets sync
  └─ REST endpoints
        ↓
Presentation layer
  ├─ Figma-aligned templates/components
  ├─ Responsive CSS
  ├─ Vanilla JS interactions
  └─ Leaflet/OpenStreetMap map components
        ↓
Routes / shortcodes / WordPress pages
```

## Stage sequence

- Stage 1: architecture and safety baseline — this document
- Stage 2: foundation cleanup/repair
- Stage 3: real listing/database model
- Stage 4: Figma sections, navigation and routes
- Stage 5: front-end/admin editing
- Stage 6: OpenStreetMap + coordinates
- Stage 7: search, filters and radius
- Stage 8: Google Sheets import
- Stage 9: two-way Google Sheets sync
- Stage 10: permissions, security and audit history
- Stage 11: responsive/accessibility/SEO/performance polish
- Stage 12: full test, package and release

## Visual reference dependency

The public Figma site can be inspected at the supplied URL, but the current web extraction does not expose its visual layout reliably. The Figma screenshot/video/reference upload will therefore be used before implementing detailed visual components. This prevents guessing at spacing, typography, cards, navigation and mobile layouts.

## CI baseline

The repository already has GitHub Actions for PHP syntax checking and plugin packaging. These checks should remain active during the migration. Packaging currently builds an installable ZIP from the main plugin file, `includes`, `assests` and `templates`.

## Immediate next step

After the visual reference is supplied, Stage 2 should begin with a targeted foundation audit and cleanup. It should not rewrite working business modules merely to match the new design.
