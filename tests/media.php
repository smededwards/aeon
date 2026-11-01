<?php
/**
 * Verify WebP and restricted SVG upload detection in WordPress.
 *
 * @package Aeon
 */

$aeon_user      = get_current_user_id();
$aeon_users     = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$aeon_valid     = wp_tempnam( 'aeon-valid.svg' );
$aeon_invalid   = wp_tempnam( 'aeon-invalid.svg' );
$aeon_uploads   = new \Aeon\Features\MediaUploads();
$aeon_svg       = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>';
$aeon_malicious = '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&xxe;</svg>';

try {
	if ( ! $aeon_valid || ! $aeon_invalid ) {
		throw new RuntimeException( 'Could not create temporary SVG fixtures.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
		throw new RuntimeException( 'Could not access the WordPress filesystem.' );
	}
	$wp_filesystem->put_contents( $aeon_valid, $aeon_svg, FS_CHMOD_FILE );
	$wp_filesystem->put_contents( $aeon_invalid, $aeon_malicious, FS_CHMOD_FILE );
	wp_set_current_user( (int) $aeon_users[0] );
	$aeon_mimes = $aeon_uploads->add_mimes( array() );
	if ( 'image/webp' !== ( $aeon_mimes['webp'] ?? '' ) || 'image/svg+xml' !== ( $aeon_mimes['svg'] ?? '' ) ) {
		throw new RuntimeException( 'Administrators must receive WebP and SVG upload MIME types.' );
	}
	$aeon_webp = $aeon_uploads->check_filetype( array(), '/tmp/aeon.webp', 'aeon.webp', array(), 'image/webp' );
	if ( 'webp' !== ( $aeon_webp['ext'] ?? '' ) || 'image/webp' !== ( $aeon_webp['type'] ?? '' ) ) {
		throw new RuntimeException( 'Valid WebP MIME detection failed.' );
	}
	$aeon_wrong_webp = $aeon_uploads->check_filetype( array(), '/tmp/aeon.webp', 'aeon.webp', array(), 'image/png' );
	if ( ! empty( $aeon_wrong_webp['ext'] ) || ! empty( $aeon_wrong_webp['type'] ) ) {
		throw new RuntimeException( 'A non-WebP file must not be accepted as WebP.' );
	}
	$aeon_valid_svg = $aeon_uploads->check_filetype( array(), $aeon_valid, 'aeon.svg', array(), false );
	$aeon_bad_svg   = $aeon_uploads->check_filetype( array(), $aeon_invalid, 'aeon.svg', array(), false );
	if ( 'svg' !== ( $aeon_valid_svg['ext'] ?? '' ) || 'image/svg+xml' !== ( $aeon_valid_svg['type'] ?? '' ) || $aeon_bad_svg ) {
		throw new RuntimeException( 'Valid SVG recognition or malicious SVG rejection failed.' );
	}
	wp_set_current_user( 0 );
	if ( isset( $aeon_uploads->add_mimes( array() )['svg'] ) || $aeon_uploads->check_filetype( array(), $aeon_valid, 'aeon.svg', array(), false ) ) {
		throw new RuntimeException( 'Untrusted users must not receive SVG upload support.' );
	}
	WP_CLI::success( 'WebP and restricted SVG upload checks passed.' );
} finally {
	wp_set_current_user( $aeon_user );
	if ( $aeon_valid ) {
		wp_delete_file( $aeon_valid );
	}
	if ( $aeon_invalid ) {
		wp_delete_file( $aeon_invalid );
	}
}
