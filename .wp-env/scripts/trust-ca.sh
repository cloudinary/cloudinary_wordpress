#!/bin/bash
# Make HTTPS loopback requests work inside the wp-env containers.
#
# The plugin's sync daemon calls its own REST API. Once WP_HOME is an https://
# URL those calls leave the container, so the container has to be able to both
# resolve the host name and verify the certificate:
#
#   1. Point the proxy host names at the Docker host gateway, because
#      cloudinary.local.wpenv.net resolves to 127.0.0.1, which inside a
#      container means the container itself.
#   2. Install the mkcert root CA so the self-signed certificate verifies.
#
# Without step 2 every loopback request would need sslverify disabled, and the
# HTTPS path would never be exercised the way it is in production.
#
# Container IPs and the gateway address change between restarts, so this runs on
# every `wp-env start` and rewrites what it finds.

set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/config.sh"

CA_FILE="$CERTS_DIR/rootCA.pem"

if [ ! -f "$CA_FILE" ]; then
	echo "Warning: no root CA at $CA_FILE. Run 'npm run env:proxy:up' first; skipping loopback trust setup."
	exit 0
fi

# This project's WordPress and CLI containers. The CLI containers are included
# so WP-CLI commands and the PHPUnit suite reach the site over HTTPS too.
CONTAINERS=$(wp_env_containers)

if [ -z "$CONTAINERS" ]; then
	echo "Warning: no wp-env containers found. Loopback trust setup skipped."
	exit 0
fi

for container in $CONTAINERS; do
	# host.docker.internal is mapped to host-gateway in wp-env's compose file,
	# so resolving it inside the container gives the address the proxy is
	# reachable on.
	gateway=$(docker exec "$container" getent hosts host.docker.internal 2>/dev/null | awk '{print $1}' | head -1)

	if [ -z "$gateway" ]; then
		echo "Warning: could not resolve the host gateway in $container; skipping."
		continue
	fi

	# Replace any previous entry so a changed gateway address cannot leave a
	# stale line behind, then append the current one.
	#
	# Docker bind-mounts /etc/hosts, so `sed -i` fails with "Device or resource
	# busy": it works by renaming a temporary file over the target. Filter into
	# a temporary file and copy the contents back instead, which writes through
	# the existing inode. Errors are surfaced rather than discarded, because a
	# silent failure here leaves a stale address behind and breaks loopback in a
	# way that is hard to trace back to this script.
	if ! docker exec --user root "$container" bash -c "
		set -e
		grep -v -e '$DEV_HOST' -e '$TESTS_HOST' /etc/hosts > /tmp/hosts.new
		echo '$gateway $DEV_HOST $TESTS_HOST' >> /tmp/hosts.new
		cat /tmp/hosts.new > /etc/hosts
		rm -f /tmp/hosts.new
	"; then
		echo "Warning: could not update /etc/hosts in $container."
	fi

	# update-ca-certificates rebuilds the bundle that both PHP and curl read.
	docker cp "$CA_FILE" "$container:/usr/local/share/ca-certificates/mkcert-root-ca.crt" >/dev/null 2>&1 || {
		echo "Warning: could not copy the root CA into $container."
		continue
	}

	docker exec --user root "$container" update-ca-certificates >/dev/null 2>&1 || {
		echo "Warning: could not install the root CA in $container."
		continue
	}
done

echo "Loopback trust configured: containers resolve $DEV_HOST and trust the local CA."
