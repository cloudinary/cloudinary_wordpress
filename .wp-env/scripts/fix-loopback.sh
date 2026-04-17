#!/bin/bash
# Fix loopback requests in wp-env Docker environment.
#
# WordPress in wp-env thinks its URL is localhost:8888, but inside the
# container Apache only listens on port 80. This causes self-pinging
# REST API requests (used by Cloudinary's sync daemon) to fail with
# cURL error 7. Adding port 8888 to Apache resolves this.
#
# This script finds the wp-env WordPress container and configures Apache
# to also listen on port 8888, enabling loopback requests to succeed.

# Find the wp-env wordpress container (exclude tests container).
CONTAINER=$(docker ps --format '{{.Names}}' | grep -E 'wordpress-1$' | grep -v tests | head -1)

if [ -z "$CONTAINER" ]; then
  echo "Warning: Could not find wp-env WordPress container. Loopback fix skipped."
  exit 0
fi

# Add Listen 8888 if not already present, then graceful restart Apache.
docker exec "$CONTAINER" bash -c \
  "grep -q 'Listen 8888' /etc/apache2/ports.conf || (echo 'Listen 8888' >> /etc/apache2/ports.conf && apache2ctl graceful)" 2>/dev/null

if [ $? -eq 0 ]; then
  echo "Loopback fix applied: Apache now also listens on port 8888 inside the container."
else
  echo "Warning: Failed to apply loopback fix."
fi
