<?php
/**
 * Flux media matching and import.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

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
		if ( ! $url ) {
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
		}

		return $attachment_id;
	}

	/**
	 * Find a local attachment by content hash, scanning the library once.
	 *
	 * @param string $remote_hash Remote attachment hash.
	 * @return int Matching attachment ID, or zero when none exists.
	 */
	private static function find_by_hash( string $remote_hash ): int {
		if ( self::$attachment_hashes === null ) {
			self::$attachment_hashes = array();
			$attachments             = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $attachments as $attachment_id ) {
				$file = get_attached_file( $attachment_id );
				if ( $file && is_readable( $file ) ) {
					self::$attachment_hashes[ hash_file( 'sha256', $file ) ] = (int) $attachment_id;
				}
			}
		}

		return self::$attachment_hashes[ $remote_hash ] ?? 0;
	}
}
