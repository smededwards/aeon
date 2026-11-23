<?php
/**
 * Flux local record application.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use WP_Error;

/**
 * Applies a remote payload to a local Post or Page with a revision first.
 */
final class Applier {
	/**
	 * Apply an editable payload to a local record.
	 *
	 * @param int                 $local_id Local post ID.
	 * @param array<string,mixed> $payload Remote payload.
	 * @param array<int,string>   $selected Selected field keys; empty applies all.
	 * @return true|WP_Error
	 */
	public static function apply( int $local_id, array $payload, array $selected = array() ) {
		$post = get_post( $local_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || $post->post_type !== $payload['post_type'] ) {
			return new WP_Error( 'flux_invalid_target', __( 'The local target does not match the remote record.', 'aeon' ) );
		}

		wp_save_post_revision( $local_id );
		$fields      = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : array();
		$use_field   = static function ( string $group, string $key ) use ( $selected ): bool {
			return empty( $selected ) || in_array( $group . '.' . $key, $selected, true );
		};
		$post_values = array( 'ID' => $local_id );
		foreach ( array(
			'title'    => 'post_title',
			'content'  => 'post_content',
			'excerpt'  => 'post_excerpt',
			'status'   => 'post_status',
			'date'     => 'post_date',
			'date_gmt' => 'post_date_gmt',
			'author'   => 'post_author',
		) as $key => $post_key ) {
			if ( $use_field( 'fields', $key ) && isset( $fields[ $key ] ) ) {
				$post_values[ $post_key ] = $key === 'author' ? absint( $fields[ $key ] ) : ( in_array( $key, array( 'content', 'excerpt' ), true ) ? wp_kses_post( $fields[ $key ] ) : sanitize_text_field( $fields[ $key ] ) );
			}
		}
		$updated = wp_update_post( $post_values );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( $post->post_type === 'page' && isset( $fields['template'] ) && $use_field( 'fields', 'template' ) ) {
			$template = sanitize_text_field( $fields['template'] );
			update_post_meta( $local_id, '_wp_page_template', $template ? $template : 'default' );
		}

		foreach ( (array) ( $payload['meta'] ?? array() ) as $key => $values ) {
			if ( ! $use_field( 'meta', (string) $key ) ) {
				continue;
			}
			if ( str_starts_with( (string) $key, '_' ) ) {
				continue;
			}
			delete_post_meta( $local_id, $key );
			foreach ( (array) $values as $value ) {
				add_post_meta( $local_id, $key, $value );
			}
		}

		foreach ( (array) ( $payload['taxonomies'] ?? array() ) as $taxonomy => $terms ) {
			if ( ! $use_field( 'taxonomies', (string) $taxonomy ) ) {
				continue;
			}
			if ( taxonomy_exists( $taxonomy ) ) {
				wp_set_object_terms( $local_id, array_map( 'sanitize_title', (array) $terms ), $taxonomy );
			}
		}

		$media_ids  = array();
		$media_urls = array();
		foreach ( (array) ( $payload['media'] ?? array() ) as $index => $reference ) {
			if ( ! $use_field( 'media', (string) $index ) ) {
				continue;
			}
			$local_media_id = Media::import( (array) $reference );
			if ( $local_media_id ) {
				$media_ids[ (int) ( $reference['id'] ?? 0 ) ]  = $local_media_id;
				$media_urls[ (int) ( $reference['id'] ?? 0 ) ] = esc_url_raw( $reference['url'] ?? '' );
				if ( ! empty( $reference['featured'] ) && $use_field( 'media', 'featured' ) ) {
					set_post_thumbnail( $local_id, $local_media_id );
				}
			}
		}
		if ( ! empty( $media_ids ) && $use_field( 'fields', 'content' ) ) {
			$content = $post_values['post_content'] ?? $post->post_content;
			foreach ( $media_ids as $remote_media_id => $local_media_id ) {
				$content    = str_replace( 'wp-image-' . $remote_media_id, 'wp-image-' . $local_media_id, $content );
				$remote_url = $media_urls[ $remote_media_id ] ?? '';
				$local_url  = wp_get_attachment_url( $local_media_id );
				if ( $remote_url && $local_url ) {
					$content = str_replace( $remote_url, $local_url, $content );
				}
			}
			wp_update_post(
				array(
					'ID'           => $local_id,
					'post_content' => $content,
				)
			);
		}

		return true;
	}
}
