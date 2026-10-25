<?php
/**
 * Classic navigation menu support.
 *
 * @package Aeon
 */

namespace Aeon\Features;

use Aeon\Settings;

/**
 * Exposes the core Menus screen for hybrid themes.
 */
final class Navigation {
	/**
	 * Receive feature preferences.
	 *
	 * @param Settings $settings Site settings.
	 */
	public function __construct( private readonly Settings $settings ) {}

	/**
	 * Register after theme setup so theme-defined support is respected.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'enable_support' ), 20 );
	}

	/**
	 * Add support when requested; never remove support from another provider.
	 *
	 * @return void
	 */
	public function enable_support(): void {
		if ( $this->settings->navigation_enabled() ) {
			add_theme_support( 'menus' );
		}
	}
}
