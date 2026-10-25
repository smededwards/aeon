<?php
/**
 * Plugin composition and hook registration.
 *
 * @package Aeon
 */

namespace Aeon;

use Aeon\Admin\SettingsPage;
use Aeon\Features\Navigation;

/**
 * Wires the plugin's services without changing behaviour in constructors.
 */
final class Plugin {
	/**
	 * Register the plugin's features and administration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$settings = new Settings();
		( new Navigation( $settings ) )->register();
		( new SettingsPage( $settings ) )->register();
	}
}
