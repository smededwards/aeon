<?php
/**
 * Plugin composition and hook registration.
 *
 * @package Aeon
 */

namespace Aeon;

use Aeon\Admin\DashboardColour;
use Aeon\Admin\Environment;
use Aeon\Admin\SettingsPage;
use Aeon\Features\Comments;
use Aeon\Features\Contact;
use Aeon\Features\DefaultFeaturedImage;
use Aeon\Features\Editor;
use Aeon\Features\Emoji;
use Aeon\Features\MediaUploads;
use Aeon\Features\Patterns;
use Aeon\Features\Seo;
use Aeon\Admin\Seo as SeoAdmin;

/**
 * Wires the plugin's services without changing behaviour in constructors.
 */
final class Plugin {
	// Plugin release version, also used for inline dashboard styles.
	public const VERSION = '0.2.1';

	/**
	 * Register the plugin's features and administration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$settings = new Settings();
		( new Contact() )->register();
		( new Emoji() )->register();
		( new Comments( $settings ) )->register();
		( new Seo( $settings ) )->register();
		( new SeoAdmin( $settings ) )->register();
		( new DefaultFeaturedImage() )->register();
		( new Patterns( $settings ) )->register();
		( new Editor( $settings ) )->register();
		( new MediaUploads() )->register();
		( new SettingsPage( $settings ) )->register();
		( new DashboardColour() )->register();
		( new Environment() )->register();
	}
}
