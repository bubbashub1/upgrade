# BubbaHub Site Editor

A mobile-first WordPress block theme for BubbaHub. It is designed to work alongside the `BubbaHub Production` plugin rather than replace it.

## Included

- WordPress Site Editor / block theme support
- Editable header, footer and page templates
- Responsive navigation with mobile overlay
- BubbaHub green/dark/soft-green design tokens
- Responsive 1/2/3-column content grids
- Full-width compatibility for pages containing `[bubba_hub]`
- Compatible styling direction for the plugin's family hub, directory, pricing and leader experiences
- Parent/leader body classes when the plugin is active

## Install

1. Download this `bubbahub-site-editor` folder as a ZIP, or package this folder as `bubbahub-site-editor.zip`.
2. In WordPress go to **Appearance → Themes → Add New → Upload Theme**.
3. Activate **BubbaHub Site Editor**.
4. Go to **Appearance → Editor** to edit navigation, header, footer, templates and global styles.
5. Keep the BubbaHub Production plugin active.

The plugin's `[bubba_hub]`, `[bubbahub_pricing]` and `[bubbahub_signup]` shortcodes remain plugin-owned.

## Important

This branch is intentionally separate from `main` so the production plugin is not changed while the theme is tested. Merge only after testing on a staging site.
