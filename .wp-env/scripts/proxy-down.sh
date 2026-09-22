#!/bin/bash
# Stop the TLS proxy.
#
# The proxy is its own compose project, so `wp-env stop` does not know about it.
# The env:stop and env:destroy npm scripts call this first.

set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/config.sh"

export DEV_HOST TESTS_HOST PROXY_HTTP_PORT PROXY_HTTPS_PORT WP_ENV_PORT WP_ENV_TESTS_PORT

docker compose \
	--project-name "$PROXY_PROJECT" \
	--file "$PROXY_DIR/docker-compose.yml" \
	down --remove-orphans

echo "HTTPS proxy stopped."
