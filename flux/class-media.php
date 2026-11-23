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
	 * Find or import an attachment reference.
	 *
	 * @param array<string,mixed> $reference Remote media reference.
	 * @return int Local attachment ID, or zero when it cannot be imported.
	 */
	public static function import( array $reference ): int {
		$remote_hash = sanitize_text_field( $reference['hash'] ?? '' );
		if ( $remote_hash ) {
			foreach ( get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			) as $attachment_id ) {
				$file = get_attached_file( $attachment_id );
				if ( $file && is_readable( $file ) && hash_equals( $remote_hash, hash_file( 'sha256', $file ) ) ) {
					return (int) $attachment_id;
				}
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

		return (int) $attachment_id;
	}
}
