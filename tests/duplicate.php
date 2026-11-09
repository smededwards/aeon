<?php
/**
 * Verify native Post and Page duplication, executed through WP-CLI.
 *
 * @package Aeon
 */

use Aeon\Features\DuplicateContent;

$aeon_user      = get_current_user_id();
$aeon_admins    = get_users(
	array(
		'role'   => 'administrator',
		'fields' => 'ID',
		'number' => 1,
	)
);
$aeon_ids       = array();
$aeon_image_id  = 0;
$aeon_assert    = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
};
$aeon_duplicate = new DuplicateContent();
$aeon_duplicate->register();
$aeon_assert( has_action( 'admin_post_aeon_duplicate_post', array( $aeon_duplicate, 'handle' ) ), 'The duplicate admin-post handler is not registered.' );

try {
	wp_set_current_user( (int) $aeon_admins[0] );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$aeon_upload = wp_upload_bits( 'aeon-duplicate-' . wp_generate_uuid4() . '.png', null, file_get_contents( WP_PLUGIN_DIR . '/aeon/tests/fixtures/pixel.png' ) );
	$aeon_assert( ! $aeon_upload['error'], 'Could not create the image fixture.' );
	$aeon_image_id = wp_insert_attachment(
		array(
			'post_title'     => 'Duplicate image fixture',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
		),
		$aeon_upload['file']
	);
	wp_update_attachment_metadata( $aeon_image_id, wp_generate_attachment_metadata( $aeon_image_id, $aeon_upload['file'] ) );

	foreach ( array( 'post', 'page' ) as $aeon_type ) {
		$aeon_source_id = wp_insert_post(
			array(
				'post_type'    => $aeon_type,
				'post_status'  => 'publish',
				'post_title'   => 'Aeon duplicate source ' . $aeon_type,
				'post_content' => '<!-- wp:paragraph --><p>Duplicate this content.</p><!-- /wp:paragraph -->',
				'post_excerpt' => 'Duplicate this summary.',
			),
			true
		);
		$aeon_assert( ! is_wp_error( $aeon_source_id ), 'Source content creation failed.' );
		$aeon_ids[] = $aeon_source_id;
		update_post_meta( $aeon_source_id, '_aeon_duplicate_value', 'Copied value' );
		update_post_meta( $aeon_source_id, '_edit_lock', 'Never copy this lock' );
		set_post_thumbnail( $aeon_source_id, $aeon_image_id );
		if ( 'post' === $aeon_type ) {
			wp_set_post_categories( $aeon_source_id, array( get_option( 'default_category' ) ) );
		}

		$aeon_source  = get_post( $aeon_source_id );
		$aeon_copy_id = $aeon_duplicate->duplicate( $aeon_source );
		$aeon_assert( ! is_wp_error( $aeon_copy_id ), 'Content duplication failed.' );
		$aeon_ids[] = $aeon_copy_id;
		$aeon_copy  = get_post( $aeon_copy_id );
		$aeon_assert( 'draft' === $aeon_copy->post_status, 'Duplicates must be created as Drafts.' );
		$aeon_assert( 'Copy of ' . $aeon_source->post_title === $aeon_copy->post_title, 'The duplicate title is incorrect.' );
		$aeon_assert( $aeon_source->post_content === $aeon_copy->post_content && $aeon_source->post_excerpt === $aeon_copy->post_excerpt, 'Content or excerpt was not copied.' );
		$aeon_assert( 'Copied value' === get_post_meta( $aeon_copy_id, '_aeon_duplicate_value', true ), 'Custom metadata was not copied.' );
		$aeon_assert( '' === get_post_meta( $aeon_copy_id, '_edit_lock', true ), 'Editor lock metadata must not be copied.' );
		$aeon_thumbnail_id = absint( get_post_meta( $aeon_copy_id, '_thumbnail_id', true ) );
		$aeon_assert( $aeon_image_id === $aeon_thumbnail_id, 'The featured image was not copied.' );
		if ( 'post' === $aeon_type ) {
			$aeon_assert( wp_get_post_categories( $aeon_copy_id ) === wp_get_post_categories( $aeon_source_id ), 'Post categories were not copied.' );
		}
	}

	$aeon_actions = $aeon_duplicate->post_actions( array(), get_post( $aeon_ids[0] ) );
	$aeon_assert( isset( $aeon_actions['aeon-duplicate'] ) && str_contains( $aeon_actions['aeon-duplicate'], 'aeon_duplicate_post' ), 'The Post list action is missing its protected duplicate URL.' );
	$aeon_actions = $aeon_duplicate->page_actions( array(), get_post( $aeon_ids[2] ) );
	$aeon_assert( isset( $aeon_actions['aeon-duplicate'] ), 'The Page list action is missing.' );
	WP_CLI::success( 'Native Post and Page duplication passed.' );
} finally {
	wp_set_current_user( $aeon_user );
	foreach ( $aeon_ids as $aeon_id ) {
		wp_delete_post( $aeon_id, true );
	}
	if ( $aeon_image_id ) {
		wp_delete_attachment( $aeon_image_id, true );
	}
}
