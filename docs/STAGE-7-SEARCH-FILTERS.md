# Stage 7 — Directory Search, Filters and Radius

## What was added

- Server-side REST search endpoint: `/wp-json/bubbahub/v1/directory/search`
- Server-side pagination (12 per page by default, maximum 48)
- Keyword search across listing title/content and common directory fields
- Town/city, postcode and wider area/location filters
- Category, age range, price and day filters
- Featured-only filter
- Newest, oldest and A–Z sorting
- Optional browser geolocation for radius searching
- 5, 10, 15, 25 and 50 mile radius choices
- Bounding-box pre-filter plus Haversine distance calculation using stored latitude/longitude
- Distance returned in miles and nearest-first ordering when radius search is active
- Responsive `[bubbahub_directory]` shortcode UI
- Loading, empty-result and temporary-error states

## Data rules

`bh_group` WordPress posts remain the source of truth. No demo records are created. Radius searches only use listings with valid stored coordinates; coordinates are never guessed in the search layer.

The canonical listing layer already accepts `lat`/`latitude` and `long`/`lng`/`longitude` aliases. Stage 6 OpenStreetMap/Nominatim tooling can populate those values.

## Usage

The directory page can use:

`[bubbahub_directory]`

The REST endpoint is public for published listings and accepts query parameters such as `q`, `city`, `postcode`, `age`, `day`, `price`, `featured`, `lat`, `lng`, `radius`, `page`, `per_page` and `sort`.

## Free-first architecture

No paid search or maps API is required. Browser geolocation is native, WordPress REST handles transport, and stored coordinates are used for distance calculations.

## Testing notes

Test on staging with real listings before deployment. In particular verify:

1. Empty database returns a clean empty state.
2. Keyword, town, postcode, age, price and day filters return expected records.
3. Radius searches exclude listings without coordinates.
4. 5–50 mile radius results are ordered by calculated distance.
5. Pagination does not load the entire directory into the browser.
6. Existing Stage 5 editing and Stage 6 map functionality remain unaffected.
