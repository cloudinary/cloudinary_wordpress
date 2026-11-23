#!/bin/bash
# Run the e2e suite against WordPress Playground instead of wp-env.
#
# Starts tests/e2e/playground/server.mjs in the background, waits until its
# WP-CLI bridge answers, runs Playwright, then stops the server. The
# Playground log is written to artifacts/playground.log.
#
# Without arguments, runs the passes of `npm run test:e2e`, each against a
# fresh site:
#
# - The parallel pass, 6 PHP workers.
# - The @serial pass without the storage-modes spec, 6 PHP workers.
# - The storage-modes spec, 1 PHP worker. It checks local files after a
#   sync, and with more than one worker a deleted file can still look present
#   to another worker (see the `workers` comment in server.mjs). Other specs
#   stay on 6 workers, because with 1 the block editor saves too slowly on CI
#   runners.
#
# With arguments, starts one site with 1 PHP worker and passes the arguments
# to Playwright, e.g. a single spec file.
#
# Usage: tests/e2e/playground/run-e2e.sh [playwright args]

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$PROJECT_DIR"

export WP_E2E_RUNTIME=playground
export WP_PLAYGROUND_PORT="${WP_PLAYGROUND_PORT:-8889}"
export WP_PLAYGROUND_CLI_PORT="${WP_PLAYGROUND_CLI_PORT:-8890}"
export WP_BASE_URL="http://localhost:${WP_PLAYGROUND_PORT}"

LOG_FILE="$PROJECT_DIR/artifacts/playground.log"
mkdir -p "$(dirname "$LOG_FILE")"
: > "$LOG_FILE"

server_pid=""

stop_server() {
	if [ -n "$server_pid" ]; then
		kill "$server_pid" 2> /dev/null || true
		wait "$server_pid" 2> /dev/null || true
		server_pid=""
	fi
}
trap stop_server EXIT

# Starts a fresh site and waits until the WP-CLI bridge, which starts
# listening only after WordPress has booted, is up.
#
# $1: number of PHP workers.
start_server() {
	local start_line
	start_line=$(($(wc -l < "$LOG_FILE") + 1))

	WP_PLAYGROUND_WORKERS="$1" node tests/e2e/playground/server.mjs >> "$LOG_FILE" 2>&1 &
	server_pid=$!

	for _ in $(seq 1 180); do
		if ! kill -0 "$server_pid" 2> /dev/null; then
			echo "Playground exited during startup:" >&2
			tail -n "+$start_line" "$LOG_FILE" >&2
			exit 1
		fi
		if tail -n "+$start_line" "$LOG_FILE" | grep -q "Playground ready"; then
			tail -n "+$start_line" "$LOG_FILE" | grep "Playground ready"
			return
		fi
		sleep 1
	done

	echo "Playground did not start within 180s:" >&2
	tail -n "+$start_line" "$LOG_FILE" >&2
	exit 1
}

if [ "$#" -gt 0 ]; then
	start_server 1
	./.wp-env/scripts/run-e2e.sh "$@"
	exit
fi

STORAGE_SPEC="tests/e2e/cloudinary-storage-modes.spec.js"

# Unlike `npm run test:e2e`, run every pass even when one fails, so one CI
# run reports every failure. Exit non-zero if any failed.
status=0

start_server 6
npm run --silent test:e2e:parallel || status=1
stop_server

start_server 6
./.wp-env/scripts/run-e2e.sh --grep @serial --workers=1 \
	--grep-invert "Cloudinary storage modes" || status=1
stop_server

start_server 1
./.wp-env/scripts/run-e2e.sh "$STORAGE_SPEC" --workers=1 || status=1
stop_server

exit "$status"
