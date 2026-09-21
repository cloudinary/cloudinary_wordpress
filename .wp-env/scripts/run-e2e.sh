#!/bin/bash
# Run Playwright with the local certificate authority trusted, when there is one.
#
# Chromium trusts the local CA through the OS keychain (`npm run
# env:install-cert`), but Playwright's Node-side APIRequestContext, which
# globalSetup uses to authenticate, ships its own CA bundle and ignores the
# keychain. NODE_EXTRA_CA_CERTS points Node at the CA.
#
# The variable is only exported when the file is actually present. Node prints
# "Ignoring extra certs ... No such file or directory" when it is not, which is
# noise in CI, where the suite runs over plain HTTP and no certificate exists.
#
# Arguments are passed through to `playwright test`.

set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/config.sh"

CA_FILE="$CERTS_DIR/rootCA.pem"

if [ -z "${NODE_EXTRA_CA_CERTS:-}" ] && [ -f "$CA_FILE" ]; then
	export NODE_EXTRA_CA_CERTS="$CA_FILE"
fi

exec npx playwright test --config tests/e2e/playwright.config.js "$@"
