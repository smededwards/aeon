#!/bin/sh
# Verify metadata output and the handoff switch in separate WordPress requests.
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
wp_run eval '$options = get_option("aeon_core_features", array()); $options["seo"] = true; update_option("aeon_core_features", $options);' >/dev/null
wp_run eval-file "$repo_dir/tests/seo.php"
wp_run eval '$options = get_option("aeon_core_features", array()); $options["seo"] = false; update_option("aeon_core_features", $options);' >/dev/null
wp_run eval 'global $wp_filter; foreach (["wp_head", "document_title_parts", "add_meta_boxes"] as $hook) { foreach ($wp_filter[$hook]->callbacks ?? [] as $callbacks) { foreach ($callbacks as $item) { $callback = $item["function"]; if (is_array($callback) && ($callback[0] instanceof Aeon\Features\Seo || $callback[0] instanceof Aeon\Admin\Seo)) { WP_CLI::error("Aeon SEO callbacks remain after disabling."); } } } } if (false === has_action("wp_head", "rel_canonical")) { WP_CLI::error("Core canonical output was removed."); } WP_CLI::success("SEO handoff disables Aeon output and controls, retaining core canonical handling.");'
