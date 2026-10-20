/**
 * Helpers for the specs tagged @live, which need a real Cloudinary
 * connection and run in parallel workers against one WordPress site.
 *
 * The steps that act on site-wide state have to take turns, so they run
 * under a cross-worker lock:
 *
 * - `wp cloudinary sync` is not scoped to one attachment. Its analyze step
 *   clears and re-adds the `_cld_unsynced` flag on every attachment, so a
 *   second sync that queries in that window finds nothing to sync.
 * - The gallery spec syncs through `/cloudinary/v1/image_data`, which would
 *   otherwise race a concurrent CLI sync for the same attachments.
 * - Deleting an attachment while another worker's sync iterates over it.
 * - Settings writes, which read and rewrite a whole option array.
 *
 * All Playwright workers run on the same host, so an atomic `mkdir` in the
 * project's artifacts directory is enough. The lock records the owner PID so
 * a worker that dies while holding it (for example on a test timeout) does
 * not block the rest of the run.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const { ensureCloudinaryConnected } = require( './connection' );
const { wpCli } = require( './wizard' );

const LOCK_DIR = path.join( process.cwd(), 'artifacts', '.site-lock' );
const OWNER_FILE = path.join( LOCK_DIR, 'owner' );
const WAIT_TIMEOUT_MS = 120_000;
const POLL_MS = 100;

const sleeper = new Int32Array( new SharedArrayBuffer( 4 ) );

/**
 * Block the current worker for a short time.
 *
 * The callers already block the event loop on `execSync`, so a synchronous
 * wait keeps the lock usable from both sync and async code.
 *
 * @param {number} ms Milliseconds.
 */
function sleep( ms ) {
	Atomics.wait( sleeper, 0, 0, ms );
}

/**
 * Whether the process that holds the lock is gone.
 *
 * @return {boolean} True if the lock can be taken over.
 */
function isStale() {
	let pid;
	try {
		pid = Number( fs.readFileSync( OWNER_FILE, 'utf8' ) );
	} catch ( e ) {
		// The owner file is written right after mkdir; give the owner a
		// moment instead of treating the gap as a dead process.
		return false;
	}
	if ( ! pid ) {
		return false;
	}
	try {
		process.kill( pid, 0 );
		return false;
	} catch ( e ) {
		return 'ESRCH' === e.code;
	}
}

/**
 * Take the lock, waiting for another worker to release it if needed.
 *
 * @throws If the lock is not free within WAIT_TIMEOUT_MS.
 */
function acquire() {
	fs.mkdirSync( path.dirname( LOCK_DIR ), { recursive: true } );
	const deadline = Date.now() + WAIT_TIMEOUT_MS;

	for (;;) {
		try {
			fs.mkdirSync( LOCK_DIR );
			fs.writeFileSync( OWNER_FILE, String( process.pid ) );
			return;
		} catch ( e ) {
			if ( 'EEXIST' !== e.code ) {
				throw e;
			}
		}

		if ( isStale() ) {
			release();
			continue;
		}
		if ( Date.now() > deadline ) {
			throw new Error(
				`Timed out after ${ WAIT_TIMEOUT_MS }ms waiting for ${ LOCK_DIR }.`
			);
		}
		sleep( POLL_MS );
	}
}

/**
 * Release the lock.
 */
function release() {
	fs.rmSync( LOCK_DIR, { recursive: true, force: true } );
}

/**
 * Run a callback while holding the lock.
 *
 * Accepts sync and async callbacks; for an async callback the lock is held
 * until its promise settles.
 *
 * @template T
 * @param {() => T} fn Callback.
 * @return {T} The callback's return value.
 */
function withSiteLock( fn ) {
	acquire();
	let result;
	try {
		result = fn();
	} catch ( e ) {
		release();
		throw e;
	}
	if ( result && 'function' === typeof result.then ) {
		return result.finally( release );
	}
	release();
	return result;
}

/**
 * Connect the plugin with the real credentials, under the lock.
 *
 * Every @live spec writes the same URL, so after the first write the
 * plugin's `verify_connection()` sees no change and skips the API call.
 *
 * @return {{ cloudName: string }} The cloud name from CLOUDINARY_E2E_URL.
 */
function connectLive() {
	return withSiteLock( ensureCloudinaryConnected );
}

/**
 * Run `wp cloudinary sync` under the lock.
 */
function syncLive() {
	withSiteLock( () => wpCli( [ 'cloudinary', 'sync' ] ) );
}

/**
 * Force-delete posts and attachments under the lock. Failures are logged,
 * not thrown, because cleanup is best effort.
 *
 * WP-CLI rather than REST: this wp-env runs without pretty permalinks,
 * which makes `force=true` on a `?rest_route=` URL fragile.
 *
 * @param {number[]} ids Post or attachment IDs.
 */
function deletePostsLive( ids ) {
	withSiteLock( () => {
		for ( const id of ids ) {
			try {
				wpCli( [ 'post', 'delete', String( id ), '--force' ] );
			} catch ( e ) {
				console.warn( `Cleanup of post ${ id } failed:`, e.message );
			}
		}
	} );
}

module.exports = {
	withSiteLock,
	connectLive,
	syncLive,
	deletePostsLive,
};
