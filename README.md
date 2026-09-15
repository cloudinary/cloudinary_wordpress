# Cloudinary's WordPress Plugin

Cloudinary is a cloud service that offers a solution to a web application's entire image and video management pipeline.
With Cloudinary, all your images are automatically uploaded, normalized, optimized and backed-up in the cloud instead of being hosted on your servers.

With Cloudinary, you can stop messing around with image editors. Cloudinary can manipulate and transform your images online, on-the-fly, directly from your WordPress console. Enhance your images using every possible filter and effect you can think of. All manipulations are done in the cloud using super-powerful hardware, and all resulting images are cached, optimized (smushed and more) and delivered via a lightning fast content delivery network (CDN).

## WordPress Plugin

The plugin is available for installation via WordPress plugins directory.
The plugin is publicly available at: [https://wordpress.org/plugins/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/](https://wordpress.org/plugins/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/)

This Git repository is the development repository, while there's a mirror public SVN repository of the actual released WordPress plugin version: [https://plugins.svn.wordpress.org/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/](https://plugins.svn.wordpress.org/cloudinary-image-management-and-manipulation-in-the-cloud-cdn/)

> **Deprecation Note**
> The legacy WordPress Plugin version (v1.x) will be deprecated as of **February 1st, 2021**, after which support, updates and bug fixes for the legacy plugin will continue in limited fashion.
> The legacy plugin will be made obsolete on **August 1st, 2021** (end-of-life date), meaning, Version 1.x of the plugin will no longer function after that date.
> We ask that you update to our latest WordPress Plugin v2.x before the August 1st deadline.

## Additional resources

Additional resources are available at:

-   [Website](https://cloudinary.com)
-   [Documentation](https://cloudinary.com/documentation)
-   [Knowledge Base](https://support.cloudinary.com/hc/en-us)

## Support

You can [open an issue through GitHub](https://github.com/cloudinary/cloudinary_wordpress/issues).

Contact us [https://cloudinary.com/contact](https://cloudinary.com/contact)

Stay tuned for updates, tips and tutorials: [Blog](https://cloudinary.com/blog), [Twitter](https://twitter.com/cloudinary), [Facebook](https://www.facebook.com/Cloudinary).

## Development

### Prerequisites

-   [Node.js](https://nodejs.org/) v22+ (see `.nvmrc`)
-   [npm](https://www.npmjs.com/) v10+
-   [Composer](https://getcomposer.org/)
-   [Docker](https://www.docker.com/) (required for the WordPress local environment via `wp-env`)
-   [mkcert](https://github.com/FiloSottile/mkcert) (required once, to trust the local HTTPS certificate)

### Local Development Setup

1. **Clone the repository:**

    ```bash
    git clone https://github.com/cloudinary/cloudinary_wordpress.git
    cd cloudinary_wordpress
    ```

2. **Set the correct Node version** (if using [nvm](https://github.com/nvm-sh/nvm)):

    ```bash
    nvm install
    nvm use
    ```

3. **Install dependencies:**

    ```bash
    npm install
    ```

    This will also run `composer install` automatically via the `postinstall` script, setting up PHP dependencies and linting tools.

4. **Start the local WordPress environment:**

    Make sure Docker is running, then:

    ```bash
    npm run env:start
    ```

    This spins up a WordPress instance at [https://cloudinary.local.wpenv.net](https://cloudinary.local.wpenv.net) with the plugin activated and `WP_DEBUG` enabled, plus a tests instance at [https://tests.cloudinary.local.wpenv.net](https://tests.cloudinary.local.wpenv.net).

    The site is served over HTTPS by an nginx proxy that `npm run env:start` brings up alongside wp-env. The environment runs over TLS by default because several code paths behave differently under HTTPS: `is_ssl()` decides the delivery URL scheme, auth cookies only get the `Secure` flag on HTTPS, and the admin enforces `FORCE_SSL_ADMIN`. Testing on plain HTTP hides those differences until production.

    Loopback REST API self-requests are configured automatically, and they verify the certificate rather than skipping the check, so they exercise the same code path as production.

5. **Trust the local certificate (first run only):**

    ```bash
    npm run env:install-cert
    ```

    This adds the locally generated certificate authority to your OS trust store, so the browser accepts `*.cloudinary.local.wpenv.net` without a warning. It asks for your password, because changing the system trust store requires it. The certificate and its CA are created on first `npm run env:start` and live in `.wp-env/certs/`, which is gitignored.

    No `/etc/hosts` entry is needed: `local.wpenv.net` and all of its subdomains resolve to `127.0.0.1` over public DNS.

6. **Build front-end assets:**

    ```bash
    npm run build        # One-time production build
    npm run dev          # Watch mode for development
    ```

### Useful Commands

| Command                | Description                              |
| ---------------------- | ---------------------------------------- |
| `npm run env:start`    | Start the local WordPress environment    |
| `npm run env:stop`     | Stop the local WordPress environment     |
| `npm run env:destroy`  | Remove the local environment completely  |
| `npm run env:install-cert` | Trust the local HTTPS certificate (once) |
| `npm run env:proxy:up` | Start the HTTPS proxy on its own         |
| `npm run env:proxy:down` | Stop the HTTPS proxy                   |
| `npm run env:logs`     | View container logs                      |
| `npm run env:cli`      | Run WP-CLI commands inside the container |
| `npm run env:clean`    | Reset the environment (removes all data) |
| `npm run build`        | Build front-end assets for production    |
| `npm run dev`          | Build front-end assets in watch mode     |
| `npm run lint`         | Run all linters (PHP, JS, CSS)           |
| `npm run lint:php`     | Run PHP CodeSniffer                      |
| `npm run lint:php:fix` | Auto-fix PHP linting issues              |
| `npm run lint:js`      | Run ESLint on JavaScript files           |
| `npm run lint:js:fix`  | Auto-fix JS linting issues               |
| `npm run lint:style`   | Run stylelint on SCSS files              |
| `npm run i18n`         | Generate translation files               |

### Troubleshooting the local environment

**The browser warns that the certificate is not trusted.** Run `npm run env:install-cert`. If the warning persists, delete `.wp-env/certs/`, run `npm run env:start` to reissue the certificate, then trust it again.

**`npm run env:start` reports that port 80 or 443 is in use.** Another local project holds the port. The message names the container or process. Stop it, then run `npm run env:proxy:up` to finish starting the proxy.

**`wp-env stop` leaves the proxy running.** The proxy is a separate Docker Compose project, so wp-env does not manage it. `npm run env:stop` and `npm run env:destroy` stop it for you; `npm run env:proxy:down` does it on its own.

**CI uses a different config.** GitHub runners have no certificate authority and no proxy, so the workflow passes `--config .wp-env.ci.json`, which is the same environment without the HTTPS URLs. Keep the shared values in both files in sync.

### Create a Plugin Release Package

Run `npm run package` to create the plugin release in the `/build` directory and package it as `cloudinary-image-management-and-manipulation-in-the-cloud-cdn.zip` in the root directory.

Files included in the release package are defined in the `gruntfile.js` under the `copy` task. Be sure to update this list of files and directories when you add new files to the project.

### Deployment to WordPress.org

Deployment is automated via the `Deploy to WordPress.org Repository` GitHub Actions workflow (`.github/workflows/deploy-to-wp-org.yml`):

1. Bump the version in `.version` on `master` (this is what gets checked against the release tag, and what `readme.txt`/`cloudinary.php` are stamped with during the build).

2. Create and publish a GitHub Release from `master`, with a tag matching that version (e.g. `3.3.4` or `v3.3.4`). Publishing the release triggers the workflow, which builds the plugin, deploys it to the WP.org SVN repository, and attaches the built zip to the release.

   - Marking the release as a **pre-release** runs the same workflow in dry-run mode: it builds and verifies everything but skips the actual SVN commit, which is the safe way to test a release without shipping it to WP.org.
   - The workflow can also be run manually from the Actions tab (`workflow_dispatch`) against any branch/tag, defaulting to a dry-run, to exercise the pipeline without publishing a GitHub release at all.

3. If you need to deploy from a local machine instead (e.g. as a fallback), run `npm run deploy`, which builds and runs `grunt deploy` using SVN credentials configured locally.

4. Run `npm run deploy-assets` to deploy just the WP.org plugin assets such as screenshots, icons and banners.

## End-to-end testing

E2E tests run against a wp-env site using Playwright.

### One-time setup

```bash
npm install
npx playwright install --with-deps chromium
npm run env:start
npm run env:install-cert
```

### Running the tests

```bash
npm run test:e2e
```

The suite runs against the HTTPS tests site at `https://tests.cloudinary.local.wpenv.net`. Certificates are verified rather than ignored, so a broken certificate fails the run instead of passing silently. Always start the suite through the npm scripts: they set `NODE_EXTRA_CA_CERTS`, which Playwright's Node-side request client needs because it does not read the OS trust store.

To run against a different site, set `WP_BASE_URL`. CI uses this to run over plain HTTP, because GitHub runners have no local certificate authority:

```bash
WP_BASE_URL=http://localhost:8889 npm run test:e2e
```

### Wizard test credentials

`tests/e2e/wizard-setup.spec.js` exercises the live Cloudinary connection flow, so it needs a real connection string. Provide one of two ways:

**Option 1 — `.env` file (recommended for sustained local development).** Copy `.env.example` to `.env` and fill in the value. `.env` is gitignored. Playwright loads it automatically at startup.

```bash
cp .env.example .env
# edit .env, set CLOUDINARY_E2E_URL=cloudinary://...
npm run test:e2e
```

**Option 2 — shell export (good for one-off runs and CI).**

```bash
export CLOUDINARY_E2E_URL='cloudinary://API_KEY:API_SECRET@CLOUD_NAME'
npm run test:e2e
```

A real shell env var takes precedence over the `.env` file.

The variable is intentionally named `CLOUDINARY_E2E_URL` (not `CLOUDINARY_URL`) so it cannot be confused with the Cloudinary SDK convention or with anything you might define in `.wp-env.override.json` for local dev. Use a dedicated test Cloudinary account — never production credentials.

> **Note:** Do **not** set `CLOUDINARY_URL` or `CLOUDINARY_CONNECTION_STRING` as PHP constants via `.wp-env.override.json` while running this spec. The plugin treats a constant-defined connection string as already-configured and hides the wizard's connection input, which makes the test impossible.

CI will provide `CLOUDINARY_E2E_URL` via a GitHub Actions secret (wired separately under WPP-1195's CI subtask).

### Debugging a failing e2e test

```bash
npm run test:e2e:debug -- wizard-setup
```

This opens Playwright's UI runner where you can step through actions, inspect the DOM, and view the network panel.

## License

Released under the GPL license.
