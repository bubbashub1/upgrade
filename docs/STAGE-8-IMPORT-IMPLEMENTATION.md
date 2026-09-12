# Stage 8 implementation

`BubbaHubGoogleImport` is loaded by the main plugin. It exposes an authenticated admin action and imports JSON rows from the configured Google endpoints. It supports Google ID and organiser username matching and writes recognised fields into `bh_group` metadata.