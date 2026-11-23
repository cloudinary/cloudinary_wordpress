/**
 * Sends one WP-CLI command to the bridge in ./server.mjs and prints the result.
 *
 * Run as a child process by tests/e2e/utils/wizard.js. The e2e helpers are
 * synchronous (they used `execSync( 'docker exec ...' )`), and running this
 * script through `execFileSync` keeps them that way without changing every
 * call site.
 *
 * Reads the JSON request from stdin, writes the command's stdout to stdout and
 * its stderr to stderr, and exits with the command's exit code.
 */

const http = require( 'http' );

const CLI_PORT = Number( process.env.WP_PLAYGROUND_CLI_PORT || 8890 );

let body = '';
process.stdin.on( 'data', ( chunk ) => ( body += chunk ) );
process.stdin.on( 'end', () => {
	const req = http.request(
		{
			host: '127.0.0.1',
			port: CLI_PORT,
			path: '/wp-cli',
			method: 'POST',
			headers: { 'content-type': 'application/json' },
		},
		( res ) => {
			let raw = '';
			res.on( 'data', ( chunk ) => ( raw += chunk ) );
			res.on( 'end', () => {
				const result = JSON.parse( raw );
				process.stdout.write( result.stdout );
				process.stderr.write( result.stderr );
				process.exitCode = result.exitCode;
			} );
		}
	);

	req.on( 'error', ( error ) => {
		process.stderr.write(
			`Could not reach the Playground WP-CLI bridge on port ${ CLI_PORT }. Start it with \`npm run env:playground\`.\n${ error.message }\n`
		);
		process.exitCode = 1;
	} );

	req.end( body );
} );
