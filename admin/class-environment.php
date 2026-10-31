<?php
/**
 * Environment-aware WordPress admin tools.
 *
 * @package Aeon
 */

namespace Aeon\Admin;

/**
 * Adds safe environment links to the admin toolbar and hides the redundant Bedrock notice.
 */
final class Environment {
	/** Environment URL option name. */
	public const OPTION = 'aeon_environment_urls';

	/** Available environment keys. */
	public const ENVIRONMENTS = array( 'local', 'development', 'production' );

	/** Empty URLs keep destinations hidden until configured. */
	public const DEFAULT_URLS = array(
		'local'       => '',
		'development' => '',
		'production'  => '',
	);

	/** Register the environment tools. */
	public function register(): void {
		add_filter( 'roots/bedrock/disallow_indexing_admin_notice', '__return_false' );
		add_action( 'admin_bar_menu', array( $this, 'add_toolbar_switcher' ), 100 );
	}

	/**
	 * Sanitise the optional base URL for each environment.
	 *
	 * @param mixed $submitted Submitted settings value.
	 * @return array<string, string>
	 */
	public static function sanitise_urls( mixed $submitted ): array {
		$urls = self::DEFAULT_URLS;

		if ( ! is_array( $submitted ) ) {
			return $urls;
		}

		foreach ( self::ENVIRONMENTS as $environment ) {
			if ( ! isset( $submitted[ $environment ] ) || ! is_string( $submitted[ $environment ] ) ) {
				continue;
			}

			$url = trim( esc_url_raw( $submitted[ $environment ], array( 'http', 'https' ) ) );

			if ( '' === $url ) {
				continue;
			}

			$parts = wp_parse_url( $url );

			if (
				! is_array( $parts ) ||
				empty( $parts['host'] ) ||
				! isset( $parts['scheme'] ) ||
				! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ||
				isset( $parts['user'] ) ||
				isset( $parts['pass'] ) ||
				isset( $parts['query'] ) ||
				isset( $parts['fragment'] )
			) {
				continue;
			}

			$urls[ $environment ] = untrailingslashit( $url );
		}

		return $urls;
	}

	/**
	 * Get the configured environment URLs.
	 *
	 * @return array<string, string>
	 */
	public static function urls(): array {
		return self::sanitise_urls( get_option( self::OPTION, self::DEFAULT_URLS ) );
	}

	/**
	 * Add the current environment and configured destinations to the toolbar.
	 *
	 * @param \WP_Admin_Bar $admin_bar WordPress toolbar instance.
	 * @return void
	 */
	public function add_toolbar_switcher( \WP_Admin_Bar $admin_bar ): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'read' ) ) {
			return;
		}

		$environment = self::detected_environment();

		if ( null === $environment ) {
			return;
		}

		$current_url = home_url( '/' );
		$urls        = self::urls();
		$admin_bar->add_node(
			array(
				'id'    => 'aeon-environment-switcher',
				'title' => sprintf(
					/* Translators: %s is the current site environment. */
					esc_html__( 'Environment: %s', 'aeon' ),
					esc_html( self::environment_label( $environment ) )
				),
				'href'  => self::contextual_url(
					'' !== $urls[ $environment ] ? $urls[ $environment ] : $current_url
				),
			)
		);

		foreach ( self::ENVIRONMENTS as $target_environment ) {
			$label    = self::environment_label( $target_environment );
			$base_url = $urls[ $target_environment ];

			if ( '' === $base_url && $target_environment === $environment ) {
				$base_url = $current_url;
			}

			if ( '' === $base_url ) {
				continue;
			}

			$title = $target_environment === $environment
				? sprintf(
					/* Translators: %s is the current site environment. */
					esc_html__( '%s (current)', 'aeon' ),
					esc_html( $label )
				)
				: esc_html( $label );

			$admin_bar->add_node(
				array(
					'id'     => 'aeon-environment-switcher-' . $target_environment,
					'parent' => 'aeon-environment-switcher',
					'title'  => $title,
					'href'   => self::contextual_url( $base_url ),
					'meta'   => array(
						'class' => $target_environment === $environment ? 'current' : '',
					),
				)
			);
		}
	}

	/**
	 * Detect the active Bedrock or WordPress environment.
	 *
	 * @return string|null
	 */
	public static function detected_environment(): ?string {
		foreach ( array( 'WP_ENV', 'WP_ENVIRONMENT_TYPE' ) as $constant ) {
			if ( ! defined( $constant ) ) {
				continue;
			}

			$environment = self::normalise_environment( (string) constant( $constant ) );

			if ( $environment ) {
				return $environment;
			}
		}

		return null;
	}

	/**
	 * Normalise supported environment aliases to the canonical environment key.
	 *
	 * @param string $environment Raw environment name.
	 * @return string|null Canonical environment key, or null when unsupported.
	 */
	public static function normalise_environment( string $environment ): ?string {
		$environment = strtolower( $environment );
		$environment = array(
			'prod' => 'production',
			'dev'  => 'development',
		)[ $environment ] ?? $environment;

		return in_array( $environment, self::ENVIRONMENTS, true ) ? $environment : null;
	}

	/**
	 * Get the visible environment label.
	 *
	 * @param string $environment Environment key.
	 * @return string
	 */
	public static function environment_label( string $environment ): string {
		$labels = array(
			'local'       => __( 'Local', 'aeon' ),
			'development' => __( 'Development', 'aeon' ),
			'production'  => __( 'Production', 'aeon' ),
		);

		return $labels[ $environment ] ?? __( 'Unknown', 'aeon' );
	}

	/**
	 * Build a destination URL, preserving the current path and safe section arguments.
	 *
	 * @param string $base_url Configured site's base URL.
	 * @return string
	 */
	private static function contextual_url( string $base_url ): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';
		$request     = wp_parse_url( $request_uri );
		$path        = is_array( $request ) && isset( $request['path'] )
			? $request['path']
			: '/';

		if ( ! is_string( $path ) || ! str_starts_with( $path, '/' ) ) {
			$path = '/';
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? untrailingslashit( $home_path ) : '';

		if ( '' !== $home_path && ( $path === $home_path || str_starts_with( $path, trailingslashit( $home_path ) ) ) ) {
			$path = substr( $path, strlen( $home_path ) );
			$path = '' === $path ? '/' : $path;
		}

		$query_args = array();

		if ( is_array( $request ) && isset( $request['query'] ) ) {
			wp_parse_str( $request['query'], $query_args );
		}

		$safe_query_args = array();

		foreach ( array( 'page', 'post_type', 'postType', 'taxonomy', 'tab', 'path' ) as $key ) {
			if ( ! isset( $query_args[ $key ] ) || ! is_scalar( $query_args[ $key ] ) ) {
				continue;
			}

			$safe_query_args[ $key ] = sanitize_text_field( (string) $query_args[ $key ] );
		}

		if ( 'post.php' === basename( $path ) ) {
			$path = wp_parse_url( admin_url( 'edit.php' ), PHP_URL_PATH );
			$post = isset( $query_args['post'] ) && is_scalar( $query_args['post'] )
				? get_post( absint( $query_args['post'] ) )
				: null;

			if ( $post instanceof \WP_Post ) {
				$safe_query_args['post_type'] = sanitize_key( $post->post_type );
			}
		}

		if ( 'term.php' === basename( $path ) ) {
			$path = wp_parse_url( admin_url( 'edit-tags.php' ), PHP_URL_PATH );
		}

		if ( ! is_string( $path ) || ! str_starts_with( $path, '/' ) ) {
			$path = '/';
		}

		if ( '' !== $home_path && ( $path === $home_path || str_starts_with( $path, trailingslashit( $home_path ) ) ) ) {
			$path = substr( $path, strlen( $home_path ) );
			$path = '' === $path ? '/' : $path;
		}

		$url = trailingslashit( $base_url ) . ltrim( $path, '/' );

		return esc_url_raw( add_query_arg( $safe_query_args, $url ), array( 'http', 'https' ) );
	}
}
