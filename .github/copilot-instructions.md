# Copilot instructions — cloudinary_wordpress

This is the official Cloudinary plugin for WordPress/WooCommerce (a site plugin installed from the WordPress.org directory, not a code SDK).

**See [`AGENTS.md`](../AGENTS.md) at the repo root for the canonical instructions** — distribution/versions, contributor setup (Node >=22, `npm install`, `npm run build`, wp-env for e2e), build/test/lint commands, developer hooks, and conventions.

Key reminders:
- Edit `src/` — never the compiled `js/` and `css/` (built by webpack via `npm run build`).
- `STABLETAG` in `cloudinary.php`/`readme.txt` is a build-time version placeholder — don't hardcode a version.
- PHP must pass `composer lint` (WordPress + VIP standards) and run on PHP 7.4+. License is GPL-2.0. PR against `master`.
