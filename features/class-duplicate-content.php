<?php
/**
 * Duplicate Posts and Pages from the native list tables.
 *
 * @package Aeon
 */

namespace Aeon\Features;

/**
 * Creates editable Draft copies without duplicating media files.
 */
final class DuplicateContent {
	/**
	 * Metadata that should describe the new copy rather than the source.
	 *
	 * @var string[]
	 */
	private const SKIPPED_META = array(
		'_edit_last',
		'_edit_lock',
		'_thumbnail_id',
		'_wp_old_slug',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
	);

	/**
	 * Register list-table actions and the protected admin handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'post_row_actions', array( $this, 'post_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'page_actions' ), 10, 2 );
		add_action( 'admin_post_aeon_duplicate_post', array( $this, 'handle' ) );
	}

	/**
	 * Add the duplicate action to Post rows.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post Current Post.
	 * @return array<string, string>
	 */
	public function post_actions( array $actions, \WP_Post $post ): array {
		return $this->add_action( $actions, $post );
	}

	/**
	 * Add the duplicate action to Page rows.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post Current Page.
	 * @return array<string, string>
	 */
	public function page_actions( array $actions, \WP_Post $post ): array {
		return $this->add_action( $actions, $post );
	}

	/**
	 * Duplicate an authorised Post or Page and open its editor.
	 *
	 * @return void
	 */
	public function handle(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'aeon_duplicate_post_' . $post_id ) ) {
			wp_die( esc_html__( 'This duplication request is invalid or has expired.', 'aeon' ), esc_html__( 'Duplicate content', 'aeon' ), array( 'response' => 403 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! $this->can_duplicate( $post ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this content.', 'aeon' ), esc_html__( 'Duplicate content', 'aeon' ), array( 'response' => 403 ) );
		}

		$duplicate_id = $this->duplicate( $post );
		if ( is_wp_error( $duplicate_id ) ) {
			wp_die( esc_html__( 'The content could not be duplicated.', 'aeon' ), esc_html__( 'Duplicate content', 'aeon' ), array( 'response' => 500 ) );
		}

		$edit_link = get_edit_post_link( $duplicate_id, '' );
		if ( ! $edit_link ) {
			wp_die( esc_html__( 'The duplicate was created, but its editor could not be opened.', 'aeon' ), esc_html__( 'Duplicate content', 'aeon' ), array( 'response' => 500 ) );
		}

		wp_safe_redirect( $edit_link, 303 );
		exit;
	}

	/**
	 * Create a Draft copy of a Post or Page and its related native data.
	 *
	 * @param \WP_Post $source Source content.
	 * @return int|\WP_Error New Post ID or the insertion error.
	 */
	public function duplicate( \WP_Post $source ): int|\WP_Error {
		$duplicate_id = wp_insert_post(
			wp_slash(
				array(
					'post_author'           => get_current_user_id(),
					'post_content'          => $source->post_content,
					'post_content_filtered' => $source->post_content_filtered,
					'post_excerpt'          => $source->post_excerpt,
					'post_parent'           => $source->post_parent,
					'post_password'         => $source->post_password,
					'post_status'           => 'draft',
					// Translators: %s is the title of the source content.
					'post_title'            => sprintf( __( 'Copy of %s', 'aeon' ), $source->post_title ),
					'post_type'             => $source->post_type,
					'comment_status'        => $source->comment_status,
					'ping_status'           => $source->ping_status,
					'menu_order'            => $source->menu_order,
				),
			),
			true
		);

		if ( is_wp_error( $duplicate_id ) ) {
			return $duplicate_id;
		}

		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) ) {
				wp_delete_post( $duplicate_id, true );
				return $terms;
			}
			$term_result = wp_set_object_terms( $duplicate_id, $terms, $taxonomy );
			if ( is_wp_error( $term_result ) ) {
				wp_delete_post( $duplicate_id, true );
				return $term_result;
			}
		}

		foreach ( get_post_meta( $source->ID ) as $key => $values ) {
			if ( in_array( $key, self::SKIPPED_META, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $duplicate_id, $key, $value );
			}
		}

		$thumbnail_id = absint( get_post_meta( $source->ID, '_thumbnail_id', true ) );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $duplicate_id, $thumbnail_id );
		}

		return $duplicate_id;
	}

	/**
	 * Add a protected duplicate URL when the current user may edit the source.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post Content row.
	 * @return array<string, string>
	 */
	private function add_action( array $actions, \WP_Post $post ): array {
		if ( ! $this->can_duplicate( $post ) ) {
			return $actions;
		}

		$url                       = add_query_arg(
			array(
				'action'  => 'aeon_duplicate_post',
				'post_id' => $post->ID,
			),
			admin_url( 'admin-post.php' )
		);
		$actions['aeon-duplicate'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( wp_nonce_url( $url, 'aeon_duplicate_post_' . $post->ID ) ),
			esc_html__( 'Duplicate', 'aeon' )
		);

		return $actions;
	}

	/**
	 * Check the supported type, status and edit capability for a source.
	 *
	 * @param \WP_Post $post Candidate source.
	 * @return bool Whether the source may be duplicated.
	 */
	private function can_duplicate( \WP_Post $post ): bool {
		return in_array( $post->post_type, array( 'post', 'page' ), true )
			&& in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'future', 'private' ), true )
			&& current_user_can( 'edit_post', $post->ID );
	}
}
