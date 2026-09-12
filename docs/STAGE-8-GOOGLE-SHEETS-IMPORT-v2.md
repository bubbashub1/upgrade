# Stage 8 — Google Sheets import

Controlled manual import of real Google Sheets data into the WordPress `bh_group` directory.

Matching uses Google ID first and organiser `wp_username` second. Existing listings are updated, new listings are created, and listings absent from the Google response are not deleted. No demo data is generated.

The importer recognises the established BubbaHub listing fields, including address, city, region, postcode, latitude, longitude, timetable, business hours, website, email, social links, price, age range, category, tags, featured and term-time data.

Use **BubbaHub > Google Sheets Sync** to confirm the configured API endpoints, then **BubbaHub > Import Google Sheets** to run an import. Stage 9 will build on this with robust automated two-way sync and conflict handling.
