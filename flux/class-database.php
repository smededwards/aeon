<?php
/**
 * Flux persistence.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

/**
 * Owns Flux's private mapping and operation tables.
 */
final class Database {
	private const VERSION = 1;
	/**
	 * Create or update Flux tables.
	 *
	 * @return void
	 */
	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset    = $wpdb->get_charset_collate();
		$mappings   = self::mappings_table();
		$operations = self::operations_table();

		dbDelta(
			"CREATE TABLE {$mappings} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_type varchar(20) NOT NULL,
				local_id bigint(20) unsigned NOT NULL,
				remote_id bigint(20) unsigned NOT NULL,
				remote_url varchar(255) NOT NULL,
				field_snapshot longtext NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY local_mapping (post_type, local_id, remote_url(191)),
				KEY remote_mapping (post_type, remote_id, remote_url(191))
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$operations} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				request_id varchar(64) NOT NULL,
				direction varchar(10) NOT NULL,
				post_type varchar(20) NOT NULL,
				local_id bigint(20) unsigned NOT NULL DEFAULT 0,
				remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY request_id (request_id),
				KEY record_operation (post_type, local_id, remote_id)
			) {$charset};"
		);
		update_option( 'aeon_flux_schema_version', self::VERSION, false );
	}

	/**
	 * Apply schema changes for an already-active Aeon installation.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( 'aeon_flux_schema_version', 0 ) < self::VERSION ) {
			self::activate();
		}
	}

	/**
	 * Get the mappings table name.
	 *
	 * @return string
	 */
	public static function mappings_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'aeon_flux_mappings';
	}

	/**
	 * Get the operations table name.
	 *
	 * @return string
	 */
	public static function operations_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'aeon_flux_operations';
	}

	/**
	 * Record a non-content operation result.
	 *
	 * @param string $request_id Request identifier.
	 * @param string $direction Sync direction.
	 * @param string $post_type Post type.
	 * @param int    $local_id Local post ID.
	 * @param int    $remote_id Remote post ID.
	 * @param string $status Operation status.
	 * @return void
	 */
	public static function log( string $request_id, string $direction, string $post_type, int $local_id, int $remote_id, string $status ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::operations_table(),
			array(
				'request_id' => $request_id,
				'direction'  => $direction,
				'post_type'  => $post_type,
				'local_id'   => $local_id,
				'remote_id'  => $remote_id,
				'status'     => $status,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}
}
