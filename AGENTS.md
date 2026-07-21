# AGENTS.md — cloudinary_wordpress

## What this is (one line)
The official Cloudinary plugin for WordPress/WooCommerce: it syncs the WordPress media library to Cloudinary and rewrites front-end image/video URLs to be optimized and CDN-delivered. It is a **site plugin**, configured in `wp-admin`, not a code SDK.

## When to use / when NOT to use
- **Use this when:** the target is a **WordPress or WooCommerce site** that should optimize and deliver media through Cloudinary. Installed by end users from the **WordPress.org plugin directory**; connected with a `cloudinary://<api_key>:<api_secret>@<cloud_name>` string in the admin UI. No application code needed for basic use.
- **Do NOT use this when:** you're building a **custom PHP application** and want to call Cloudinary in code — use [`cloudinary_php`](https://github.com/cloudinary/cloudinary_php). This plugin does **not** wrap or require the PHP SDK; it builds delivery URLs and calls the Cloudinary API itself (only runtime PHP dep is `ext-json`).
- **Siblings:** other platform integrations are `cloudinary_magento2`, `cloudinary_sap_commerce`, `cloudinary_sfcc_site_cartridge`, `cloudinary_commercetools`. For an agent/no-code path see [Cloudinary MCP servers](https://github.com/cloudinary/mcp-servers).

## Distribution & versions
- **Distribution channel:** WordPress.org plugin directory. Slug: `cloudinary-image-management-and-manipulation-in-the-cloud-cdn`. **Not** published to Composer/Packagist for site use.
- **Current version:** 3.3.4 (source of truth: `.version` and `package.json`). In `cloudinary.php` and `readme.txt` the version reads `STABLETAG` — that's a **build-time placeholder** replaced at release. Do not hand-set it.
- **Runtime requirements:** WordPress 5.6+ (tested to 7.0), PHP 7.4+. `cloudinary.php` guards on `version_compare(phpversion(),'7.4','>=')` before loading.

## Setup for contributors
This is a WordPress plugin built with `@wordpress/scripts` (webpack). **Build toolchain needs Node >=22 and npm >=10** (`.nvmrc` = 22, `engines.node` = ">=22", CI uses Node 22 — the old README's "Node v16" is stale).

```bash
nvm use            # Node 22 (per .nvmrc)
npm install        # installs JS deps; postinstall runs `composer install` for PHP tooling
npm run build      # compile src/ -> js/ and css/ via wp-scripts (webpack)
npm run dev        # wp-scripts start (watch mode)
```

For end-to-end tests, the repo uses `@wordpress/env` (Docker):
```bash
npm run env:start  # boots WordPress at http://localhost:8888 (wp-env, needs Docker)
npm run env:stop
```

## Minimal developer hook example
Extension is via WordPress filters/actions from a theme/plugin, not by editing this repo. Verified hook:
```php
// Add a global transformation to every delivered image (php/class-media.php:994).
add_filter( 'cloudinary_transformations', function ( $transformations, $attachment_id ) {
	$transformations[] = array( 'effect' => 'sharpen:80' );
	return $transformations;
}, 10, 2 );
```
Other verified hooks: `cloudinary_is_media` (`php/class-media.php:331`), `cloudinary_can_sync_asset` (`php/class-sync.php:339`), `cloudinary_is_deliverable` (`php/class-delivery.php:419`), action `cloudinary_register_sync_types` (`php/class-sync.php:679`), action `cloudinary_init_delivery` (`php/class-delivery.php:850`). Full list: <https://cloudinary.com/documentation/wordpress_developers#actions_and_filters>.

WP-CLI (registered in `instance.php` as the `cloudinary` namespace): `wp cloudinary sync` (`php/traits/trait-cli.php:144`), `wp cloudinary analyze` (`php/traits/trait-cli.php:202`).

## Build / test / lint (from CI: .github/workflows/ci.yml)
```bash
npm ci                                  # clean install (CI)
npm run lint                            # runs lint:php (phpcs), lint:js (wp-scripts), lint:style
npm run build                           # webpack production build

# End-to-end (Playwright on wp-env, Docker required):
npx playwright install --with-deps chromium
npm run build
npm run env:start
npm run test:e2e                        # playwright test --config tests/e2e/playwright.config.js
npm run env:stop

# Single e2e test file:
npx playwright test tests/e2e/<file>.spec.js --config tests/e2e/playwright.config.js

# PHP-only lint / auto-fix (Composer scripts):
composer lint                           # phpcs (WordPress/VIP coding standards)
composer fix                            # phpcbf
```
CI matrix: PHP **7.4 and 8.3**, Node **22**. The `build` job runs `npm ci` → `npm run lint` → `npm run build`; the `e2e` job builds, boots wp-env, and runs Playwright (needs the `CLOUDINARY_E2E_URL` secret for live-cloud e2e).

## Conventions & gotchas
- **Edit `src/`, not the compiled output.** JS/CSS in `js/` and `css/` are built from `src/` by webpack — never hand-edit the compiled files; run `npm run build`.
- **`STABLETAG` is a build-time placeholder** for the version in `cloudinary.php` and `readme.txt` (replaced by grunt-text-replace / release-it at release). Don't hardcode a version there.
- PHP lives in namespaced `Cloudinary\` classes under `php/`; bootstrap is `cloudinary.php` → `instance.php` → `new Cloudinary\Plugin()`.
- PHP must pass **WordPress + VIP coding standards** (phpcs via `composer lint`) and stay compatible with **PHP 7.4**.
- Release tooling (Grunt `grunt-wp-deploy` to the WP.org SVN, `release-it`) is maintainer-only — don't run `npm run deploy`.
- **License: GPL-2.0** — keep new files GPL-compatible.
- **`npm run readme` is currently broken** (aliases a `composer readme` script that isn't defined). Don't rely on it.

## Canonical docs
- WordPress integration guide: https://cloudinary.com/documentation/wordpress_integration
- Developer hooks (actions & filters): https://cloudinary.com/documentation/wordpress_developers#actions_and_filters
- Plugin listing: https://wordpress.org/plugins/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/
- Transformation & API references: https://cloudinary.com/documentation/cloudinary_references

## Agent / MCP note
For autonomous Cloudinary operations outside WordPress, prefer the [Cloudinary MCP servers](https://github.com/cloudinary/mcp-servers). Use this repo for changes to the WordPress plugin itself.

## Commit / PR conventions
- Branch off and PR against `master`. Keep the CI matrix green (PHP 7.4 & 8.3, Node 22).
- Run `npm run lint` and `npm run build` before pushing; add/adjust Playwright e2e coverage for behavior changes.
- Edit `src/`; never commit hand-edited `js/`/`css/` build output or a hardcoded version in place of `STABLETAG`.
