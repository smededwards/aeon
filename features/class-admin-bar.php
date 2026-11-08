<?php
/**
 * Front-end admin-bar visibility controls.
 *
 * @package Aeon
 */

namespace Aeon\Features;

/**
 * Hides the front-end toolbar while scrolling down and restores it on upward scroll.
 */
final class AdminBar {
	/**
	 * Register the front-end asset hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Enqueue the enhancement only when the current visitor can see the toolbar.
	 *
	 * @return void
	 */
	public function assets(): void {
		if ( ! is_user_logged_in() || ! is_admin_bar_showing() ) {
			return;
		}

		$base = dirname( __DIR__ ) . '/build/admin-bar-scroll';
		if ( ! is_readable( $base . '.asset.php' ) ) {
			return;
		}

		$asset = require $base . '.asset.php';
		wp_enqueue_script(
			'aeon-admin-bar-scroll',
			plugins_url( 'build/admin-bar-scroll.js', dirname( __DIR__ ) . '/aeon.php' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'aeon-admin-bar-scroll',
			plugins_url( 'build/admin-bar-scroll.css', dirname( __DIR__ ) . '/aeon.php' ),
			array(),
			$asset['version']
		);
	}
}
