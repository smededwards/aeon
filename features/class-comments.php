<?php
/**
 * Reversible comment controls for Posts and Pages.
 *
 * @package Aeon
 */

namespace Aeon\Features;

use Aeon\Settings;

/** Closes discussion without altering saved comments or per-post preferences. */
final class Comments {
	/**
	 * Receive feature preferences.
	 *
	 * @param Settings $settings Site settings.
	 */
	public function __construct( private readonly Settings $settings ) {}

	/** Register only while comment disabling is enabled. */
	public function register(): void {
		if ( ! $this->settings->is_enabled( 'disable_comments' ) ) {
			return;
		}
		add_action( 'init', array( $this, 'remove_support' ), 100 );
		add_filter( 'comments_open', array( $this, 'close_discussion' ), 100, 2 );
		add_filter( 'pings_open', array( $this, 'close_discussion' ), 100, 2 );
		add_filter( 'comments_array', array( $this, 'hide_comments' ), 100, 2 );
		add_filter( 'get_comments_number', array( $this, 'hide_count' ), 100, 2 );
		add_action( 'admin_menu', array( $this, 'remove_menu' ), 100 );
		add_action( 'admin_bar_menu', array( $this, 'remove_toolbar_node' ), 100 );
		add_filter( 'manage_posts_columns', array( $this, 'remove_column' ), 100, 2 );
		add_filter( 'manage_pages_columns', array( $this, 'remove_page_column' ), 100 );
		foreach ( array( 'comments', 'comments-title', 'post-comments', 'post-comments-form', 'post-comments-link', 'post-comments-count' ) as $block ) {
			add_filter( 'render_block_core/' . $block, array( $this, 'hide_block' ), 100, 3 );
		}
	}

	/** Remove native editor controls for these two post types only. */
	public function remove_support(): void {
		foreach ( array( 'post', 'page' ) as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
		}
	}

	/**
	 * Close incoming discussion for a supported content item.
	 *
	 * @param bool $open Current discussion state.
	 * @param int  $post_id Content identifier.
	 * @return bool Effective discussion state.
	 */
	public function close_discussion( bool $open, int $post_id ): bool {
		return $this->applies_to( $post_id ) ? false : $open;
	}

	/**
	 * Hide existing comments from classic comment templates.
	 *
	 * @param array $comments Queried comments.
	 * @param int   $post_id Content identifier.
	 * @return array Visible comments.
	 */
	public function hide_comments( array $comments, int $post_id ): array {
		return $this->applies_to( $post_id ) ? array() : $comments;
	}

	/**
	 * Hide comment counts without modifying stored counts.
	 *
	 * @param int|string $count Existing count.
	 * @param int        $post_id Content identifier.
	 * @return int|string Visible count.
	 */
	public function hide_count( $count, int $post_id ) {
		return $this->applies_to( $post_id ) ? 0 : $count;
	}

	/** Hide the Comments menu; retain Settings → Discussion. */
	public function remove_menu(): void {
		remove_menu_page( 'edit-comments.php' );
	}

	/**
	 * Hide the toolbar comment shortcut.
	 *
	 * @param \WP_Admin_Bar $bar Toolbar instance.
	 */
	public function remove_toolbar_node( \WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'comments' );
	}

	/**
	 * Remove comment columns for Posts only, preserving other post types.
	 *
	 * @param array  $columns Table columns.
	 * @param string $post_type Current post type.
	 * @return array Remaining columns.
	 */
	public function remove_column( array $columns, string $post_type ): array {
		if ( 'post' === $post_type ) {
			unset( $columns['comments'] );
		}
		return $columns;
	}

	/**
	 * Remove the Page table's comment column.
	 *
	 * @param array $columns Table columns.
	 * @return array Remaining columns.
	 */
	public function remove_page_column( array $columns ): array {
		unset( $columns['comments'] );
		return $columns;
	}

	/**
	 * Suppress native discussion blocks for the current content item.
	 *
	 * @param string    $content Rendered block markup.
	 * @param array     $block Parsed block data.
	 * @param \WP_Block $instance Block instance and context.
	 * @return string Visible markup.
	 */
	public function hide_block( string $content, array $block, \WP_Block $instance ): string {
		$post_id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		return $this->applies_to( $post_id ) ? '' : $content;
	}

	/**
	 * Check scope without affecting attachments or other content types.
	 *
	 * @param int $post_id Content identifier.
	 * @return bool Whether these controls apply.
	 */
	private function applies_to( int $post_id ): bool {
		return in_array( get_post_type( $post_id ), array( 'post', 'page' ), true );
	}
}
