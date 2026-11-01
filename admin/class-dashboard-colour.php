<?php
/**
 * Environment-aware WordPress dashboard accents.
 *
 * @package Aeon
 */

namespace Aeon\Admin;

use Aeon\Plugin;

/**
 * Applies an optional dashboard accent based on the Bedrock environment.
 */
final class DashboardColour {
	// Settings API option name.
	public const OPTION = 'aeon_dashboard_environment';

	// Use WordPress blue when no environment accent is selected.
	public const DEFAULT_SELECTION = 'default';

	// Environment accent colours.
	private const COLOURS = array(
		'production'  => '#E93845',
		'local'       => '#269B50',
		'development' => '#E98220',
	);

	/**
	 * Register dashboard and editor styles.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_styles' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_canvas_styles' ), 100 );
	}

	/**
	 * Validate the selected environment override.
	 *
	 * @param mixed $selection Submitted option value.
	 * @return string Sanitised selection or the default WordPress palette.
	 */
	public static function sanitise_selection( mixed $selection ): string {
		if ( 'environment' === $selection ) {
			return self::DEFAULT_SELECTION;
		}

		$allowed = array_merge( array( self::DEFAULT_SELECTION ), array_keys( self::COLOURS ) );

		return is_string( $selection ) && in_array( $selection, $allowed, true )
			? $selection
			: self::DEFAULT_SELECTION;
	}

	/**
	 * Read the saved selection, defaulting to the WordPress blue accent.
	 *
	 * @return string Saved environment or default selection.
	 */
	public static function selection(): string {
		return self::sanitise_selection( get_option( self::OPTION, self::DEFAULT_SELECTION ) );
	}

	/**
	 * Detect the Bedrock environment, then fall back to WordPress core's type.
	 *
	 * @return string|null Detected environment when supported.
	 */
	public static function detected_environment(): ?string {
		return Environment::detected_environment();
	}

	/**
	 * Enqueue the dashboard accent stylesheet.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		$this->enqueue_accent_styles();
	}

	/**
	 * Enqueue the environment palette in the block editor interface.
	 *
	 * @return void
	 */
	public function enqueue_editor_styles(): void {
		$this->enqueue_accent_styles();
	}

	/**
	 * Apply the environment palette to the block editor canvas without affecting the front end.
	 *
	 * @return void
	 */
	public function enqueue_editor_canvas_styles(): void {
		if ( is_admin() ) {
			$this->enqueue_accent_styles();
		}
	}

	/**
	 * Enqueue the selected external palette and shared dashboard rules.
	 *
	 * @return void
	 */
	private function enqueue_accent_styles(): void {
		$environment = self::selection();
		if ( self::DEFAULT_SELECTION === $environment ) {
			return;
		}

		$palette_file = sprintf( 'dashboard-colours-%s.css', $environment );
		$palette_path = dirname( __DIR__ ) . '/build/' . $palette_file;
		$base_path    = dirname( __DIR__ ) . '/build/dashboard-colours.css';

		wp_enqueue_style(
			'aeon-dashboard-environment',
			plugins_url( '../build/' . $palette_file, __FILE__ ),
			array( 'colors' ),
			is_file( $palette_path ) ? (string) filemtime( $palette_path ) : Plugin::VERSION
		);
		wp_enqueue_style(
			'aeon-dashboard-colours',
			plugins_url( '../build/dashboard-colours.css', __FILE__ ),
			array( 'aeon-dashboard-environment' ),
			is_file( $base_path ) ? (string) filemtime( $base_path ) : Plugin::VERSION
		);
	}
}
