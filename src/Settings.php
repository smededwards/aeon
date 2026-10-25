<?php
/**
 * Persisted core feature preferences.
 *
 * @package Aeon
 */

namespace Aeon;

/**
 * Reads and sanitises the site's Aeon settings.
 */
final class Settings {
	/** Option key shared with the WordPress Settings API. */
	public const OPTION = 'aeon_core_features';

	/**
	 * Determine whether Aeon should enable classic menu support.
	 *
	 * @return bool
	 */
	public function navigation_enabled(): bool {
		$options = get_option( self::OPTION, array() );
		if ( ! is_array( $options ) || ! array_key_exists( 'navigation', $options ) ) {
			return true;
		}
		return in_array( $options['navigation'], array( true, 1, '1' ), true );
	}

	/**
	 * Accept only the known boolean setting; discard unknown keys.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, bool>
	 */
	public function sanitise( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array( 'navigation' => $this->navigation_enabled() );
		}
		return array(
			'navigation' => isset( $input['navigation'] ) && in_array( $input['navigation'], array( true, 1, '1' ), true ),
		);
	}
}
