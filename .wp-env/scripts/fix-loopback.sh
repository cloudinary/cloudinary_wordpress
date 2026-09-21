#!/bin/bash
# Fix plain-HTTP loopback requests in the wp-env Docker environment.
#
# wp-env publishes WordPress on port 8888, but inside the container Apache
# only listens on port 80. Self-pinging REST API requests (used by
# Cloudinary's sync daemon) that target the published port therefore fail
# with cURL error 7. Adding port 8888 to Apache resolves this.
#
# The environment now runs over HTTPS through the proxy in .wp-env/proxy/,
# where loopback goes through the proxy instead. This fix stays because the
# published port remains reachable as a fallback, and because the CI
# environment (.wp-env.ci.json) runs on plain HTTP and relies on it.

source "$(dirname "${BASH_SOURCE[0]}")/config.sh"

# The development WordPress container. wp_env_containers scopes the search to
# this project; see config.sh for why the container name cannot be matched
# directly.
CONTAINER=$(wp_env_containers | grep -v -- '-tests-' | grep -- '-wordpress-' | head -1)

if [ -z "$CONTAINER" ]; then
  echo "Warning: Could not find wp-env WordPress container. Loopback fix skipped."
  exit 0
fi

# Add Listen 8888 if not already present, then graceful restart Apache.
if docker exec "$CONTAINER" bash -c \
  "grep -q 'Listen 8888' /etc/apache2/ports.conf || (echo 'Listen 8888' >> /etc/apache2/ports.conf && apache2ctl graceful)" 2>/dev/null; then
  echo "Loopback fix applied: Apache now also listens on port 8888 inside the container."
else
  echo "Warning: Failed to apply loopback fix."
fi
