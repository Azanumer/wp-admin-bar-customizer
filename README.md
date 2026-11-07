# WP Admin Bar Customizer

Brand the WordPress admin bar — add your own links, remove the clutter, set a custom colour, and hide the bar for selected roles. No code needed; everything lives on one settings page.

## Features

- **Custom colour** — pick your brand colour; hover states are darkened automatically.
- **Remove items** — hide the WordPress logo menu, site name, comments bubble, + New menu, updates bubble, search form, My Account menu and more.
- **Custom links** — up to 20 of your own links (support page, analytics, client dashboard), top-level or nested under an existing menu node, optionally opening in a new tab.
- **Hide the bar for roles** — subscribers and customers never see the front-end bar. Administrators are never hidden, so you can always get back to the settings page.
- **Safe** — only `manage_options` users can open the page; all saves are nonce-checked and every value is sanitized (hex colours, URL-escaped links, allow-listed node IDs).

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Go to **Settings → Admin Bar**.

## Notes

- Custom link IDs are generated from the link title, so two links with the same title would collide — give each link a distinct title.
- Parent node IDs you can nest under: `site-name`, `my-account`, `new-content`, or any node ID added by another plugin.
- Uninstalling deletes the single `wabc_settings` option.

## License

MIT — see [LICENSE](LICENSE).
