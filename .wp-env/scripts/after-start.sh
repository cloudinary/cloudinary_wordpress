#!/bin/bash
# Orchestrates everything that has to happen after `wp-env start`.
#
# Referenced by .wp-env.json as the afterStart lifecycle script. The order
# matters: the containers must be patched before the proxy points at them, and
# the proxy must have issued the certificate before the containers can trust it.

set -euo pipefail

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Apache also listens on the published port, so plain HTTP loopback keeps
# working as a fallback.
"$SCRIPTS_DIR/fix-loopback.sh"

# Issues the certificate on first run, then starts nginx.
"$SCRIPTS_DIR/proxy-up.sh"

# Needs the certificate from the previous step.
"$SCRIPTS_DIR/trust-ca.sh"
