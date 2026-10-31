<?php
/**
 * Optional SEO fields and sharing metadata.
 *
 * @package Aeon
 */

namespace Aeon\Features;

use Aeon\Settings;

/**
 * Extends native document titles without replacing core canonical or sitemap output.
 */
final class Seo {
	// Native post-meta key for the optional title.
	public const TITLE = '_aeon_seo_title';
	// Native post-meta key for the optional description.
	public const DESCRIPTION = '_aeon_seo_description';
	// Native post-meta key for the optional social attachment.
	public const IMAGE = '_aeon_social_image';

	/**
	 * Receive site preferences.
	 *
	 * @param Settings $settings Feature settings.
	 */
	public function __construct( private readonly Settings $settings ) {}

	/**
	 * Register native metadata; output is optional and can be handed to another provider.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		if ( $this->settings->is_enabled( 'seo' ) ) {
			add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
			add_action( 'wp_head', array( $this, 'head' ), 5 );
			add_action( 'wp_head', array( $this, 'schema' ), 30 );
		}
	}

	/**
	 * Register only the three post/page fields, with native REST and revision support.
	 *
	 * @return void
	 */
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

	/**
	 * Output the page description and platform-specific sharing metadata.
	 *
	 * @return void
	 */
	public function head(): void {
		$post = $this->context();
		if ( ! $post ) {
			return;
		}
		$title       = $this->plain_text( wp_get_document_title() );
		$description = $this->description( $post );
		$url         = is_home() ? get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ), false ) : wp_get_canonical_url( $post );
		$url         = $url ? $url : get_permalink( $post );
		$image       = $this->image( $post->ID );
		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}
		$properties = array(
			'og:title'     => $title,
			'og:type'      => 'post' === $post->post_type ? 'article' : 'website',
			'og:url'       => $url,
			'og:site_name' => $this->plain_text( get_bloginfo( 'name' ) ),
			'og:locale'    => str_replace( '-', '_', get_locale() ),
		);
		if ( $description ) {
			$properties['og:description'] = $description;
		}
		if ( $image ) {
			$properties['og:image']        = $image['url'];
			$properties['og:image:width']  = (string) $image['width'];
			$properties['og:image:height'] = (string) $image['height'];
			if ( $image['alt'] ) {
				$properties['og:image:alt'] = $image['alt'];
			}
		}
		if ( 'post' === $post->post_type ) {
			$properties['article:published_time'] = get_post_time( DATE_W3C, false, $post );
			$properties['article:modified_time']  = get_post_modified_time( DATE_W3C, false, $post );
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
			$twitter['twitter:image'] = $image['url'];
			if ( $image['alt'] ) {
				$twitter['twitter:image:alt'] = $image['alt'];
			}
		}
		foreach ( $twitter as $name => $value ) {
			printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $name ), esc_attr( $value ) );
		}
	}

	/**
	 * Output JSON-LD for the public person, website, page and article entities.
	 *
	 * @return void
	 */
	public function schema(): void {
		$post = $this->context();
		if ( ! $post ) {
			return;
		}

		$json = wp_json_encode(
			$this->schema_data( $post ),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
		);
		if ( ! is_string( $json ) ) {
			return;
		}

		wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
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
					'id'     => $id,
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
	 * Determine whether an attachment is the site's generic placeholder artwork.
	 *
	 * @param array $image Image metadata with an attachment ID.
	 * @return bool Whether the source attachment is a placeholder.
	 */
	private function is_placeholder_image( array $image ): bool {
		$file = get_attached_file( (int) ( $image['id'] ?? 0 ) );
		return $file && 1 === preg_match( '/^placeholder(?:[-_].*)?\.[a-z0-9]+$/i', basename( $file ) );
	}

	/**
	 * Build one connected schema graph for the current public page.
	 *
	 * The `aeon_seo_person_schema` filter lets the site add accurate person details,
	 * such as job title, profile image and sameAs links, without hardcoding them in Aeon.
	 *
	 * @param \WP_Post $post Public page or post.
	 * @return array Connected JSON-LD graph.
	 */
	private function schema_data( \WP_Post $post ): array {
		$home_url   = home_url( '/' );
		$person_id  = $home_url . '#person';
		$website_id = $home_url . '#website';
		$page_url   = is_home() ? get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ), false ) : wp_get_canonical_url( $post );
		$page_url   = $page_url ? $page_url : get_permalink( $post );
		$page_id    = $page_url . '#webpage';
		$name       = $this->plain_text( get_bloginfo( 'name' ) );
		$title      = $this->plain_text( wp_get_document_title() );
		$person     = array(
			'@type' => 'Person',
			'@id'   => $person_id,
			'name'  => $name,
			'url'   => $home_url,
		);
		$person     = apply_filters( 'aeon_seo_person_schema', $person, $post );
		if ( ! is_array( $person ) ) {
			$person = array();
		}
		$person = array_merge(
			array(
				'name' => $name,
				'url'  => $home_url,
			),
			$person,
			array(
				'@type' => 'Person',
				'@id'   => $person_id,
			)
		);

		$website      = array(
			'@type'      => 'WebSite',
			'@id'        => $website_id,
			'url'        => $home_url,
			'name'       => $name,
			'inLanguage' => get_bloginfo( 'language' ),
			'publisher'  => array( '@id' => $person_id ),
		);
		$page         = array(
			'@type'      => is_page( 'about' ) ? 'ProfilePage' : 'WebPage',
			'@id'        => $page_id,
			'url'        => $page_url,
			'name'       => $title,
			'isPartOf'   => array( '@id' => $website_id ),
			'about'      => array( '@id' => $person_id ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		$description  = $this->description( $post );
		$image        = $this->image( $post->ID );
		$schema_image = $image && ! $this->is_placeholder_image( $image ) ? $image : array();
		if ( $description ) {
			$page['description'] = $description;
		}
		if ( $schema_image ) {
			$page['primaryImageOfPage'] = array(
				'@type'  => 'ImageObject',
				'url'    => $schema_image['url'],
				'width'  => $schema_image['width'],
				'height' => $schema_image['height'],
			);
			if ( $schema_image['alt'] ) {
				$page['primaryImageOfPage']['caption'] = $schema_image['alt'];
			}
		}
		if ( 'ProfilePage' === $page['@type'] ) {
			$page['mainEntity'] = array( '@id' => $person_id );
		}

		$graph = array( $person, $website, $page );
		if ( 'post' === $post->post_type ) {
			$headline = get_post_meta( $post->ID, self::TITLE, true );
			$headline = $this->plain_text( $headline ? $headline : get_the_title( $post ) );
			$article  = array(
				'@type'            => 'BlogPosting',
				'@id'              => $page_id . '#article',
				'headline'         => $headline,
				'mainEntityOfPage' => array( '@id' => $page_id ),
				'author'           => array( '@id' => $person_id ),
				'publisher'        => array( '@id' => $person_id ),
				'datePublished'    => get_post_time( DATE_W3C, false, $post ),
				'dateModified'     => get_post_modified_time( DATE_W3C, false, $post ),
				'inLanguage'       => get_bloginfo( 'language' ),
			);
			if ( $description ) {
				$article['description'] = $description;
			}
			if ( $schema_image ) {
				$article['image'] = array( $schema_image['url'] );
			}
			$graph[] = $article;
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
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
