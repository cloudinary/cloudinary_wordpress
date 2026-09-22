#!/bin/bash
# Start the TLS proxy in front of wp-env.
#
# Issues the certificate on first run, then brings up nginx on ports 80 and 443.
# Run automatically by after-start.sh; safe to run on its own to restart the
# proxy without restarting wp-env.

set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/config.sh"

# Report which container or process holds a port, so a conflict names the
# culprit instead of surfacing as a raw Docker bind error.
describe_port_holder() {
	local port="$1"
	local container

	container=$(docker ps --format '{{.Names}}\t{{.Ports}}' | grep -E ":$port->" | cut -f1 | head -1)

	if [ -n "$container" ]; then
		echo "Docker container '$container'"
		return
	fi

	local process
	process=$(lsof -nP -iTCP:"$port" -sTCP:LISTEN -Fc 2>/dev/null | grep '^c' | head -1 | cut -c2-)

	if [ -n "$process" ]; then
		echo "process '$process'"
		return
	fi

	echo "another process"
}

# Ignore ports already held by our own proxy; those are from a previous start
# and compose will reuse them.
check_port() {
	local port="$1"

	if [ -z "$(lsof -nP -iTCP:"$port" -sTCP:LISTEN -t 2>/dev/null)" ]; then
		return 0
	fi

	if docker compose --project-name "$PROXY_PROJECT" ps --quiet 2>/dev/null | grep -q .; then
		return 0
	fi

	echo "Error: port $port is already in use by $(describe_port_holder "$port")." >&2
	echo "Stop it and run 'npm run env:proxy:up' to finish starting the HTTPS proxy." >&2
	return 1
}

check_port "$PROXY_HTTP_PORT"
check_port "$PROXY_HTTPS_PORT"

mkdir -p "$CERTS_DIR"

export DEV_HOST TESTS_HOST PROXY_HTTP_PORT PROXY_HTTPS_PORT WP_ENV_PORT WP_ENV_TESTS_PORT

docker compose \
	--project-name "$PROXY_PROJECT" \
	--file "$PROXY_DIR/docker-compose.yml" \
	up --detach --build --remove-orphans

echo "HTTPS proxy running:"
echo "  Development: https://$DEV_HOST"
echo "  Tests:       https://$TESTS_HOST"
