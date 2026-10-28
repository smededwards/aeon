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
pattern_id=''
restore() {
  if [ "$existed" = 1 ]; then
    wp_run option update aeon_core_features "$backup" --format=json >/dev/null
  else
    wp_run option delete aeon_core_features >/dev/null 2>&1 || true
  fi
  if [ -n "$pattern_id" ]; then wp_run post delete "$pattern_id" --force >/dev/null; fi
}
trap restore EXIT
wp_run option delete aeon_core_features >/dev/null 2>&1 || true
wp_run eval-file "$repo_dir/tests/integration.php"

pattern_id=$(wp_run post create --post_type=wp_block --post_status=publish --post_title='Aeon pattern integration' --post_content='<!-- wp:paragraph --><p>Aeon pattern verification.</p><!-- /wp:paragraph -->' --porcelain)
wp_run option delete aeon_core_features >/dev/null 2>&1 || true
wp_run eval-file "$repo_dir/tests/patterns.php" default "$pattern_id"
for flags in 000 001 010 011 100 101 110 111; do
  shortcut=$(printf '%s' "$flags" | cut -c1)
  core=$(printf '%s' "$flags" | cut -c2)
  remote=$(printf '%s' "$flags" | cut -c3)
  values=$(printf '{"patterns_shortcut":%s,"core_patterns":%s,"remote_patterns":%s}' "$shortcut" "$core" "$remote")
  wp_run option update aeon_core_features "$values" --format=json >/dev/null
  wp_run eval-file "$repo_dir/tests/patterns.php" "$flags" "$pattern_id"
done
