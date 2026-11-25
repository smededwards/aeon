<?php
/**
 * Flux media matching and import.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use Aeon\Admin\Environment;

/**
 * Matches media by content hash and imports missing attachments.
 */
final class Media {
	/**
	 * Attachment IDs indexed by content hash for this request.
	 *
	 * @var array<string,int>|null
	 */
	private static ?array $attachment_hashes = null;

	/**
	 * Find or import an attachment reference.
	 *
	 * @param array<string,mixed> $reference Remote media reference.
	 * @return int Local attachment ID, or zero when it cannot be imported.
	 */
	public static function import( array $reference ): int {
		$remote_hash = sanitize_text_field( $reference['hash'] ?? '' );
		if ( $remote_hash ) {
			$existing_id = self::find_by_hash( $remote_hash );
			if ( $existing_id ) {
				return $existing_id;
			}
		}

		$url = esc_url_raw( $reference['url'] ?? '' );
		if ( ! $url || ! self::is_allowed_url( $url ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file = download_url( $url, 30 );
		if ( is_wp_error( $file ) ) {
			return 0;
		}
		$attachment_id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( $reference['name'] ?? basename( wp_parse_url( $url, PHP_URL_PATH ) ) ),
				'tmp_name' => $file,
			),
			0
		);
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $file );
			return 0;
		}

		$attachment_id = (int) $attachment_id;
		if ( $remote_hash ) {
			self::$attachment_hashes[ $remote_hash ] = $attachment_id;
			update_post_meta( $attachment_id, '_aeon_flux_hash', $remote_hash );
		}

		return $attachment_id;
	}

	/**
	 * Find a local attachment by its indexed content hash.
	 *
	 * @param string $remote_hash Remote attachment hash.
	 * @return int Matching attachment ID, or zero when none exists.
	 */
	private static function find_by_hash( string $remote_hash ): int {
		if ( self::$attachment_hashes === null ) {
			self::$attachment_hashes = array();
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_aeon_flux_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $remote_hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $attachments ) {
			self::$attachment_hashes[ $remote_hash ] = (int) $attachments[0];
		}

		return self::$attachment_hashes[ $remote_hash ] ?? 0;
	}

	/**
	 * Allow media only from configured WordPress environments.
	 *
	 * @param string $url Candidate media URL.
	 * @return bool Whether the URL is safe for Flux to fetch.
	 */
	private static function is_allowed_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$allowed_hosts = array( strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) );
		foreach ( Environment::urls() as $environment_url ) {
			$host = wp_parse_url( $environment_url, PHP_URL_HOST );
			if ( $host ) {
				$allowed_hosts[] = strtolower( (string) $host );
			}
		}

		return in_array( strtolower( $parts['host'] ), array_unique( $allowed_hosts ), true );
	}
}
