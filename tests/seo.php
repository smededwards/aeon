<?php
/**
 * SEO metadata, native editor persistence and access checks in WordPress.
 *
 * @package Aeon
 */

use Aeon\Features\Seo;

$aeon_assert          = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
};
$aeon_query_vars      = $GLOBALS['wp_query']->query_vars;
$aeon_user            = get_current_user_id();
$aeon_previous_screen = null;
$aeon_page_id         = 0;
$aeon_posts_page      = get_option( 'page_for_posts', null );
$aeon_ids             = array();
$aeon_images          = array();
$aeon_seo             = new Seo( new \Aeon\Settings() );
require_once ABSPATH . 'wp-admin/includes/image.php';
try {
	$aeon_id    = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'SEO fixture',
			'post_excerpt' => 'Native excerpt fallback.',
			'post_content' => '<!-- wp:paragraph --><p>Native body fallback.</p><!-- /wp:paragraph --><!-- wp:query --><!-- wp:query-no-results --><!-- wp:paragraph --><p>SHOULD_NOT_LEAK</p><!-- /wp:paragraph --><!-- /wp:query-no-results --><!-- /wp:query -->',
		)
	);
	$aeon_ids[] = $aeon_id;
	foreach ( array( 'featured', 'social' ) as $aeon_name ) {
		$aeon_upload = wp_upload_bits( 'aeon-seo-' . $aeon_name . '-' . wp_generate_uuid4() . '.png', null, file_get_contents( WP_PLUGIN_DIR . '/aeon/tests/fixtures/pixel.png' ) );
		$aeon_assert( ! $aeon_upload['error'], 'Could not create the image fixture.' );
		$aeon_image    = wp_insert_attachment(
			array(
				'post_title'     => 'SEO image fixture',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			),
			$aeon_upload['file']
		);
		$aeon_images[] = $aeon_image;
		wp_update_attachment_metadata( $aeon_image, wp_generate_attachment_metadata( $aeon_image, $aeon_upload['file'] ) );
	}
	set_post_thumbnail( $aeon_id, $aeon_images[0] );
	$aeon_view   = static function ( int $post_id ): void {
		wp(
			array(
				'p'           => $post_id,
				'post_type'   => 'any',
				'post_status' => 'any',
			)
		);
	};
	$aeon_markup = static function () use ( $aeon_seo ): string {
		ob_start();
		$aeon_seo->head();
		return ob_get_clean();
	};
	/**
	 * Capture and decode the schema graph emitted by Aeon.
	 *
	 * @return array Decoded JSON-LD document.
	 */
	$aeon_schema_data = static function () use ( $aeon_seo ): array {
		ob_start();
		$aeon_seo->schema();
		$aeon_schema_markup = ob_get_clean();
		if ( ! preg_match( '/<script[^>]*>(.*?)<\/script>/s', $aeon_schema_markup, $aeon_matches ) ) {
			return array();
		}
		$aeon_schema = json_decode( $aeon_matches[1], true );
		return is_array( $aeon_schema ) ? $aeon_schema : array();
	};
	$aeon_view( $aeon_id );
	$aeon_head = $aeon_markup();
	$aeon_assert( str_contains( $aeon_head, 'Native excerpt fallback.' ) && str_contains( $aeon_head, wp_get_attachment_url( $aeon_images[0] ) ), 'Default excerpt or featured-image fallback failed.' );
	$aeon_assert( ! str_contains( $aeon_head, '<title>' ) && ! str_contains( $aeon_head, 'rel="canonical"' ), 'Aeon must not duplicate core title/canonical output.' );
	$aeon_assert( false !== has_action( 'wp_head', 'rel_canonical' ), 'Core canonical output was removed.' );
	$aeon_graph = $aeon_schema_data()['@graph'] ?? array();
	$aeon_types = array_column( $aeon_graph, '@type' );
	$aeon_assert( in_array( 'BlogPosting', $aeon_types, true ) && in_array( 'Person', $aeon_types, true ) && in_array( 'WebSite', $aeon_types, true ), 'A public post must emit a valid article, person and website schema graph.' );
	$aeon_page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'SEO page fixture',
		)
	);
	$aeon_ids[]   = $aeon_page_id;
	$aeon_view( $aeon_page_id );
	$aeon_page_types = array_column( $aeon_schema_data()['@graph'] ?? array(), '@type' );
	$aeon_assert( in_array( 'WebPage', $aeon_page_types, true ) && ! in_array( 'BlogPosting', $aeon_page_types, true ), 'A public page must emit page schema without article schema.' );
	update_option( 'page_for_posts', $aeon_page_id );
	$GLOBALS['wp_query']->is_home           = true;
	$GLOBALS['wp_query']->is_page           = false;
	$GLOBALS['wp_query']->is_singular       = false;
	$GLOBALS['wp_query']->queried_object    = get_post( $aeon_page_id );
	$GLOBALS['wp_query']->queried_object_id = $aeon_page_id;
	$aeon_index_types                       = array_column( $aeon_schema_data()['@graph'] ?? array(), '@type' );
	$aeon_assert( in_array( 'WebPage', $aeon_index_types, true ) && ! in_array( 'BlogPosting', $aeon_index_types, true ), 'The Posts index must emit page schema without a duplicate article entity.' );
	$aeon_view( $aeon_id );
	update_post_meta( $aeon_id, Seo::TITLE, '<b>SEO override</b>' );
	update_post_meta( $aeon_id, Seo::DESCRIPTION, 'Description "quoted" & safe.' );
	update_post_meta( $aeon_id, Seo::IMAGE, $aeon_images[1] );
	$aeon_view( $aeon_id );
	$aeon_head = $aeon_markup();
	$aeon_assert( str_contains( wp_get_document_title(), 'SEO override' ) && 'SEO fixture' === get_post_field( 'post_title', $aeon_id ), 'SEO titles must not rewrite the native post title.' );
	$aeon_assert( str_contains( $aeon_head, 'Description &quot;quoted&quot; &amp; safe.' ) && str_contains( $aeon_head, wp_get_attachment_url( $aeon_images[1] ) ), 'Description escaping or custom sharing image failed.' );
	$aeon_assert( 1 === substr_count( $aeon_head, 'name="description"' ) && 1 === substr_count( $aeon_head, 'property="og:title"' ) && 1 === substr_count( $aeon_head, 'name="twitter:card"' ), 'Metadata was duplicated.' );
	update_post_meta( $aeon_id, Seo::IMAGE, $aeon_id );
	$aeon_assert( 0 === (int) get_post_meta( $aeon_id, Seo::IMAGE, true ) && str_contains( $aeon_markup(), wp_get_attachment_url( $aeon_images[0] ) ), 'Non-image IDs must restore the featured-image fallback.' );
	delete_post_meta( $aeon_id, Seo::DESCRIPTION );
	wp_update_post(
		array(
			'ID'           => $aeon_id,
			'post_excerpt' => '',
		)
	);
	$aeon_view( $aeon_id );
	$aeon_assert( str_contains( $aeon_markup(), 'Native body fallback.' ), 'Plain body fallback failed.' );
	$aeon_assert( ! str_contains( $aeon_markup(), 'SHOULD_NOT_LEAK' ), 'Query Loop fallback text must not leak into descriptions.' );
	wp_update_post(
		array(
			'ID'            => $aeon_id,
			'post_password' => 'test-password',
		)
	);
	$aeon_view( $aeon_id );
	$aeon_assert( '' === $aeon_markup() && ! str_contains( wp_get_document_title(), 'SEO override' ), 'Password-protected content must not leak overrides.' );
	wp_update_post(
		array(
			'ID'            => $aeon_id,
			'post_password' => '',
			'post_status'   => 'draft',
		)
	);
	$aeon_view( $aeon_id );
	$aeon_assert( '' === $aeon_markup(), 'Draft content must not emit sharing metadata.' );
	wp_update_post(
		array(
			'ID'          => $aeon_id,
			'post_status' => 'publish',
		)
	);
	$aeon_view( $aeon_id );
	foreach ( array( 'post', 'page' ) as $aeon_type ) {
		$aeon_registered = get_registered_meta_keys( 'post', $aeon_type );
		foreach ( array( Seo::TITLE, Seo::DESCRIPTION, Seo::IMAGE ) as $aeon_key ) {
			$aeon_assert( isset( $aeon_registered[ $aeon_key ] ) && $aeon_registered[ $aeon_key ]['revisions_enabled'], 'Native revision-enabled meta is missing.' );
		}
	}
	wp_set_current_user( 0 );
	$aeon_rest = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $aeon_id );
	$aeon_rest->set_param( 'meta', array( Seo::TITLE => 'Unauthorised override' ) );
	$aeon_assert( rest_do_request( $aeon_rest )->get_status() >= 400, 'Guests must not edit SEO metadata.' );
	$aeon_admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $aeon_admins[0] );
	$aeon_rest->set_param( 'meta', array( Seo::TITLE => '<b>REST title</b>' ) );
	$aeon_assert( rest_do_request( $aeon_rest )->get_status() < 300 && 'REST title' === get_post_meta( $aeon_id, Seo::TITLE, true ), 'Authorised native REST writes must sanitise meta.' );
	$aeon_editor = new \Aeon\Admin\Seo( new \Aeon\Settings() );
	$_POST       = array(
		'_aeon_seo_nonce' => 'invalid',
		Seo::TITLE        => 'Bad nonce title',
	);
	$aeon_editor->save( $aeon_id );
	$aeon_assert( 'REST title' === get_post_meta( $aeon_id, Seo::TITLE, true ), 'An invalid editor nonce changed metadata.' );
	$_POST = array(
		'_aeon_seo_nonce' => wp_create_nonce( 'aeon_seo_' . $aeon_id ),
		Seo::TITLE        => '<b>Editor title</b>',
	);
	$aeon_editor->save( $aeon_id );
	$aeon_assert( 'Editor title' === get_post_meta( $aeon_id, Seo::TITLE, true ), 'Native editor save failed.' );
	ob_start();
	$aeon_editor->render( get_post( $aeon_id ) );
	$aeon_form = ob_get_clean();
	$aeon_assert( str_contains( $aeon_form, '_aeon_seo_nonce' ) && str_contains( $aeon_form, 'aeon-seo__choose' ), 'Native SEO editor fields or media picker are missing.' );
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	$aeon_previous_screen = get_current_screen();
	set_current_screen( 'post' );
	$aeon_editor->assets( 'post.php' );
	$aeon_script = wp_scripts()->registered['aeon-seo'] ?? null;
	$aeon_style  = wp_styles()->registered['aeon-seo'] ?? null;
	$aeon_assert( $aeon_script && str_ends_with( $aeon_script->src, '/plugins/aeon/build/seo.js' ), 'The SEO editor script must be enqueued from the built plugin asset.' );
	$aeon_assert( $aeon_style && str_ends_with( $aeon_style->src, '/plugins/aeon/build/seo.css' ), 'The SEO editor stylesheet must be enqueued from the built plugin asset.' );
	WP_CLI::success( 'SEO fallbacks, overrides, escaping, image validation, protected content, REST permissions and native editor persistence passed.' );
} finally {
	$_POST = array();
	set_current_screen( $aeon_previous_screen ? $aeon_previous_screen->id : '' );
	wp_set_current_user( $aeon_user );
	foreach ( $aeon_images as $aeon_image ) {
		wp_delete_attachment( $aeon_image, true );
	}
	foreach ( $aeon_ids as $aeon_id ) {
		wp_delete_post( $aeon_id, true );
	}
	if ( null === $aeon_posts_page ) {
		delete_option( 'page_for_posts' );
	} else {
		update_option( 'page_for_posts', $aeon_posts_page );
	}
	wp( $aeon_query_vars );
}
