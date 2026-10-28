#!/bin/sh
# Exercise separate WordPress requests so on/off hooks are registered naturally.
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
wp_run eval '$options = get_option("aeon_core_features", array()); $options["simplify_blocks"] = true; update_option("aeon_core_features", $options);' >/dev/null
wp_run eval-file "$repo_dir/tests/editor.php" simplified
wp_run eval '$options = get_option("aeon_core_features", array()); $options["simplify_blocks"] = false; update_option("aeon_core_features", $options);' >/dev/null
wp_run eval-file "$repo_dir/tests/editor.php" unrestricted
