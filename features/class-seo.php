<?php
/**
 * Optional SEO fields and sharing metadata.
 *
 * @package Aeon
 */

namespace Aeon\Features;

use Aeon\Settings;

/** Extends native document titles without replacing core canonical or sitemap output. */
final class Seo {
	/** Native post-meta key for the optional title. */
	public const TITLE = '_aeon_seo_title';
	/** Native post-meta key for the optional description. */
	public const DESCRIPTION = '_aeon_seo_description';
	/** Native post-meta key for the optional social attachment. */
	public const IMAGE = '_aeon_social_image';

	/**
	 * Receive site preferences.
	 *
	 * @param Settings $settings Feature settings.
	 */
	public function __construct( private readonly Settings $settings ) {}

	/** Register native metadata; output is optional and can be handed to another provider. */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		if ( $this->settings->is_enabled( 'seo' ) ) {
			add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
			add_action( 'wp_head', array( $this, 'head' ), 5 );
		}
	}

	/** Register only the three post/page fields, with native REST and revision support. */
	public function register_meta(): void {
		foreach ( array( 'post', 'page' ) as $type ) {
			foreach ( array( self::TITLE, self::DESCRIPTION, self::IMAGE ) as $key ) {
				$is_image = self::IMAGE === $key;
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => $is_image ? 'integer' : 'string',
						'single'            => true,
						'default'           => $is_image ? 0 : '',
						'sanitize_callback' => $is_image ? array( $this, 'sanitise_image' ) : 'sanitize_text_field',
						'auth_callback'     => array( $this, 'can_edit' ),
						'show_in_rest'      => array( 'schema' => array( 'context' => array( 'edit' ) ) ),
						'revisions_enabled' => true,
					)
				);
			}
		}
	}

	/**
	 * Restrict editing to users who can edit the specific content item.
	 *
	 * @param bool   $allowed Previous decision.
	 * @param string $key Meta key.
	 * @param int    $post_id Content ID.
	 * @return bool Whether this user can edit the field.
	 */
	public function can_edit( bool $allowed, string $key, int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Store an image attachment ID, never an arbitrary URL or non-image object.
	 *
	 * @param mixed $value Submitted attachment identifier.
	 * @return int Valid image ID or zero for the featured-image fallback.
	 */
	public function sanitise_image( mixed $value ): int {
		$id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		return $id && wp_attachment_is_image( $id ) ? $id : 0;
	}

	/**
	 * Override just the content title; preserve core site/tagline and pagination parts.
	 *
	 * @param array $parts WordPress title components.
	 * @return array Effective components.
	 */
	public function title_parts( array $parts ): array {
		$post = $this->context();
		if ( $post ) {
			$title = get_post_meta( $post->ID, self::TITLE, true );
			if ( '' !== trim( $title ) ) {
				$parts['title'] = sanitize_text_field( $title );
			}
		}
		return $parts;
	}

	/** Add one description and one set of platform-specific tags. */
	public function head(): void {
		$post = $this->context();
		if ( ! $post ) {
			return;
		}
		$title       = $this->plain_text( wp_get_document_title() );
		$description = $this->description( $post );
		$url         = is_home() ? get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ), false ) : wp_get_canonical_url( $post );
		$image       = $this->image( $post->ID );
		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}
		$properties = array(
			'og:title'     => $title,
			'og:type'      => 'post' === $post->post_type ? 'article' : 'website',
			'og:url'       => $url,
			'og:site_name' => $this->plain_text( get_bloginfo( 'name' ) ),
		);
		if ( $description ) {
			$properties['og:description'] = $description;
		}
		if ( $image ) {
			$properties['og:image']        = $image['url'];
			$properties['og:image:width']  = (string) $image['width'];
			$properties['og:image:height'] = (string) $image['height'];
			$properties['og:image:alt']    = $image['alt'];
		}
		foreach ( $properties as $property => $value ) {
			printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $value ) );
		}
		$twitter = array(
			'twitter:card'  => $image ? 'summary_large_image' : 'summary',
			'twitter:title' => $title,
		);
		if ( $description ) {
			$twitter['twitter:description'] = $description;
		}
		if ( $image ) {
			$twitter['twitter:image']     = $image['url'];
			$twitter['twitter:image:alt'] = $image['alt'];
		}
		foreach ( $twitter as $name => $value ) {
			printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $name ), esc_attr( $value ) );
		}
	}

	/**
	 * Resolve an explicitly public post/page, including the configured Posts page.
	 *
	 * @return \WP_Post|null Public content eligible for metadata.
	 */
	private function context(): ?\WP_Post {
		if ( is_admin() || is_feed() || is_preview() || ( ! is_singular( array( 'post', 'page' ) ) && ! is_home() ) ) {
			return null;
		}
		$posts_page = (int) get_option( 'page_for_posts' );
		$post       = is_home() ? ( $posts_page ? get_post( $posts_page ) : null ) : get_queried_object();
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || ! is_post_publicly_viewable( $post ) || $post->post_password ) {
			return null;
		}
		return $post;
	}

	/**
	 * Prefer the override, then an excerpt, then a short plain-text content summary.
	 *
	 * @param \WP_Post $post Content item.
	 * @return string Plain-text description.
	 */
	private function description( \WP_Post $post ): string {
		$override = $this->plain_text( get_post_meta( $post->ID, self::DESCRIPTION, true ) );
		if ( $override ) {
			return $override;
		}
		$text = $this->plain_text( $post->post_excerpt ? $post->post_excerpt : excerpt_remove_blocks( $post->post_content ) );
		if ( ! $text && is_front_page() ) {
			$text = $this->plain_text( get_bloginfo( 'description' ) );
		}
		return wp_html_excerpt( $text, 160, '…' );
	}

	/**
	 * Prefer a usable social image, otherwise the native featured image.
	 *
	 * @param int $post_id Content identifier.
	 * @return array Image metadata, or an empty array.
	 */
	private function image( int $post_id ): array {
		foreach ( array_unique( array( (int) get_post_meta( $post_id, self::IMAGE, true ), get_post_thumbnail_id( $post_id ) ) ) as $id ) {
			if ( ! $id || ! wp_attachment_is_image( $id ) || ! in_array( get_post_status( $id ), array( 'inherit', 'publish' ), true ) ) {
				continue;
			}
			$source = wp_get_attachment_image_src( $id, 'full' );
			if ( $source ) {
				return array(
					'url'    => esc_url_raw( $source[0] ),
					'width'  => $source[1],
					'height' => $source[2],
					'alt'    => $this->plain_text( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
				);
			}
		}
		return array();
	}

	/**
	 * Read text without running shortcodes or dynamic block renderers.
	 *
	 * @param string $value Stored text or block markup.
	 * @return string Normalised plain text.
	 */
	private function plain_text( string $value ): string {
		$value = preg_replace( '/<br\s*\/?>|<\/(?:p|div|h[1-6]|li|tr|td|th|blockquote|pre)>/i', ' ', $value ) ?? '';
		$value = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $value ), true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
	}
}
