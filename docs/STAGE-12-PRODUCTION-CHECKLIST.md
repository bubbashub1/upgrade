# Stage 12 — Production readiness checklist

## Automated checks

- PHP 8.2 syntax lint passes for every plugin PHP file.
- Stage 8 import static check is version-tolerant and validates the importer contract.
- CI must be green before merging to `main`.

## Runtime smoke tests

- Activate the plugin on a staging WordPress site.
- Confirm activation does not create demo/sample listings.
- Confirm existing `bh_group` records remain intact.
- Confirm existing WordPress pages are not overwritten.
- Confirm the BubbaHub navigation resolves to real WordPress pages.
- Confirm listing search, filters, pagination and radius search work with real records.
- Confirm OpenStreetMap renders when valid coordinates exist.
- Confirm `lat`, `long`, `latitude`, `longitude`, `lng` aliases are handled consistently.
- Confirm a leader can edit only permitted listings.
- Confirm an administrator/manager can edit managed listings.
- Confirm REST endpoints reject unauthenticated writes.
- Confirm Google import/sync requires the required capability and nonce/authentication.
- Confirm no Google Sheet demo accounts, sample revenue, bookings or profile data are exposed.
- Confirm audit records are created for permitted listing changes/deletions.

## Figma/live-data rule

The Figma export is treated as presentation and interaction reference only. Its hard-coded sample users, groups, bookings, messages, schools, venues and financial figures must never be seeded into production.

## Deployment rule

Do not merge or install on the live BubbaHub site until the CI workflow is green and the runtime smoke tests above have been completed on staging with a database backup.

## Release

- Keep `main` stable.
- Merge the staged PR only after CI is green and staging checks pass.
- Package the plugin from the approved `main` commit.
- Record the release commit SHA and plugin version.
