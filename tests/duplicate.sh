#!/bin/sh
# Verify native Post and Page duplication in a real WordPress installation.
set -eu
: "${WP_PATH:?Set WP_PATH to the WordPress installation}"
wp --path="$WP_PATH" eval-file "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)/tests/duplicate.php"
