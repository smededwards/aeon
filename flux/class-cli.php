<?php
/**
 * Flux WP-CLI commands.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use WP_CLI;

/**
 * Provides safe command-line controls for Flux pulls and diagnostics.
 */
final class Cli {
	/**
	 * Flux connection settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Create the command controller.
	 *
	 * @param Settings $settings Flux settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register the Aeon Flux command group.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'aeon flux', $this );
	}

	/**
	 * Display the current Flux connection and storage status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aeon flux status
	 *     wp aeon flux status --prod
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function status( array $args = array(), array $assoc_args = array() ): void {
		$production = ! empty( $assoc_args['prod'] );
		$connection = $this->settings->connection( $production ? 'production' : null );
		$label      = $production ? 'Production' : (string) $connection['environment'];

		if ( $production && ( empty( $connection['url'] ) || empty( $connection['token'] ) ) ) {
			WP_CLI::error( 'Production requires a configured URL and shared connection token.' );
		}

		WP_CLI\Utils\format_items(
			'table',
			array(
				array(
					'field' => 'Enabled',
					'value' => empty( $connection['enabled'] ) ? 'No' : 'Yes',
				),
				array(
					'field' => 'Environment',
					'value' => $label,
				),
				array(
					'field' => 'URL',
					'value' => (string) $connection['url'],
				),
				array(
					'field' => 'Remote Writes',
					'value' => empty( $connection['allow_push'] ) ? 'No' : 'Yes',
				),
				array(
					'field' => 'Token',
					'value' => empty( $connection['token'] ) ? 'Missing' : 'Configured',
				),
			),
			array( 'field', 'value' )
		);

		if ( ! $production ) {
			return;
		}

		$handshake = ( new Client( $connection ) )->handshake();
		if ( is_wp_error( $handshake ) ) {
			WP_CLI::error( $handshake->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Connected to Flux at %s.', (string) $connection['url'] ) );
	}

	/**
	 * Pull a remote Post or Page into a local record.
	 *
	 * ## OPTIONS
	 *
	 * <local-id>
	 * : Local Post or Page ID.
	 *
	 * <remote-id>
	 * : Remote Post or Page ID.
	 *
	 * [--post-type=<post-type>]
	 * : Post type to request. Defaults to the local record's type.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated field keys to apply, such as fields.title,fields.content.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aeon flux pull 42 18
	 *     wp aeon flux pull 42 18 --post-type=page --fields=fields.title,fields.content
	 *
	 * @param array<int,string>    $args       Positional arguments.
	 * @param array<string,string> $assoc_args Named arguments.
	 * @return void
	 */
	public function pull( array $args, array $assoc_args ): void {
		$local_id  = absint( $args[0] ?? 0 );
		$remote_id = absint( $args[1] ?? 0 );
		$local     = get_post( $local_id );

		if ( ! $local || ! in_array( $local->post_type, array( 'post', 'page' ), true ) ) {
			WP_CLI::error( 'The local record must be an existing Post or Page.' );
		}

		if ( ! $remote_id ) {
			WP_CLI::error( 'A remote record ID is required.' );
		}

		$connection = $this->settings->connection();
		if ( empty( $connection['enabled'] ) || empty( $connection['url'] ) || empty( $connection['token'] ) ) {
			WP_CLI::error( 'Flux must be enabled with a configured URL and token.' );
		}

		$post_type = sanitize_key( $assoc_args['post-type'] ?? $local->post_type );
		if ( $post_type !== $local->post_type || ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			WP_CLI::error( 'The requested post type must match the local Post or Page.' );
		}

		$remote = ( new Client( $connection ) )->get_record( $post_type, $remote_id );
		if ( is_wp_error( $remote ) ) {
			WP_CLI::error( $remote->get_error_message() );
		}

		$fields = array_filter( array_map( 'trim', explode( ',', (string) ( $assoc_args['fields'] ?? '' ) ) ) );
		$result = Applier::apply( $local_id, $remote, $fields );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		Mapping::save( $post_type, $local_id, $remote_id, (string) $connection['url'], Payload::snapshot( $remote ) );
		Database::log( wp_generate_uuid4(), 'pull', $post_type, $local_id, $remote_id, 'success' );
		WP_CLI::success( sprintf( 'Pulled remote %s %d into local %s %d.', $post_type, $remote_id, $post_type, $local_id ) );
	}
}
