<?php
/**
 * Disable WordPress emoji asset injection.
 *
 * @package Aeon
 */

namespace Aeon\Features;

/**
 * Removes emoji detection scripts, inline styles and image conversion hooks.
 */
final class Emoji {

	/**
	 * Register the emoji cleanup before WordPress renders page assets.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'remove_core_hooks' ) );
	}

	/**
	 * Remove WordPress emoji scripts, styles and static-image conversion hooks.
	 *
	 * @return void
	 */
	public function remove_core_hooks(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'embed_head', 'print_emoji_detection_script' );

		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}
}
