<?php
/**
 * Frontend emoji style controls.
 *
 * @package Aeon
 */

namespace Aeon\Features;

/**
 * Removes the frontend emoji stylesheet after WordPress enqueues it.
 */
final class Emoji {

	/**
	 * Register after the core priority-10 enqueue callback.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'remove_styles' ), 20 );
	}

	/**
	 * Dequeue only the frontend emoji stylesheet.
	 *
	 * @return void
	 */
	public function remove_styles(): void {
		wp_dequeue_style( 'wp-emoji-styles' );
	}
}
