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

	public const DEFAULT_ENVIRONMENT = 'production';

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
		$environments = array(
			'local'       => __( 'Local', 'aeon' ),
			'development' => __( 'Development', 'aeon' ),
			'production'  => __( 'Production', 'aeon' ),
		);
		$options      = '';
		foreach ( $environments as $key => $label ) {
			$options .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $environment, $key, false ), esc_html( $label ) );
		}

		printf( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'<table class="form-table" role="presentation"><tr><th scope="row"><label for="aeon-flux-enabled">%1$s</label></th><td><label for="aeon-flux-enabled"><input type="checkbox" id="aeon-flux-enabled" name="%2$s[enabled]" value="1"%3$s /> <span class="description">%4$s</span></label></td></tr><tr><th scope="row"><label for="aeon-flux-push">%5$s</label></th><td><label for="aeon-flux-push"><input type="checkbox" id="aeon-flux-push" name="%2$s[allow_push]" value="1"%6$s /> <span class="description">%7$s</span></label></td></tr><tr><th scope="row"><label for="aeon-flux-environment">%8$s</label></th><td><select id="aeon-flux-environment" name="%2$s[environment]">%9$s</select><p class="description">%10$s</p></td></tr><tr><th scope="row"><label for="aeon-flux-token">%11$s</label></th><td><input class="regular-text" type="password" id="aeon-flux-token" name="%2$s[token]" value="%12$s" autocomplete="new-password" required /><p class="description">%13$s</p></td></tr></table>',
			esc_html__( 'Enable Flux', 'aeon' ),
			esc_attr( self::OPTION ),
			checked( $enabled, true, false ),
			esc_html__( 'Allow the editor to connect to the configured environment.', 'aeon' ),
			esc_html__( 'Allow Remote Writes', 'aeon' ),
			checked( $allow_push, true, false ),
			esc_html__( 'Permit signed local-to-production updates after explicit editor confirmation.', 'aeon' ),
			esc_html__( 'Remote Environment', 'aeon' ),
			$options, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Uses the corresponding URL configured in Aeon Dashboard settings.', 'aeon' ),
			esc_html__( 'Shared Connection Token', 'aeon' ),
			esc_attr( $token ),
			esc_html__( 'The token is encrypted with the WordPress authentication salts before storage.', 'aeon' )
		);
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
		$environment = in_array( $environment, Environment::ENVIRONMENTS, true ) ? $environment : self::DEFAULT_ENVIRONMENT;

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
	 * @return array<string,string|int>
	 */
	public function connection(): array {
		$value       = get_option( self::OPTION, array() );
		$environment = isset( $value['environment'] ) ? $value['environment'] : self::DEFAULT_ENVIRONMENT;
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
