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

	/** Default behaviour for known controls; absent new settings stay enabled. */
	public const DEFAULTS = array(
		'seo'               => true,
		'disable_comments'  => true,
		'simplify_blocks'   => true,
		'hide_openverse'    => true,
		'patterns_shortcut' => true,
		'core_patterns'     => true,
		'remote_patterns'   => true,
	);

	/**
	 * Read a known setting without enabling unrecognised features.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public function is_enabled( string $feature ): bool {
		if ( ! array_key_exists( $feature, self::DEFAULTS ) ) {
			return false;
		}
		$options = get_option( self::OPTION, array() );
		if ( ! is_array( $options ) || ! array_key_exists( $feature, $options ) ) {
			return self::DEFAULTS[ $feature ];
		}
		return in_array( $options[ $feature ], array( true, 1, '1' ), true );
	}

	/**
	 * Sanitise known keys while preserving preferences omitted by older forms.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, bool>
	 */
	public function sanitise( mixed $input ): array {
		$clean = array();
		foreach ( array_keys( self::DEFAULTS ) as $feature ) {
			$clean[ $feature ] = is_array( $input ) && array_key_exists( $feature, $input )
				? in_array( $input[ $feature ], array( true, 1, '1' ), true )
				: $this->is_enabled( $feature );
		}
		return $clean;
	}
}
