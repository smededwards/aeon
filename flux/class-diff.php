<?php
/**
 * Flux field comparisons.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

/**
 * Produces a small field-level diff for the editor preview.
 */
final class Diff {
	/**
	 * Compare two record snapshots.
	 *
	 * @param array<string,mixed> $local Local payload.
	 * @param array<string,mixed> $remote Remote payload.
	 * @return array<string,array{local:mixed,remote:mixed}>
	 */
	public static function compare( array $local, array $remote ): array {
		$changes = array();
		foreach ( array( 'fields', 'meta', 'taxonomies', 'media' ) as $group ) {
			$local_values  = isset( $local[ $group ] ) && is_array( $local[ $group ] ) ? $local[ $group ] : array();
			$remote_values = isset( $remote[ $group ] ) && is_array( $remote[ $group ] ) ? $remote[ $group ] : array();
			foreach ( array_unique( array_merge( array_keys( $local_values ), array_keys( $remote_values ) ) ) as $key ) {
				$old = $local_values[ $key ] ?? null;
				$new = $remote_values[ $key ] ?? null;
				if ( wp_json_encode( $old ) !== wp_json_encode( $new ) ) {
					$changes[ $group . '.' . $key ] = array(
						'local'  => $old,
						'remote' => $new,
					);
				}
			}
		}

		return $changes;
	}
}
