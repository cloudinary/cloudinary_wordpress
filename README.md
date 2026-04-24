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

-   [Node.js](https://nodejs.org/) v16+ (see `.nvmrc`)
-   [npm](https://www.npmjs.com/) v6.9+
-   [Composer](https://getcomposer.org/)
-   [Docker](https://www.docker.com/) (required for the WordPress local environment via `wp-env`)

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

    This spins up a WordPress instance at [http://localhost:8888](http://localhost:8888) with the plugin activated and `WP_DEBUG` enabled. A loopback fix is applied automatically so REST API self-requests work inside the container.

5. **Build front-end assets:**

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

### Create a Plugin Release Package

Run `npm run package` to create the plugin release in the `/build` directory and package it as `cloudinary-image-management-and-manipulation-in-the-cloud-cdn.zip` in the root directory.

Files included in the release package are defined in the `gruntfile.js` under the `copy` task. Be sure to update this list of files and directories when you add new files to the project.

### Deployment to WordPress.org

1. Tag a release from the `master` branch on GitHub.

2. Run `npm run deploy` to deploy the version referenced in the `cloudinary.php` file of the current branch.

3. Run `npm run deploy-assets` to deploy just the WP.org plugin assets such as screenshots, icons and banners.

## License

Released under the GPL license.
