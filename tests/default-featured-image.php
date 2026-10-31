<?php
/**
 * Verify the default featured-image fallback in WordPress.
 *
 * @package Aeon
 */

$aeon_user     = get_current_user_id();
$aeon_image_id = 0;
$aeon_post_id  = 0;
$aeon_page_id  = 0;
$aeon_previous = get_option( \Aeon\Features\DefaultFeaturedImage::OPTION, null );
$aeon_upload   = wp_upload_bits( 'aeon-placeholder-' . wp_generate_uuid4() . '.png', null, file_get_contents( WP_PLUGIN_DIR . '/aeon/tests/fixtures/pixel.png' ) );

try {
	if ( $aeon_upload['error'] ) {
		throw new RuntimeException( 'Could not create the fallback-image fixture.' );
	}
	$aeon_image_id = wp_insert_attachment(
		array(
			'post_title'     => 'Aeon fallback fixture',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
		),
		$aeon_upload['file']
	);
	wp_update_attachment_metadata( $aeon_image_id, wp_generate_attachment_metadata( $aeon_image_id, $aeon_upload['file'] ) );
	update_option( \Aeon\Features\DefaultFeaturedImage::OPTION, $aeon_image_id );
	$aeon_post_id  = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Aeon fallback fixture post',
		)
	);
	$aeon_page_id  = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Aeon fallback fixture page',
		)
	);
	$aeon_fallback = new \Aeon\Features\DefaultFeaturedImage();
	if (
		$aeon_image_id !== $aeon_fallback->default_thumbnail( 0, get_post( $aeon_post_id ) ) ||
		$aeon_image_id !== $aeon_fallback->default_thumbnail( 0, get_post( $aeon_page_id ) )
	) {
		throw new RuntimeException( 'Posts and Pages without a thumbnail must receive the configured fallback.' );
	}
	if ( $aeon_image_id !== $aeon_fallback->default_thumbnail( $aeon_image_id, get_post( $aeon_post_id ) ) ) {
		throw new RuntimeException( 'An existing featured image must remain unchanged.' );
	}
	WP_CLI::success( 'Default featured-image fallback passed.' );
} finally {
	wp_set_current_user( $aeon_user );
	if ( null === $aeon_previous ) {
		delete_option( \Aeon\Features\DefaultFeaturedImage::OPTION );
	} else {
		update_option( \Aeon\Features\DefaultFeaturedImage::OPTION, $aeon_previous );
	}
	if ( $aeon_post_id ) {
		wp_delete_post( $aeon_post_id, true );
	}
	if ( $aeon_page_id ) {
		wp_delete_post( $aeon_page_id, true );
	}
	if ( $aeon_image_id ) {
		wp_delete_attachment( $aeon_image_id, true );
	}
}
