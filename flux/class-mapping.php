<?php
/**
 * Flux record mappings.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

/**
 * Reads and writes cross-environment record identities.
 */
final class Mapping {
	/**
	 * Find a mapping for a local record.
	 *
	 * @param string $post_type Post type.
	 * @param int    $local_id Local post ID.
	 * @param string $remote_url Remote environment URL.
	 * @return object|null
	 */
	public static function find_local( string $post_type, int $local_id, string $remote_url ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT * FROM %i WHERE post_type = %s AND local_id = %d AND remote_url = %s',
				Database::mappings_table(),
				$post_type,
				$local_id,
				$remote_url
			)
		);
	}

	/**
	 * Save a mapping and its last-synced payload snapshot.
	 *
	 * @param string $post_type Post type.
	 * @param int    $local_id Local post ID.
	 * @param int    $remote_id Remote post ID.
	 * @param string $remote_url Remote environment URL.
	 * @param array  $snapshot Field snapshot.
	 * @return void
	 */
	public static function save( string $post_type, int $local_id, int $remote_id, string $remote_url, array $snapshot ): void {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'INSERT INTO %i (post_type, local_id, remote_id, remote_url, field_snapshot, created_at, updated_at) VALUES (%s, %d, %d, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE remote_id = VALUES(remote_id), field_snapshot = VALUES(field_snapshot), updated_at = VALUES(updated_at)',
				Database::mappings_table(),
				$post_type,
				$local_id,
				$remote_id,
				$remote_url,
				wp_json_encode( $snapshot ),
				$now,
				$now
			)
		);
	}
}
