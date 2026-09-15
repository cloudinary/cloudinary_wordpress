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

# Ports wp-env publishes for the development and tests sites. The proxy sends
# traffic to these over plain HTTP inside the Docker host network.
WP_ENV_PORT=8888
WP_ENV_TESTS_PORT=8889

# Compose project name for the proxy stack. Deliberately distinct from the
# wp-env project so `wp-env destroy` cannot take the proxy with it.
PROXY_PROJECT="cloudinary-wp-env-proxy"

# Absolute paths, resolved from this script's location so the scripts work from
# any working directory.
WP_ENV_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CERTS_DIR="$WP_ENV_DIR/certs"
PROXY_DIR="$WP_ENV_DIR/proxy"
