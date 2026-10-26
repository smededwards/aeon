<?php
/**
 * Reversible native block inserter controls.
 *
 * @package Aeon
 */

namespace Aeon\Features;

use Aeon\Settings;

/** Limits editor choices without unregistering blocks or altering saved content. */
final class Editor {

	/** Content, layout and nested blocks used by the portfolio. */
	private const BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/code',
		'core/table',
		'core/image',
		'core/cover',
		'core/media-text',
		'core/columns',
		'core/column',
		'core/group',
		'core/buttons',
		'core/button',
		'core/separator',
		'core/spacer',
		'core/site-logo',
		'core/site-tagline',
		'core/site-title',
		'core/navigation',
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list',
		'core/page-list-item',
		'core/block',
		'core/query',
		'core/post-template',
		'core/post-title',
		'core/post-date',
		'core/post-excerpt',
		'core/post-featured-image',
		'core/query-no-results',
		'core/query-pagination',
		'core/query-pagination-next',
		'core/query-pagination-numbers',
		'core/query-pagination-previous',
	);

	/**
	 * Receive feature preferences.
	 *
	 * @param Settings $settings Site settings.
	 */
	public function __construct( private readonly Settings $settings ) {}

	/** Register the optional native inserter filter. */
	public function register(): void {
		add_filter( 'block_editor_settings_all', array( $this, 'media_settings' ), 100 );
		if ( $this->settings->is_enabled( 'simplify_blocks' ) ) {
			add_filter( 'allowed_block_types_all', array( $this, 'allowed_blocks' ), 100, 2 );
		}
	}

	/**
	 * Hide the native Openverse source without changing other media settings.
	 *
	 * @param array $settings Current editor settings.
	 * @return array Updated editor settings.
	 */
	public function media_settings( array $settings ): array {
		if ( $this->settings->is_enabled( 'hide_openverse' ) ) {
			$settings['enableOpenverseMediaCategory'] = false;
		}
		return $settings;
	}

	/**
	 * Restrict supported content editors while respecting earlier restrictions.
	 *
	 * @param bool|string[]            $allowed Existing allowed block types.
	 * @param \WP_Block_Editor_Context $context Current editor context.
	 * @return bool|string[] Effective allowed block types.
	 */
	public function allowed_blocks( $allowed, \WP_Block_Editor_Context $context ) {
		if ( ! $context->post || ! in_array( $context->post->post_type, array( 'post', 'page', 'wp_block' ), true ) ) {
			return $allowed;
		}
		if ( false === $allowed ) {
			return false;
		}
		return is_array( $allowed ) ? array_values( array_intersect( $allowed, self::BLOCKS ) ) : self::BLOCKS;
	}
}
