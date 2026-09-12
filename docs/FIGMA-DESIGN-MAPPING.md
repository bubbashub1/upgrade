# Figma Design Mapping

Source supplied by Bubba Hub: `Convert Web App to Mobile.zip` from the Figma Make export.

## Design direction

The Figma application is a responsive family/community hub with a light, warm interface and a dark teal application shell. The UI uses rounded cards, compact controls, soft borders, restrained shadows and a mobile-first navigation pattern.

Primary visual tokens observed in the supplied source:

- Deep teal: `#1e3330` / `#1e3330` family for headings, navigation and strong surfaces.
- Brand teal: `#18b97a` / teal family for active controls and positive actions.
- Pale mint: `#e3f5ee` / teal-50 family for active navigation and soft backgrounds.
- Warm cream: `#faedcd` / amber family for membership/status accents.
- Warm orange: `#bc6c25` for selected badges/accent states.
- Page background: `#f4f6f4` / pale teal-tinted neutral.
- Borders: `#e4edea` / `#e6ebe9`.
- Typography uses a clean sans-serif UI with Fraunces used for prominent editorial headings.
- Cards generally use large rounded corners, compact spacing and subtle shadows.

## Navigation from the actual Figma source

The supplied Figma `App.tsx` defines the primary navigation as:

1. What's On — directory/discovery
2. Bubba Buddy — parent connections
3. My Hub — family dashboard
4. Support — support and guidance
5. About Us — organisation information
6. Leader Portal — provider management, conditional to leader access

This is the canonical visual navigation for the WordPress implementation. Earlier placeholder navigation such as separate Directory, Compare, Antenatal and Membership items should not replace these primary Figma tabs. Those features can remain available inside the appropriate sections/account areas.

## Figma functional sections to map into WordPress

### What's On

Use the real `bh_group` listings and Stage 7 server-side search/filtering. The Figma search interface contains keyword, category, region, city, postcode/radius, age range and session-length controls. The WordPress implementation must use live records rather than the Figma's in-memory sample records.

### Bubba Buddy

The Figma contains parent connection cards, connection search, privacy messaging and messaging controls. The WordPress version must use real logged-in user/connection data. No sample parents, avatars or conversations should be seeded.

### My Hub

The Figma contains children/family information, saved groups, visited groups, suggested groups, bookings, school/nursery information and an update ticker. These must be populated from WordPress data. Do not carry across the sample users, children, bookings, schools or groups from the Figma source.

### Support

Map the Figma support/guidance presentation to the existing WordPress support content and keep the design consistent.

### About Us

Use the existing Bubba Hub About Us content rather than the Figma's generic/demo copy.

### Leader Portal

The Figma contains provider dashboard, listings, bookings, tickets, venues, Q&A, support, profile/storefront and settings concepts. WordPress must populate these from the existing leader/account/booking systems. Do not display the Figma's sample leader, company, revenue, booking or venue data.

## Data rule

The Figma source is a **design and interaction reference**, not a data source.

All sample values found in the Figma export must be treated as placeholders and must not be imported into WordPress.

WordPress is the production data source. Google Sheets remains an optional synchronisation source. OpenStreetMap/Leaflet remains the mapping layer.

## Responsive behaviour

Preserve the Figma mobile approach:

- compact top navigation
- mobile menu button
- stacked cards
- touch-friendly controls
- filters that collapse/expand
- maps that remain usable on small screens
- no fixed-width desktop-only layouts

## Implementation rule

Port the Figma structure and visual language into WordPress PHP/HTML/CSS/JavaScript. Do not ship the React/Vite Figma application as the production WordPress runtime and do not require Base44/Figma Make for the finished plugin.
