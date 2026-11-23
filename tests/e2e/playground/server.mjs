/**
 * Boots the e2e WordPress site in WordPress Playground (PHP compiled to
 * WebAssembly), as a Docker-free alternative to wp-env.
 *
 * Two servers are started:
 *
 * - The WordPress site, on WP_PLAYGROUND_PORT (default 8889, the same port
 *   wp-env's tests site uses, so WP_BASE_URL does not change).
 * - A WP-CLI bridge on WP_PLAYGROUND_CLI_PORT (default 8890), bound to
 *   127.0.0.1. Playground has no container to `docker exec` into, so
 *   tests/e2e/utils/wizard.js sends WP-CLI commands here instead. See
 *   ./wp-cli-client.js.
 *
 * WordPress core, the PHP version and the wp-config constants are read from
 * .wp-env.ci.json so both runtimes test the same thing.
 *
 * Usage: node tests/e2e/playground/server.mjs
 */

import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const PROJECT_DIR = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'../../..'
);
const PLUGIN_SLUG = path.basename( PROJECT_DIR );
const PORT = Number( process.env.WP_PLAYGROUND_PORT || 8889 );
const CLI_PORT = Number( process.env.WP_PLAYGROUND_CLI_PORT || 8890 );
const SITE_URL = `http://localhost:${ PORT }`;
const WP_CLI_PHAR = '/tmp/wp-cli.phar';

const wpEnvConfig = JSON.parse(
	fs.readFileSync( path.join( PROJECT_DIR, '.wp-env.ci.json' ), 'utf8' )
);
const testsEnv = wpEnvConfig.env?.tests ?? {};

const startedAt = Date.now();

const server = await runCLI( {
	command: 'server',
	port: PORT,
	'site-url': SITE_URL,
	php: testsEnv.phpVersion || wpEnvConfig.phpVersion || '8.2',
	wp: wpEnvConfig.core || 'latest',
	// Each PHP worker keeps its own cached view of the mounted filesystem.
	// With @wp-playground/cli 3.1.61, a file that one worker deletes can still
	// look present to another: file_exists() is true but filesize() fails
	// with "stat failed". Specs that check local files must use 1 worker;
	// run-e2e.sh sets this per Playwright pass.
	workers: Number( process.env.WP_PLAYGROUND_WORKERS || 6 ),
	mount: [
		{
			hostPath: PROJECT_DIR,
			vfsPath: `/wordpress/wp-content/plugins/${ PLUGIN_SLUG }`,
		},
		{
			hostPath: path.join( PROJECT_DIR, '.wp-env/mu-plugins' ),
			vfsPath: '/wordpress/wp-content/mu-plugins',
		},
	],
	blueprint: {
		extraLibraries: [ 'wp-cli' ],
		steps: [
			{
				step: 'defineWpConfigConsts',
				consts: {
					...( wpEnvConfig.config || {} ),
					...( testsEnv.config || {} ),
				},
			},
			{
				step: 'activatePlugin',
				pluginPath: `/wordpress/wp-content/plugins/${ PLUGIN_SLUG }`,
			},
		],
	},
} );

/**
 * Runs one WP-CLI command inside Playground.
 *
 * Everything happens in a single PHP request, so it runs on one Playground
 * worker. That matters for `evalFile`: each worker has its own /tmp, so a file
 * written there by a separate call might not be visible to the worker that
 * runs the command.
 *
 * @param {Object}   payload
 * @param {string[]} payload.args       WP-CLI arguments after `wp`.
 * @param {string}   [payload.worker]   Playwright worker marker, see tests/e2e/fixtures.js.
 * @param {string}   [payload.evalFile] PHP source for `wp eval-file`. The file path is appended to `args`.
 * @return {Promise<{exitCode: number, stdout: string, stderr: string}>} Result.
 */
async function runWpCli( { args, worker, evalFile } ) {
	const argv = [ ...args ];
	let evalPath = '';

	if ( typeof evalFile === 'string' ) {
		evalPath = `/tmp/cld-e2e-eval-${ Date.now() }-${ Math.random()
			.toString( 36 )
			.slice( 2 ) }.php`;
		argv.push( evalPath );
	}

	// Playground runs PHP as a web SAPI, which prints warnings as HTML on
	// stdout. Under the real CLI SAPI they go to stderr as plain text, and
	// specs that JSON.parse() the output depend on that.
	const php = `<?php
ini_set( 'display_errors', 'stderr' );
ini_set( 'html_errors', '0' );
putenv( 'SHELL_PIPE=0' );
${
	worker && /^[A-Za-z0-9_-]+$/.test( worker )
		? `putenv( 'CLD_E2E_WORKER=${ worker }' );`
		: ''
}
${
	evalPath
		? `file_put_contents( ${ JSON.stringify(
				evalPath
			) }, base64_decode( ${ JSON.stringify(
				Buffer.from( evalFile ).toString( 'base64' )
			) } ) );`
		: ''
}
$GLOBALS['argv'] = array_merge(
	array( ${ JSON.stringify( WP_CLI_PHAR ) }, '--path=/wordpress' ),
	json_decode( base64_decode( ${ JSON.stringify(
		Buffer.from( JSON.stringify( argv ) ).toString( 'base64' )
	) } ), true )
);
define( 'STDIN', fopen( 'php://memory', 'rb' ) );
define( 'STDOUT', fopen( 'php://stdout', 'wb' ) );
define( 'STDERR', fopen( 'php://stderr', 'wb' ) );
require ${ JSON.stringify( WP_CLI_PHAR ) };
`;

	let response;
	try {
		response = await server.playground.run( { code: php } );
	} catch ( error ) {
		// PHP.run() throws on a non-zero exit code. The response is attached,
		// but crosses the worker boundary as plain data, without getters.
		if ( ! error.response ) {
			throw error;
		}
		response = error.response;
	}

	const stdout =
		typeof response.text === 'string'
			? response.text
			: new TextDecoder().decode( response.bytes ?? new Uint8Array() );
	const stderr = response.errors || '';

	if ( stderr ) {
		console.error( `wp ${ args.join( ' ' ) }:\n${ stderr }` );
	}

	return {
		exitCode: response.exitCode ?? 0,
		// Including the phar prints its `#!/usr/bin/env php` line first.
		stdout: stdout.replace( /^#!.*\n/, '' ),
		stderr,
	};
}

const cliServer = http.createServer( ( req, res ) => {
	if ( req.method !== 'POST' || req.url !== '/wp-cli' ) {
		res.writeHead( 404 ).end();
		return;
	}

	let body = '';
	req.on( 'data', ( chunk ) => ( body += chunk ) );
	req.on( 'end', async () => {
		try {
			const result = await runWpCli( JSON.parse( body ) );
			res.writeHead( 200, { 'content-type': 'application/json' } );
			res.end( JSON.stringify( result ) );
		} catch ( error ) {
			res.writeHead( 500, { 'content-type': 'application/json' } );
			res.end(
				JSON.stringify( {
					exitCode: 1,
					stdout: '',
					stderr: String( error?.stack || error ),
				} )
			);
		}
	} );
} );

cliServer.listen( CLI_PORT, '127.0.0.1', () => {
	console.log(
		`Playground ready in ${ Math.round(
			( Date.now() - startedAt ) / 1000
		) }s: site ${ SITE_URL }, WP-CLI bridge http://127.0.0.1:${ CLI_PORT }`
	);
} );

async function shutdown() {
	cliServer.close();
	await server[ Symbol.asyncDispose ]();
	process.exit( 0 );
}

process.on( 'SIGINT', shutdown );
process.on( 'SIGTERM', shutdown );
