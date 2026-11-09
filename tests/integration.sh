#!/bin/sh
# Run against a dedicated WordPress installation with Aeon active.
set -eu
: "${WP_PATH:?Set WP_PATH to the WordPress installation}"
wp_run() { wp --path="$WP_PATH" "$@"; }
repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
if backup=$(wp_run option get aeon_core_features --format=json 2>/dev/null); then
  existed=1
else
  existed=0
  backup='{}'
fi
restore() {
  if [ "$existed" = 1 ]; then
    wp_run option update aeon_core_features "$backup" --format=json >/dev/null
  else
    wp_run option delete aeon_core_features >/dev/null 2>&1 || true
  fi
}
trap restore EXIT
wp_run option delete aeon_core_features >/dev/null 2>&1 || true
wp_run eval-file "$repo_dir/tests/integration.php"
