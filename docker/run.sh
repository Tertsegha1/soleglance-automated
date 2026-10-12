#!/usr/bin/env bash
# Run a command in the PHP container with the repo mounted and vendor in a volume.
# Usage: docker/run.sh php artisan test
MSYS_NO_PATHCONV=1 exec docker run --rm -i \
  -v "$(cd "$(dirname "$0")/.." && pwd -W 2>/dev/null || pwd):/app" \
  -v soleglance_vendor:/app/vendor \
  -w /app soleglance-php "$@"
