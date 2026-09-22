#!/bin/bash
# Shared settings for the local HTTPS proxy scripts.
#
# Sourced by the other scripts in this directory so the host names, ports and
# paths are defined in exactly one place.

# The development and tests host names.
#
# `local.wpenv.net` and all of its subdomains resolve to 127.0.0.1 over public
# DNS, so no /etc/hosts entry is needed. See
# https://github.com/Automattic/vip-go-mu-plugins for the same approach.
DEV_HOST="cloudinary.local.wpenv.net"
TESTS_HOST="tests.cloudinary.local.wpenv.net"

# Ports the proxy binds on the host. Standard ports keep the port number out of
# WP_HOME, which is where scheme-related bugs tend to hide.
PROXY_HTTP_PORT=80
PROXY_HTTPS_PORT=443

# Compose project name for the proxy stack. Deliberately distinct from the
# wp-env project so `wp-env destroy` cannot take the proxy with it.
PROXY_PROJECT="cloudinary-wp-env-proxy"

# Absolute paths, resolved from this script's location so the scripts work from
# any working directory.
WP_ENV_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CERTS_DIR="$WP_ENV_DIR/certs"
PROXY_DIR="$WP_ENV_DIR/proxy"
PROJECT_DIR="$(cd "$WP_ENV_DIR/.." && pwd)"

# Ports wp-env publishes for the development and tests sites. The proxy forwards
# to these over plain HTTP through the Docker host gateway.
#
# wp-env supports overriding them through the WP_ENV_PORT and WP_ENV_TESTS_PORT
# environment variables and through a "port" key in .wp-env.json or
# .wp-env.override.json. Respect an already-exported variable, then fall back to
# the config files, and only then to wp-env's own defaults. A hard-coded value
# here would leave nginx forwarding to a port nothing listens on, which surfaces
# as an upstream error rather than as an obvious misconfiguration.
read_configured_port() {
	local key="$1"
	local file

	for file in "$PROJECT_DIR/.wp-env.override.json" "$PROJECT_DIR/.wp-env.json"; do
		if [ ! -f "$file" ]; then
			continue
		fi

		local value
		value=$(node -e "
			try {
				const config = require('$file');
				const port = $key;
				if ( Number.isInteger( port ) ) {
					process.stdout.write( String( port ) );
				}
			} catch ( error ) {}
		" 2>/dev/null)

		if [ -n "$value" ]; then
			echo "$value"
			return
		fi
	done
}

WP_ENV_PORT="${WP_ENV_PORT:-$(read_configured_port 'config.port')}"
WP_ENV_PORT="${WP_ENV_PORT:-8888}"

WP_ENV_TESTS_PORT="${WP_ENV_TESTS_PORT:-$(read_configured_port 'config.env && config.env.tests && config.env.tests.port')}"
WP_ENV_TESTS_PORT="${WP_ENV_TESTS_PORT:-8889}"

# Prints the names of this project's wp-env containers, one per line.
#
# Identifies them by the bind mount of this repository, which wp-env adds to
# every WordPress and CLI container as the plugin directory. Matching on the
# container name is not safe: wp-env derives its Compose project name from a
# hash of the config path, so a pattern loose enough to match it also matches
# other projects' containers, and these scripts would then edit /etc/hosts and
# the certificate store of an unrelated environment.
#
# Only the WordPress and CLI services carry this mount, so the database
# containers, which need neither the host alias nor the certificate authority,
# are excluded automatically.
wp_env_containers() {
	local container

	for container in $(docker ps --format '{{.Names}}'); do
		if docker inspect "$container" --format '{{json .Mounts}}' 2>/dev/null |
			grep -q "\"Source\":\"$PROJECT_DIR\""; then
			echo "$container"
		fi
	done
}
