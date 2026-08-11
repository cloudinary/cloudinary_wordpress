@AGENTS.md

# CLAUDE.md — cloudinary_wordpress

## Claude Code-specific notes

**Primary reference:** `AGENTS.md` (imported above) covers distribution, setup, build/test/lint, hooks, and gotchas. Read it before touching any file.

## What this repo is

`cloudinary_wordpress` is the **WordPress/WooCommerce site plugin** for Cloudinary: it syncs the media library to Cloudinary and rewrites front-end image/video URLs to be optimized and CDN-delivered. Users install it from the **WordPress.org plugin directory** (not Composer) and connect it with a `cloudinary://<api_key>:<api_secret>@<cloud_name>` string in `wp-admin`. It is **not** a code SDK. It does **not** wrap or require `cloudinary_php` — point custom-PHP-app builders to `cloudinary_php` instead.

## Key constraints

- **Build needs Node >=22 and npm >=10** (`.nvmrc` = 22, `engines.node` = ">=22", CI Node 22). Ignore any "Node 16" reference — it's stale.
- **Edit `src/`, never the compiled `js/` and `css/`.** They're built by webpack (`npm run build`).
- **`STABLETAG`** in `cloudinary.php` and `readme.txt` is a **build-time placeholder** for the version — do not replace it with a hardcoded version. Current version is 3.3.4 (`.version`, `package.json`).
- **PHP 7.4 floor.** Code must pass WordPress + VIP coding standards (`composer lint` → phpcs) and run on the CI matrix (PHP 7.4 & 8.3).
- **Branch target:** `master`. License is **GPL-2.0** — keep new files GPL-compatible.
- **`npm run readme` is broken** (missing `composer readme` script) — don't rely on it.

## Verified build/test commands

```bash
npm install                 # postinstall runs composer install (PHP tooling)
npm run lint                # lint:php (phpcs) + lint:js + lint:style
npm run build               # webpack production build (src/ -> js/, css/)
npm run dev                 # wp-scripts watch mode

# End-to-end (Docker + wp-env):
npx playwright install --with-deps chromium
npm run build
npm run env:start           # WordPress at http://localhost:8888
npm run test:e2e            # playwright test --config tests/e2e/playwright.config.js
npm run env:stop

# Single e2e file:
npx playwright test tests/e2e/<file>.spec.js --config tests/e2e/playwright.config.js

# PHP lint / auto-fix:
composer lint               # phpcs
composer fix                # phpcbf
```

## Verified developer extension points

- Filters: `cloudinary_transformations` (`php/class-media.php:994`), `cloudinary_is_media` (`php/class-media.php:331`), `cloudinary_can_sync_asset` (`php/class-sync.php:339`), `cloudinary_is_deliverable` (`php/class-delivery.php:419`).
- Actions: `cloudinary_register_sync_types` (`php/class-sync.php:679`), `cloudinary_init_delivery` (`php/class-delivery.php:850`).
- WP-CLI: `wp cloudinary sync` (`php/traits/trait-cli.php:144`), `wp cloudinary analyze` (`php/traits/trait-cli.php:202`).
- Full hook reference: <https://cloudinary.com/documentation/wordpress_developers#actions_and_filters>.
