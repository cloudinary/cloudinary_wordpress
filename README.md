# Cloudinary plugin for WordPress

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/cloudinary-image-management-and-manipulation-in-the-cloud-cdn.svg)](https://wordpress.org/plugins/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/)
[![License: GPL v2](https://img.shields.io/badge/license-GPL--2.0-blue.svg)](./LICENSE)
[![CI](https://github.com/cloudinary/cloudinary_wordpress/actions/workflows/ci.yml/badge.svg)](https://github.com/cloudinary/cloudinary_wordpress/actions/workflows/ci.yml)

The Cloudinary plugin for WordPress syncs the WordPress media library to Cloudinary and rewrites the front-end image and video URLs a theme outputs so they deliver optimized, responsively sized, and CDN-served — no template edits. It's a site plugin configured in `wp-admin`, not a code SDK; it installs from the WordPress.org plugin directory (slug `cloudinary-image-management-and-manipulation-in-the-cloud-cdn`) and runs on WordPress 5.6+ (tested to 7.0) and PHP 7.4+.

## Installation

Install it from the WordPress.org plugin directory, not Composer:

1. In `wp-admin`, go to **Plugins > Add New**.
2. Search for **Cloudinary**.
3. On *Cloudinary - Deliver Images and Videos at Scale*, click **Install Now**, then **Activate**.

Or upload the ZIP from the [plugin page](https://wordpress.org/plugins/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/) via **Plugins > Add New > Upload Plugin**. The `composer.json` in this repo is for local development tooling only — don't `composer require` the plugin into a site.

## Configuration

The plugin holds the API secret server-side (in the WordPress database), so there's no client-side config and no env var to export. Connect it with a single connection string in the admin UI:

1. In the [Cloudinary Console](https://console.cloudinary.com/console), copy the **API environment variable** for your product environment. Its format is:

   ```
   cloudinary://<API_KEY>:<API_SECRET>@<CLOUD_NAME>
   ```

2. In `wp-admin`, open the **Cloudinary** menu (the setup wizard opens on first activation).
3. Paste the connection string into the connection field and save.

Cloudinary verifies the credentials, then the Image, Video, Lazy Loading, and Sync settings pages become available. The API secret stays in the WordPress database on the server — it's never exposed on the front end. Keep it out of client-side code and version control.

## Quick examples

The plugin is configured through the admin UI; its behavior is extended from a theme or plugin through WordPress filters and WP-CLI. Add the PHP snippets to your theme's `functions.php`. Each hook name and line reference below is verified against the plugin source.

### Add a global transformation to every delivered image

The `cloudinary_transformations` filter (`php/class-media.php:994`) receives the transformation array Cloudinary applies to an asset and the WordPress attachment ID. Append a step to change how every image is delivered:

```php
<?php
// In your theme's functions.php.
add_filter(
	'cloudinary_transformations',
	function ( $transformations, $attachment_id ) {
		// Sharpen every delivered image.
		$transformations[] = array( 'effect' => 'sharpen:80' );
		return $transformations;
	},
	10,
	2
);
```

### Keep specific assets out of Cloudinary

The `cloudinary_can_sync_asset` filter (`php/class-sync.php:339`) receives whether an asset may sync, its attachment ID, and its sync type. Return `false` to keep an asset on the WordPress host:

```php
<?php
// In your theme's functions.php.
add_filter(
	'cloudinary_can_sync_asset',
	function ( $can, $attachment_id, $type ) {
		// Don't push PDFs to Cloudinary.
		if ( 'application/pdf' === get_post_mime_type( $attachment_id ) ) {
			return false;
		}
		return $can;
	},
	10,
	3
);
```

### Bulk-sync and inspect the media library with WP-CLI

The plugin registers a `wp cloudinary` command namespace. `sync` (`php/traits/trait-cli.php:144`) pushes all eligible media library assets to Cloudinary in one pass; `analyze` (`php/traits/trait-cli.php:202`) reports each asset's sync state. Run them over SSH or in a deploy script:

```bash
# Push the whole existing library to Cloudinary.
wp cloudinary sync

# Report what's synced, pending, or errored.
wp cloudinary analyze
```

## For AI agents

`cloudinary_wordpress` is the WordPress/WooCommerce site plugin: it installs from the WordPress.org directory, connects via a `cloudinary://<API_KEY>:<API_SECRET>@<CLOUD_NAME>` string in `wp-admin`, and needs no application code for basic use. It does not wrap or require `cloudinary_php`. For other platforms and tasks, route to a different package:

| Task | Use instead |
|---|---|
| Call Cloudinary from a custom PHP application | [`cloudinary_php`](https://github.com/cloudinary/cloudinary_php) |
| A Magento 2 / Adobe Commerce store | [`cloudinary_magento2`](https://github.com/cloudinary/cloudinary_magento2) |
| SAP Commerce Cloud | [`cloudinary_sap_commerce`](https://github.com/cloudinary/cloudinary_sap_commerce) |
| Salesforce Commerce Cloud | [`cloudinary_sfcc_site_cartridge`](https://github.com/cloudinary/cloudinary_sfcc_site_cartridge) |
| commercetools | [`cloudinary_commercetools`](https://github.com/cloudinary/cloudinary_commercetools) |
| Run Cloudinary operations as agent tools | [Cloudinary MCP servers](https://github.com/cloudinary/mcp-servers) |

## Links

- [WordPress integration guide](https://cloudinary.com/documentation/wordpress_integration)
- [Developer hooks (actions and filters)](https://cloudinary.com/documentation/wordpress_developers#actions_and_filters)
- [Transformation and API references](https://cloudinary.com/documentation/cloudinary_references)
- [Documentation llms.txt index](https://cloudinary.com/documentation/llms.txt)
- [Plugin on WordPress.org](https://wordpress.org/plugins/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/)

Released under the GPL-2.0 license.
