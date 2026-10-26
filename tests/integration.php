<?php
/**
 * Behaviour checks executed within an actual WordPress installation.
 *
 * @package Aeon
 */

$aeon_mode     = $args[0] ?? 'enabled';
$aeon_menu_id  = (int) ( $args[1] ?? 0 );
$aeon_expected = 'disabled' !== $aeon_mode;
$aeon_settings = new \Aeon\Settings();
$aeon_assert   = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
};

$aeon_assert( $aeon_settings->navigation_enabled() === $aeon_expected, 'Stored preference was not respected.' );
$aeon_assert( current_theme_supports( 'menus' ) === $aeon_expected, 'Classic menu support did not follow the preference.' );
$aeon_assert( false !== wp_get_nav_menu_object( $aeon_menu_id ), 'The saved navigation menu was removed.' );
$aeon_assert( 1 === count( wp_get_nav_menu_items( $aeon_menu_id ) ), 'Saved navigation links were altered.' );
$aeon_values = array_replace( \Aeon\Settings::DEFAULTS, array( 'navigation' => $aeon_expected ) );
$aeon_assert(
	array_replace( $aeon_values, array( 'navigation' => true ) ) === $aeon_settings->sanitise(
		array(
			'navigation' => '1',
			'unknown'    => 'discard',
		)
	),
	'Settings must discard unknown keys.'
);
$aeon_assert( array_replace( $aeon_values, array( 'navigation' => false ) ) === $aeon_settings->sanitise( array( 'navigation' => 'false' ) ), 'Arbitrary strings must not enable features.' );
$aeon_assert( array_replace( $aeon_values, array( 'navigation' => false ) ) === $aeon_settings->sanitise( array( 'navigation' => '0' ) ), 'An unchecked checkbox must disable the feature.' );
$aeon_assert( $aeon_values === $aeon_settings->sanitise( array() ), 'Partial submissions must preserve omitted preferences.' );
$aeon_assert( $aeon_values === $aeon_settings->sanitise( 'invalid' ), 'Malformed input must preserve the current preference.' );
$aeon_assert( ! $aeon_settings->is_enabled( 'unknown' ), 'Unknown features must remain disabled.' );

if ( ! $aeon_expected ) {
	add_theme_support( 'menus' );
	( new \Aeon\Features\Navigation( $aeon_settings ) )->enable_support();
	$aeon_assert( current_theme_supports( 'menus' ), 'Aeon must not remove menu support supplied by another provider.' );
}

// Check the actual Settings API registration and form, including denial to guests.
require_once ABSPATH . 'wp-admin/includes/template.php';
$aeon_page = new \Aeon\Admin\SettingsPage( $aeon_settings );
$aeon_page->register_settings();
$aeon_registered = get_registered_settings();
$aeon_assert( isset( $aeon_registered[ \Aeon\Settings::OPTION ] ), 'The settings option was not registered.' );
$aeon_users = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$aeon_user  = get_current_user_id();
wp_set_current_user( (int) $aeon_users[0] );
add_settings_error( 'aeon_notice_regression', 'aeon_notice_single', 'Aeon notice regression marker.', 'success' );
ob_start();
require ABSPATH . 'wp-admin/options-head.php';
$aeon_page->render();
$aeon_form = ob_get_clean();
$aeon_assert( 1 === substr_count( $aeon_form, 'Aeon notice regression marker.' ), 'Settings notices must be rendered once by the native settings header.' );
$aeon_assert( str_contains( $aeon_form, 'action="options.php"' ), 'Settings must use the core save endpoint.' );
$aeon_assert( str_contains( $aeon_form, 'name="_wpnonce"' ), 'The settings form is missing its nonce.' );
$aeon_assert( str_contains( $aeon_form, 'aeon_core_features[navigation]' ), 'The navigation control was not rendered.' );

$aeon_die_handler = static function () {
	return static function () {
		throw new \RuntimeException( 'Settings access was denied.' );
	};
};
add_filter( 'wp_die_handler', $aeon_die_handler, PHP_INT_MAX );
wp_set_current_user( 0 );
$aeon_denied = false;
ob_start();
try {
	$aeon_page->render();
} catch ( \RuntimeException $aeon_exception ) {
	$aeon_denied = true;
} finally {
	ob_end_clean();
	remove_filter( 'wp_die_handler', $aeon_die_handler, PHP_INT_MAX );
	wp_set_current_user( $aeon_user );
}
$aeon_assert( $aeon_denied, 'Unauthorised users must not see the settings form.' );

WP_CLI::success( 'Navigation behaviour passed: ' . $aeon_mode );
