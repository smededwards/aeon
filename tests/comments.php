<?php
/**
 * Behaviour checks for reversible comment controls, executed through WP-CLI.
 * Creates and removes its own Posts, Pages, comments and unrelated-type fixtures.
 *
 * @package Aeon
 */

$disabled = 'disabled' === ( $args[0] ?? 'disabled' );
$fixtures = array();
$assert   = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
};
register_post_type(
	'aeon_comment_test',
	array(
		'public'   => true,
		'supports' => array( 'title', 'comments', 'trackbacks' ),
	)
);
try {
	foreach ( array( 'post', 'page', 'aeon_comment_test' ) as $fixture_type ) {
		$fixture_id = wp_insert_post(
			array(
				'post_type'      => $fixture_type,
				'post_status'    => 'publish',
				'post_title'     => 'Aeon discussion verification',
				'comment_status' => 'open',
				'ping_status'    => 'open',
			),
			true
		);
		$assert( ! is_wp_error( $fixture_id ), 'Fixture creation failed.' );
		$fixtures[ $fixture_type ] = $fixture_id;
	}
	$comment_id = wp_insert_comment(
		array(
			'comment_post_ID'  => $fixtures['post'],
			'comment_content'  => 'Stored fixture comment',
			'comment_approved' => 1,
		)
	);
	wp_update_comment_count_now( $fixtures['post'] );
	foreach ( array( 'post', 'page' ) as $fixture_type ) {
		$assert( comments_open( $fixtures[ $fixture_type ] ) === ! $disabled, 'Comment availability did not follow the setting.' );
		$assert( pings_open( $fixtures[ $fixture_type ] ) === ! $disabled, 'Pingback availability did not follow the setting.' );
		$assert( post_type_supports( $fixture_type, 'comments' ) === ! $disabled, 'Editor support did not follow the setting.' );
		$assert( 'open' === get_post_field( 'comment_status', $fixtures[ $fixture_type ] ), 'Stored discussion preference was overwritten.' );
	}
	$assert( comments_open( $fixtures['aeon_comment_test'] ) && pings_open( $fixtures['aeon_comment_test'] ), 'Unrelated content types were affected.' );
	$assert( post_type_supports( 'aeon_comment_test', 'comments' ), 'Unrelated editor support was affected.' );
	$stored  = get_comment( $comment_id );
	$visible = apply_filters( 'comments_array', array( $stored ), $fixtures['post'] );
	$assert( count( $visible ) === ( $disabled ? 0 : 1 ), 'Classic comment visibility failed.' );
	$assert( null !== get_comment( $comment_id ) && 1 === (int) get_post_field( 'comment_count', $fixtures['post'] ), 'Stored comment data was changed.' );
	$assert( (int) get_comments_number( $fixtures['post'] ) === ( $disabled ? 0 : 1 ), 'Visible count failed.' );
	$columns = apply_filters(
		'manage_posts_columns',
		array(
			'title'    => 'Title',
			'comments' => 'Comments',
		),
		'post'
	);
	$assert( isset( $columns['comments'] ) === ! $disabled, 'Comment table column failed.' );
	$other_columns = apply_filters( 'manage_posts_columns', array( 'comments' => 'Comments' ), 'aeon_comment_test' );
	$assert( isset( $other_columns['comments'] ), 'Unrelated table column was removed.' );
	$block = new WP_Block(
		array(
			'blockName'    => 'core/comments',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		),
		array( 'postId' => $fixtures['post'] )
	);
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress's native dynamic block hook.
	$markup = apply_filters( 'render_block_core/comments', '<section>Existing comments</section>', array(), $block );
	$assert( ( '' === $markup ) === $disabled, 'Native comment block visibility failed.' );
	$administrators = get_users(
		array(
			'role'   => 'administrator',
			'fields' => 'ID',
			'number' => 1,
		)
	);
	wp_set_current_user( (int) $administrators[0] );
	$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
	$request->set_param( 'post', $fixtures['post'] );
	$request->set_param( 'content', 'Never inserted by this permissions check' );
	$permission = ( new WP_REST_Comments_Controller() )->create_item_permissions_check( $request );
	$assert( $disabled ? is_wp_error( $permission ) : true === $permission, 'REST comment permissions did not follow the setting.' );
	if ( $disabled ) {
		$response = wp_handle_comment_submission(
			array(
				'comment_post_ID' => $fixtures['post'],
				'comment'         => 'Rejected fixture submission',
			)
		);
		$assert( is_wp_error( $response ) && 'comment_closed' === $response->get_error_code(), 'Classic comment submission was not closed.' );
	}
	$settings = new Aeon\Settings();
	$assert( $settings->sanitise( array() )['disable_comments'] === $disabled, 'Partial settings submission changed the preference.' );
	WP_CLI::success( 'Comment controls passed in ' . ( $disabled ? 'disabled' : 'enabled' ) . ' mode, preserving existing data and unrelated post types.' );
} finally {
	foreach ( $fixtures as $fixture_id ) {
		wp_delete_post( $fixture_id, true );
	}
	unregister_post_type( 'aeon_comment_test' );
}
