<?php
/**
 * Flux settings.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use Aeon\Admin\Environment;

/**
 * Registers the private local/production connection.
 */
final class Settings {
	public const OPTION = 'aeon_flux_connection';

	public const DEFAULT_ENVIRONMENT = 'prod';

	/**
	 * Register the Flux option with the Settings API.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the Flux option.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'aeon_flux', self::OPTION, array( $this, 'sanitize' ) );
	}

	/**
	 * Render connection controls.
	 *
	 * @return void
	 */
	public function render(): void {
		$settings     = get_option( self::OPTION, array() );
		$environment  = isset( $settings['environment'] ) ? $settings['environment'] : self::DEFAULT_ENVIRONMENT;
		$token        = isset( $settings['token'] ) ? Crypto::decrypt( $settings['token'] ) : '';
		$enabled      = ! empty( $settings['enabled'] );
		$allow_push   = ! empty( $settings['allow_push'] );
		$url          = $this->connection( $environment )['url'];
		$environments = Environment::choices();
		$token_set    = '' !== $token;

		require __DIR__ . '/views/settings.php';
	}

	/**
	 * Return the Flux settings nonce fields for the shared Aeon form.
	 *
	 * @return string
	 */
	public function fields(): string {
		ob_start();
		settings_fields( 'aeon_flux' );
		return (string) ob_get_clean();
	}

	/**
	 * Render the legacy standalone form for compatibility with direct callers.
	 *
	 * @return void
	 */
	public function render_standalone(): void {
		$fields = $this->fields();
		printf( '<div class="wrap"><h1>%1$s</h1><form method="post" action="options.php">%2$s', esc_html__( 'Flux', 'aeon' ), $fields ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->render();
		printf( '<p>%s</p></form></div>', get_submit_button( esc_html__( 'Save Changes', 'aeon' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Sanitize and encrypt submitted settings.
	 *
	 * @param mixed $value Raw option value.
	 * @return array<string,string|int>
	 */
	public function sanitize( $value ): array {
		$value       = is_array( $value ) ? $value : array();
		$token       = isset( $value['token'] ) ? sanitize_text_field( $value['token'] ) : '';
		$old         = get_option( self::OPTION, array() );
		$environment = isset( $value['environment'] ) ? sanitize_key( $value['environment'] ) : ( $old['environment'] ?? self::DEFAULT_ENVIRONMENT );
		$environment = Environment::normalise_environment( $environment ) ?? self::DEFAULT_ENVIRONMENT;

		return array(
			'enabled'     => empty( $value['enabled'] ) ? 0 : 1,
			'allow_push'  => empty( $value['allow_push'] ) ? 0 : 1,
			'environment' => $environment,
			'token'       => $token ? Crypto::encrypt( $token ) : ( $old['token'] ?? '' ),
		);
	}

	/**
	 * Return decrypted connection settings.
	 *
	 * @param string|null $requested_environment Optional environment override.
	 * @return array<string,string|int>
	 */
	public function connection( ?string $requested_environment = null ): array {
		$value       = get_option( self::OPTION, array() );
		$environment = Environment::normalise_environment( $requested_environment ?? ( $value['environment'] ?? self::DEFAULT_ENVIRONMENT ) ) ?? self::DEFAULT_ENVIRONMENT;
		$urls        = Environment::urls();
		$url         = isset( $urls[ $environment ] ) ? $urls[ $environment ] : '';
		if ( ! $url && Environment::detected_environment() === $environment ) {
			$url = home_url( '/' );
		}

		return array(
			'enabled'     => empty( $value['enabled'] ) ? 0 : 1,
			'allow_push'  => empty( $value['allow_push'] ) ? 0 : 1,
			'environment' => $environment,
			'url'         => untrailingslashit( $url ),
			'token'       => isset( $value['token'] ) ? Crypto::decrypt( $value['token'] ) : '',
		);
	}
}
