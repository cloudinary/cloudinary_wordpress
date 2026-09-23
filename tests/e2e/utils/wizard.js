/**
 * Helpers for the Cloudinary wizard e2e spec.
 *
 * State changes (deleting the connection options) bypass the WP REST
 * API so they don't trigger `pre_update_option_cloudinary_connect`,
 * which would make a live Cloudinary API call. Direct DB access via
 * docker + wp-cli is the right tool here.
 *
 * We use `docker exec` rather than `npx wp-env run cli` because the
 * latter routes through `got` → `api.wordpress.org` at startup and
 * times out on macOS due to an IPv6 resolution issue. `docker exec`
 * goes straight to the running container.
 */

const { execSync } = require( 'child_process' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );

const CONNECT_OPTION = 'cloudinary_connect';
const SIGNATURE_OPTION = 'cloudinary_connection_signature';
const STATUS_OPTION = 'cloudinary_status';

let cachedCliContainer = null;

/**
 * Find the wp-env CLI container name dynamically.
 *
 * Playwright drives the `tests-wordpress` site (port 8889) by default,
 * so we target the matching `*-tests-cli-1` container. The container
 * name embeds a project hash that varies between machines; we discover
 * it by listing running containers and filtering for the suffix.
 *
 * @return {string} Container name.
 * @throws If no matching container is running.
 */
function getCliContainer() {
	if ( cachedCliContainer ) {
		return cachedCliContainer;
	}

	const out = execSync( "docker ps --format '{{.Names}}'", {
		encoding: 'utf8',
	} );
	const lines = out.split( '\n' ).filter( Boolean );

	const cli = lines.find( ( name ) => /-tests-cli-1$/.test( name ) );

	if ( ! cli ) {
		throw new Error(
			'Could not find a running wp-env tests-cli container. Run `docker ps` and confirm a `*-tests-cli-1` container is up.'
		);
	}

	cachedCliContainer = cli;
	return cli;
}

/**
 * `docker exec` flags that forward the per-worker e2e marker (set by
 * `tests/e2e/fixtures.js`) into the container, so the analytics-capture
 * mu-plugin's WP-CLI command reads and clears this worker's own log rather
 * than a log shared with the other parallel workers.
 *
 * @return {string[]} Zero or more `-e KEY=VALUE` arguments.
 */
function workerEnvFlags() {
	const marker = process.env.CLD_E2E_WORKER;
	if ( ! marker || ! /^[A-Za-z0-9_-]+$/.test( marker ) ) {
		return [];
	}
	return [ '-e', `CLD_E2E_WORKER=${ marker }` ];
}

/**
 * Run a WP-CLI command inside the wp-env cli container.
 *
 * @param {string[]} args wp-cli arguments after the leading `wp`.
 * @return {string}        stdout, trimmed.
 */
function wpCli( args ) {
	const container = getCliContainer();
	const cmd = [
		'docker',
		'exec',
		...workerEnvFlags(),
		container,
		'wp',
		...args,
		'--allow-root',
	].join( ' ' );

	return execSync( cmd, {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} ).trim();
}

/**
 * Runs PHP via `wp eval-file` inside the wp-env cli container.
 *
 * Unlike `wp eval`, `eval-file` reliably sees plugin-defined globals/functions
 * such as `get_plugin_instance()` — under plain `wp eval` that call fails with
 * "Call to undefined function", for reasons not fully tracked down. Route any
 * snippet that touches the plugin instance through here instead.
 *
 * @param {string} phpCode PHP statements (no `<?php` tag).
 * @return {string} stdout, trimmed.
 */
function wpEvalFile( phpCode ) {
	const container = getCliContainer();
	const localPath = path.join(
		os.tmpdir(),
		`cld-e2e-eval-${ Date.now() }-${ Math.random()
			.toString( 36 )
			.slice( 2 ) }.php`
	);
	const remotePath = `/tmp/${ path.basename( localPath ) }`;

	// `get_plugin_instance()` and friends live in the Cloudinary namespace;
	// an unqualified call from the global namespace (the default for a
	// plain eval'd file) fails with "Call to undefined function".
	fs.writeFileSync(
		localPath,
		`<?php\nnamespace Cloudinary;\n${ phpCode }\n`
	);
	try {
		execSync( `docker cp ${ localPath } ${ container }:${ remotePath }`, {
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} );
		return execSync(
			[
				'docker',
				'exec',
				...workerEnvFlags(),
				container,
				'wp',
				'eval-file',
				remotePath,
				'--allow-root',
			].join( ' ' ),
			{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] }
		).trim();
	} finally {
		fs.unlinkSync( localPath );
		execSync( `docker exec ${ container } rm -f ${ remotePath }`, {
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} );
	}
}

/**
 * Wipe any existing Cloudinary connection so the wizard reappears.
 *
 * Each option may or may not exist. We attempt all three and
 * silently swallow "Could not get/delete option" errors.
 */
function resetCloudinaryConnection() {
	for ( const opt of [ CONNECT_OPTION, SIGNATURE_OPTION, STATUS_OPTION ] ) {
		try {
			wpCli( [ 'option', 'delete', opt ] );
		} catch ( e ) {
			// Option not present; that's fine.
		}
	}
}

/**
 * Read the Cloudinary connection string from the environment.
 *
 * We use a dedicated `CLOUDINARY_E2E_URL` env var rather than the
 * Cloudinary SDK's conventional `CLOUDINARY_URL` to make it explicit
 * that this is test-only credentials and to avoid colliding with any
 * SDK auto-bootstrap behaviour developers may rely on locally.
 *
 * Throws if not set so the test fails loudly rather than silently
 * producing a meaningless pass/fail.
 *
 * @return {string} The cloudinary:// URL.
 */
function getCloudinaryUrlFromEnv() {
	const url = process.env.CLOUDINARY_E2E_URL;
	if ( ! url || ! url.startsWith( 'cloudinary://' ) ) {
		throw new Error(
			'CLOUDINARY_E2E_URL env var must be set to a valid cloudinary:// connection string before running the wizard e2e spec.'
		);
	}
	return url;
}

/**
 * Navigate the admin browser to the wizard screen.
 *
 * We hit the wizard URL directly so the test does not depend on the
 * "not connected → wizard" redirect.
 *
 * @param {Object} admin Admin fixture from @wordpress/e2e-test-utils-playwright.
 */
async function visitWizard( admin ) {
	await admin.visitAdminPage( 'admin.php', 'page=cloudinary&section=wizard' );
}

module.exports = {
	getCliContainer,
	getCloudinaryUrlFromEnv,
	resetCloudinaryConnection,
	visitWizard,
	wpCli,
	wpEvalFile,
};
