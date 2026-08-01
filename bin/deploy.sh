#!/usr/bin/env bash
# Deploys the current branch on the production server: fast-forwards from
# origin/main and clears the compiled Twig template cache (prod runs with
# auto_reload off, so stale .twig output would otherwise persist until the
# cache directory is emptied by hand).
#
# Run this ON THE SERVER, from within the notquitehuman_data checkout:
#   ssh webserver
#   cd ~/Projects/other_docker/notquitehuman_data
#   bin/deploy.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTAINER=notquitehuman
CONTAINER_APP_PATH=/var/www/notquitehuman

cd "$REPO_ROOT"

echo "==> Fetching origin"
git fetch origin

echo "==> Fast-forwarding to origin/main"
git merge --ff-only origin/main

echo "==> Clearing Twig template cache"
docker exec "$CONTAINER" sh -c "rm -rf '$CONTAINER_APP_PATH/var/cache/twig'/*"

echo "==> Deploy complete ($(git rev-parse --short HEAD))"
