<?php
/**
 * Default featured image for Posts and Pages.
 *
 * @package Aeon
 */

namespace Aeon\Features;

/** Supplies a configured image only when content has no featured image. */
final class DefaultFeaturedImage {
	/** Option storing the fallback image attachment ID. */
	public const OPTION = 'aeon_default_featured_image';

	/**
	 * Register the default featured-image filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'post_thumbnail_id', array( $this, 'default_thumbnail' ), 10, 2 );
	}

	/**
	 * Return the configured fallback for Posts and Pages without a thumbnail.
	 *
	 * @param int|false         $thumbnail_id Current featured image attachment ID.
	 * @param int|\WP_Post|null $post Post whose featured image is being retrieved.
	 * @return int|false Existing or configured attachment ID.
	 */
	public function default_thumbnail( int|false $thumbnail_id, int|\WP_Post|null $post ): int|false {
		if ( $thumbnail_id || ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return $thumbnail_id;
		}

		$image_id = self::image_id();
		return $image_id ? $image_id : $thumbnail_id;
	}

	/**
	 * Sanitise and store only an existing image attachment ID.
	 *
	 * @param mixed $value Submitted attachment identifier.
	 * @return int Valid image attachment ID, or zero when no image is selected.
	 */
	public static function sanitise_image( mixed $value ): int {
		$image_id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return $image_id && self::is_valid_image( $image_id ) ? $image_id : 0;
	}

	/**
	 * Find the existing placeholder.png attachment used as the initial setting.
	 *
	 * @return int Attachment ID, or zero when the Media Library has no exact match.
	 */
	public static function default_image_id(): int {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => array( 'inherit', 'publish' ),
				'post_mime_type' => 'image',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'name'           => 'placeholder',
			)
		);
		foreach ( $attachments as $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( $file && 'placeholder.png' === strtolower( basename( $file ) ) && self::is_valid_image( $attachment_id ) ) {
				return (int) $attachment_id;
			}
		}
		return 0;
	}

	/**
	 * Read the configured image only while its attachment remains available.
	 *
	 * @return int Valid image attachment ID, or zero when the setting is empty or stale.
	 */
	public static function image_id(): int {
		static $configured_image_id = null;
		if ( null === $configured_image_id ) {
			$default_image_id    = self::default_image_id();
			$image_id            = absint( get_option( self::OPTION, $default_image_id ) );
			$configured_image_id = self::is_valid_image( $image_id ) ? $image_id : 0;
		}
		return $configured_image_id;
	}

	/**
	 * Check that an identifier refers to a published or inherited image attachment.
	 *
	 * @param int $image_id Attachment identifier.
	 * @return bool Whether the attachment is a usable image.
	 */
	private static function is_valid_image( int $image_id ): bool {
		if ( $image_id < 1 ) {
			return false;
		}

		$attachment = get_post( $image_id );
		return $attachment instanceof \WP_Post
			&& 'attachment' === $attachment->post_type
			&& in_array( $attachment->post_status, array( 'inherit', 'publish' ), true )
			&& wp_attachment_is_image( $image_id )
			&& (bool) wp_get_attachment_url( $image_id );
	}
}
