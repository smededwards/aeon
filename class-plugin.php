<?php
/**
 * Plugin composition and hook registration.
 *
 * @package Aeon
 */

namespace Aeon;

use Aeon\Features\Contact;
use Aeon\Admin\SettingsPage;
use Aeon\Features\Navigation;
use Aeon\Features\Emoji;
use Aeon\Features\Comments;
use Aeon\Features\Seo;
use Aeon\Admin\Seo as SeoAdmin;
use Aeon\Features\Patterns;
use Aeon\Features\Editor;

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
		( new Contact() )->register();
		( new Navigation( $settings ) )->register();
		( new Emoji() )->register();
		( new Comments( $settings ) )->register();
		( new Seo( $settings ) )->register();
		( new SeoAdmin( $settings ) )->register();
		( new Patterns( $settings ) )->register();
		( new Editor( $settings ) )->register();
		( new SettingsPage( $settings ) )->register();
	}
}
