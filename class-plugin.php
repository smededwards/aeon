<?php
/**
 * Plugin composition and hook registration.
 *
 * @package Aeon
 */

namespace Aeon;

use Aeon\Admin\DashboardColour;
use Aeon\Admin\Environment;
use Aeon\Admin\Seo as SeoAdmin;
use Aeon\Admin\SettingsPage;
use Aeon\Features\AdminBar;
use Aeon\Features\Comments;
use Aeon\Features\Contact;
use Aeon\Features\DefaultFeaturedImage;
use Aeon\Features\DuplicateContent;
use Aeon\Features\Emoji;
use Aeon\Features\MediaUploads;
use Aeon\Features\Seo;
use Aeon\Flux\Database;
use Aeon\Flux\Editor;
use Aeon\Flux\Rest;
use Aeon\Flux\Settings as FluxSettings;

/**
 * Wires the plugin's services without changing behaviour in constructors.
 */
final class Plugin {
	// Plugin release version, also used to version built assets.
	public const VERSION = '0.4.1';

	/**
	 * Register the plugin's features and administration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		Database::maybe_upgrade();
		$settings = new Settings();
		( new Contact() )->register();
		( new Emoji() )->register();
		( new AdminBar() )->register();
		( new Comments( $settings ) )->register();
		( new Seo( $settings ) )->register();
		( new SeoAdmin( $settings ) )->register();
		( new DefaultFeaturedImage() )->register();
		( new DuplicateContent() )->register();
		( new MediaUploads() )->register();
		$flux_settings = new FluxSettings();
		( new SettingsPage( $settings, $flux_settings ) )->register();
		( new DashboardColour() )->register();
		( new Environment() )->register();
		$flux_settings->register();
		( new Rest( $flux_settings ) )->register();
		( new Editor( $flux_settings ) )->register();
	}
}
