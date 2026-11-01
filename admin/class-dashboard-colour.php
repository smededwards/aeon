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
	 * @return string
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
	 * @return string
	 */
	public static function selection(): string {
		return self::sanitise_selection( get_option( self::OPTION, self::DEFAULT_SELECTION ) );
	}

	/**
	 * Detect the Bedrock environment, then fall back to WordPress core's type.
	 *
	 * @return string|null
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
		$environment = $this->active_environment();

		if ( null === $environment ) {
			return;
		}

		$this->enqueue_accent_styles( $this->dashboard_variables_css( $environment ) );
	}

	/**
	 * Enqueue the environment palette in the block editor interface.
	 *
	 * @return void
	 */
	public function enqueue_editor_styles(): void {
		$environment = $this->active_environment();

		if ( null === $environment ) {
			return;
		}

		$css = $this->dashboard_variables_css( $environment );
		$this->enqueue_accent_styles( $css );

		foreach ( array( 'wp-edit-post', 'wp-edit-site', 'wp-edit-widgets' ) as $handle ) {
			wp_add_inline_style( $handle, $css );
		}
	}

	/**
	 * Apply the environment palette to the block editor canvas without affecting the front end.
	 *
	 * @return void
	 */
	public function enqueue_editor_canvas_styles(): void {
		if ( ! is_admin() ) {
			return;
		}

		$environment = $this->active_environment();

		if ( null === $environment ) {
			return;
		}

		wp_add_inline_style( 'wp-edit-blocks', $this->dashboard_variables_css( $environment ) );
	}

	/**
	 * Return the detected environment or a manually selected override.
	 *
	 * @return string|null Null means retain the default WordPress blue.
	 */
	private function active_environment(): ?string {
		$selection = self::selection();

		return self::DEFAULT_SELECTION === $selection ? null : $selection;
	}

	/**
	 * Enqueue the selected palette after WordPress' admin colour scheme.
	 *
	 * @param string $css Dynamic environment variables.
	 * @return void
	 */
	private function enqueue_accent_styles( string $css ): void {
		if ( wp_style_is( 'aeon-dashboard-environment', 'enqueued' ) ) {
			return;
		}

		$style_path    = dirname( __DIR__ ) . '/build/dashboard-colours.css';
		$style_version = is_file( $style_path ) ? (string) filemtime( $style_path ) : Plugin::VERSION;

		wp_register_style(
			'aeon-dashboard-environment',
			plugins_url( '../build/dashboard-colours.css', __FILE__ ),
			array( 'colors' ),
			$style_version
		);
		wp_enqueue_style( 'aeon-dashboard-environment' );
		wp_add_inline_style( 'aeon-dashboard-environment', $css );
	}

	/**
	 * Build the dynamic WordPress colour variables for the selected environment.
	 *
	 * @param string $environment Active environment key.
	 * @return string
	 */
	private function dashboard_variables_css( string $environment ): string {
		$colour        = self::COLOURS[ $environment ];
		$rgb           = self::hex_to_rgb( $colour );
		$darker_10     = self::adjust_lightness( $rgb, -0.05 );
		$darker_20     = self::adjust_lightness( $rgb, -0.10 );
		$focus         = self::adjust_lightness( $rgb, 0.10 );
		$menu_focus    = self::adjust_hsl( $rgb, 0.1745, 0.1991, 0.00362 );
		$mix_darker_10 = array_map( static fn( int $channel ): int => (int) round( $channel * 0.9 ), $rgb );

		return sprintf(
			<<<'CSS'
			:root, body, .interface-interface-skeleton, .editor-styles-wrapper {
				--wp-admin-theme-color: %1$s !important;
				--wp-admin-theme-color--rgb: %2$s !important;
				--wp-admin-theme-color-darker-10: %3$s !important;
				--wp-admin-theme-color-darker-10--rgb: %4$s !important;
				--wp-admin-theme-color-darker-20: %5$s !important;
				--wp-admin-theme-color-darker-20--rgb: %6$s !important;
				--wp-components-color-accent: %1$s !important;
				--wp-components-color-accent--rgb: %2$s !important;
				--wp-components-color-accent-darker-10: %3$s !important;
				--wp-components-color-accent-darker-20: %5$s !important;
				--aeon-dashboard-accent: %1$s;
				--aeon-dashboard-accent-hover: %7$s;
				--aeon-dashboard-accent-focus: %8$s;
				--aeon-dashboard-accent-menu: %9$s;
				--aeon-dashboard-accent-mix-dark-10: %10$s;
			}
			CSS,
			$colour,
			self::format_rgb( $rgb ),
			$darker_10['hex'],
			self::format_rgb( $darker_10['rgb'] ),
			$darker_20['hex'],
			self::format_rgb( $darker_20['rgb'] ),
			$darker_20['hex'],
			$focus['hex'],
			$menu_focus['hex'],
			self::format_rgb( $mix_darker_10 )
		);
	}

	/**
	 * Convert a trusted hexadecimal palette colour to RGB channels.
	 *
	 * @param string $colour Six-digit hexadecimal colour.
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function hex_to_rgb( string $colour ): array {
		return array_map( 'hexdec', str_split( substr( $colour, 1 ), 2 ) );
	}

	/**
	 * Adjust HSL lightness to match WordPress' admin-scheme token shades.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb       Base colour channels.
	 * @param float                         $lightness Lightness adjustment from -1 to 1.
	 * @return array{hex: string, rgb: array{0: int, 1: int, 2: int}}
	 */
	private static function adjust_lightness( array $rgb, float $lightness ): array {
		return self::adjust_hsl( $rgb, $lightness, 0, 0 );
	}

	/**
	 * Apply HSL adjustments like the WordPress modern scheme's Sass colour functions.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb        Base colour channels.
	 * @param float                         $lightness  Lightness adjustment from -1 to 1.
	 * @param float                         $saturation Saturation adjustment from -1 to 1.
	 * @param float                         $hue        Hue adjustment in turns.
	 * @return array{hex: string, rgb: array{0: int, 1: int, 2: int}}
	 */
	private static function adjust_hsl( array $rgb, float $lightness, float $saturation, float $hue ): array {
		$channels = array_map( static fn( int $channel ): float => $channel / 255, $rgb );
		$max      = max( $channels );
		$min      = min( $channels );
		$delta    = $max - $min;
		$h        = 0.0;
		$l        = ( $max + $min ) / 2;
		$s        = 0.0;

		if ( 0.0 !== $delta ) {
			$s = $delta / ( 1 - abs( 2 * $l - 1 ) );

			if ( $max === $channels[0] ) {
				$h = fmod( ( $channels[1] - $channels[2] ) / $delta, 6 );
			} elseif ( $max === $channels[1] ) {
				$h = ( $channels[2] - $channels[0] ) / $delta + 2;
			} else {
				$h = ( $channels[0] - $channels[1] ) / $delta + 4;
			}

			$h /= 6;
		}

		$h = fmod( $h + $hue + 1, 1 );
		$l = max( 0, min( 1, $l + $lightness ) );
		$s = max( 0, min( 1, $s + $saturation ) );

		$chroma = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x      = $chroma * ( 1 - abs( fmod( $h * 6, 2 ) - 1 ) );
		$m      = $l - $chroma / 2;

		if ( $h < 1 / 6 ) {
			$adjusted = array( $chroma, $x, 0 );
		} elseif ( $h < 2 / 6 ) {
			$adjusted = array( $x, $chroma, 0 );
		} elseif ( $h < 3 / 6 ) {
			$adjusted = array( 0, $chroma, $x );
		} elseif ( $h < 4 / 6 ) {
			$adjusted = array( 0, $x, $chroma );
		} elseif ( $h < 5 / 6 ) {
			$adjusted = array( $x, 0, $chroma );
		} else {
			$adjusted = array( $chroma, 0, $x );
		}

		$adjusted = array_map( static fn( float $channel ): int => (int) round( ( $channel + $m ) * 255 ), $adjusted );

		return array(
			'hex' => sprintf( '#%02X%02X%02X', ...$adjusted ),
			'rgb' => $adjusted,
		);
	}

	/**
	 * Format RGB channels for a CSS custom property.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb Colour channels.
	 * @return string
	 */
	private static function format_rgb( array $rgb ): string {
		return implode( ', ', $rgb );
	}
}
