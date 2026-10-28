<?php
/**
 * Real WordPress checks for independent pattern controls.
 *
 * @package Aeon
 */

$aeon_mode       = $args[0] ?? '111';
$aeon_pattern_id = (int) ( $args[1] ?? 0 );
$aeon_flags      = 'default' === $aeon_mode ? '111' : $aeon_mode;
$aeon_shortcut   = '1' === $aeon_flags[0];
$aeon_core       = '1' === $aeon_flags[1];
$aeon_remote     = '1' === $aeon_flags[2];
$aeon_settings   = new \Aeon\Settings();
$aeon_patterns   = new \Aeon\Features\Patterns( $aeon_settings );
$aeon_registry   = \WP_Block_Patterns_Registry::get_instance();
$aeon_assert     = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
};

$aeon_assert( $aeon_settings->is_enabled( 'patterns_shortcut' ) === $aeon_shortcut, 'Shortcut preference was ignored.' );
$aeon_assert( $aeon_registry->is_registered( 'core/query-standard-posts' ) === $aeon_core, 'Bundled core patterns did not follow the preference.' );
$aeon_assert( true === current_theme_supports( 'core-block-patterns' ), 'Pattern controls must not conflate core and remote theme support.' );
$aeon_assert( apply_filters( 'should_load_remote_block_patterns', true ) === $aeon_remote, 'Remote preference was ignored.' );
$aeon_assert( false === apply_filters( 'should_load_remote_block_patterns', false ), 'Restrictions from another provider must be preserved.' );

foreach ( array( 'theme', 'plugin', 'pattern-directory/core' ) as $aeon_source ) {
	register_block_pattern(
		'aeon-verification/' . sanitize_title( $aeon_source ),
		array(
			'title'   => 'Verification',
			'content' => '<!-- wp:paragraph --><p>Verification</p><!-- /wp:paragraph -->',
			'source'  => $aeon_source,
		)
	);
}
$aeon_patterns->filter_bundled_patterns();
foreach ( array( 'theme', 'plugin', 'pattern-directory/core' ) as $aeon_source ) {
	$aeon_assert( $aeon_registry->is_registered( 'aeon-verification/' . sanitize_title( $aeon_source ) ), 'A non-bundled pattern source was removed.' );
}
$aeon_assert( null !== get_post( $aeon_pattern_id ), 'The saved user pattern was deleted.' );
$aeon_assert( str_contains( do_blocks( '<!-- wp:block {"ref":' . $aeon_pattern_id . '} /-->' ), 'Aeon pattern verification.' ), 'Saved synced-pattern content stopped rendering.' );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
$aeon_users = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $aeon_users[0] );
remove_submenu_page( 'themes.php', \Aeon\Features\Patterns::MENU_SLUG );
$aeon_patterns->add_shortcut();
$aeon_assert( count( $GLOBALS['submenu']['themes.php'] ?? array() ) === ( $aeon_shortcut ? 1 : 0 ), 'The shortcut did not follow its preference.' );
$aeon_patterns->add_shortcut();
$aeon_assert( count( $GLOBALS['submenu']['themes.php'] ?? array() ) === ( $aeon_shortcut ? 1 : 0 ), 'Duplicate shortcuts were added.' );
remove_submenu_page( 'themes.php', \Aeon\Features\Patterns::MENU_SLUG );
add_submenu_page( 'themes.php', 'Patterns', 'Patterns', 'edit_theme_options', \Aeon\Features\Patterns::MENU_SLUG );
$aeon_patterns->add_shortcut();
$aeon_assert( 1 === count( $GLOBALS['submenu']['themes.php'] ?? array() ), 'A native shortcut was duplicated or removed.' );

$aeon_page = new \Aeon\Admin\SettingsPage( $aeon_settings );
$aeon_page->register_settings();
ob_start();
$aeon_page->render();
$aeon_form = ob_get_clean();
foreach ( array_keys( \Aeon\Settings::DEFAULTS ) as $aeon_key ) {
	$aeon_assert( str_contains( $aeon_form, 'aeon_core_features[' . $aeon_key . ']' ), 'A settings control is missing.' );
}

$aeon_server  = rest_get_server();
$aeon_request = new \WP_REST_Request( 'GET', '/wp/v2/pattern-directory/patterns' );
$aeon_error   = new \WP_Error( 'earlier_restriction', 'Verification' );
$aeon_assert( $aeon_error === $aeon_patterns->restrict_directory( $aeon_error, $aeon_server, $aeon_request ), 'An earlier REST restriction was overwritten.' );
$aeon_assert( null === $aeon_patterns->restrict_directory( null, $aeon_server, new \WP_REST_Request( 'GET', '/wp/v2/pages' ) ), 'An unrelated REST route was blocked.' );
if ( $aeon_remote ) {
	$aeon_assert( null === $aeon_patterns->restrict_directory( null, $aeon_server, $aeon_request ), 'Enabled remote queries were blocked.' );
} else {
	$aeon_response = rest_do_request( $aeon_request );
	$aeon_assert( 403 === $aeon_response->get_status() && 'aeon_remote_patterns_disabled' === $aeon_response->get_data()['code'], 'Disabled directory requests were not blocked by Aeon.' );
}

wp_set_current_user( 0 );
remove_submenu_page( 'themes.php', \Aeon\Features\Patterns::MENU_SLUG );
$aeon_patterns->add_shortcut();
$aeon_assert( array() === ( $GLOBALS['submenu']['themes.php'] ?? array() ), 'A guest received the pattern-manager shortcut.' );
WP_CLI::success( 'Pattern controls passed: ' . $aeon_mode );
