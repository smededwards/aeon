<?php
/**
 * Flux post payloads.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

/**
 * Builds the deliberately small, editable record representation.
 */
final class Payload {

	/**
	 * Build a synchronisable payload for a Post or Page.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string,mixed>
	 */
	public static function from_post( \WP_Post $post ): array {
		$meta = array();
		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last', '_wp_old_slug' ), true ) || str_starts_with( $key, '_aeon_flux' ) ) {
				continue;
			}
			$meta[ $key ] = array_map( 'maybe_unserialize', $values );
		}

		$taxonomies = array();
		foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
			$terms                   = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'slugs' ) );
			$taxonomies[ $taxonomy ] = is_wp_error( $terms ) ? array() : $terms;
		}

		$media = array();
		if ( $post->post_type && has_post_thumbnail( $post->ID ) ) {
			$media[] = self::media( (int) get_post_thumbnail_id( $post->ID ), true );
		}
		preg_match_all( '/wp-image-(\d+)/', $post->post_content, $matches );
		foreach ( array_unique( array_map( 'absint', $matches[1] ) ) as $attachment_id ) {
			$media[] = self::media( $attachment_id );
		}

		return array(
			'protocol'     => 'flux/v1',
			'post_type'    => $post->post_type,
			'source_id'    => (int) $post->ID,
			'source_guid'  => $post->guid,
			'modified_gmt' => $post->post_modified_gmt,
			'fields'       => array(
				'title'    => $post->post_title,
				'content'  => $post->post_content,
				'excerpt'  => $post->post_excerpt,
				'status'   => $post->post_status,
				'date'     => $post->post_date,
				'date_gmt' => $post->post_date_gmt,
				'author'   => (int) $post->post_author,
				'template' => get_page_template_slug( $post->ID ),
			),
			'meta'         => $meta,
			'taxonomies'   => $taxonomies,
			'media'        => $media,
		);
	}

	/**
	 * Return the stable field snapshot used for conflict comparison.
	 *
	 * @param array<string,mixed> $payload Record payload.
	 * @return array<string,mixed>
	 */
	public static function snapshot( array $payload ): array {
		return array(
			'fields'     => $payload['fields'],
			'meta'       => $payload['meta'],
			'taxonomies' => $payload['taxonomies'],
			'media'      => $payload['media'],
		);
	}

	/**
	 * Build a compact attachment reference.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $featured Whether this is the record's featured image.
	 * @return array<string,mixed>
	 */
	private static function media( int $attachment_id, bool $featured = false ): array {
		$file = get_attached_file( $attachment_id );

		return array(
			'id'       => $attachment_id,
			'url'      => wp_get_attachment_url( $attachment_id ),
			'name'     => basename( (string) $file ),
			'hash'     => $file && is_readable( $file ) ? hash_file( 'sha256', $file ) : '',
			'featured' => $featured,
		);
	}
}
