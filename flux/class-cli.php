<?php
/**
 * Flux WP-CLI commands.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use Aeon\Admin\Environment;
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
	 *     wp aeon flux status --environment=local
	 *     wp aeon flux status --prod
	 *     wp aeon flux status --local
	 *     wp aeon flux status --dev
	 *
	 * ## OPTIONS
	 *
	 * [--environment=<environment>]
	 * : Environment to inspect: local, development or production. Defaults to the configured Flux target.
	 *
	 * [--prod]
	 * : Alias for --environment=production.
	 *
	 * [--local]
	 * : Alias for --environment=local.
	 *
	 * [--dev]
	 * : Alias for --environment=development.
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @return void
	 */
	public function status( array $args = array(), array $assoc_args = array() ): void {
		$environment      = $this->requested_environment( $assoc_args );
		$connection       = $this->settings->connection( $environment );
		$handshake        = null;
		$handshake_status = 'Not tested';

		if ( ! empty( $connection['enabled'] ) && ! empty( $connection['url'] ) && ! empty( $connection['token'] ) ) {
			$handshake        = ( new Client( $connection ) )->handshake();
			$handshake_status = is_wp_error( $handshake ) ? 'Failed' : 'Connected';
		}

		WP_CLI\Utils\format_items(
			'table',
			array(
				array(
					'field' => 'Site Environment',
					'value' => Environment::environment_label( Environment::detected_environment() ?? 'unknown' ),
				),
				array(
					'field' => 'Target Environment',
					'value' => Environment::environment_label( (string) $connection['environment'] ),
				),
				array(
					'field' => 'Target URL',
					'value' => (string) $connection['url'],
				),
				array(
					'field' => 'Enabled',
					'value' => empty( $connection['enabled'] ) ? 'No' : 'Yes',
				),
				array(
					'field' => 'Remote Writes',
					'value' => empty( $connection['allow_push'] ) ? 'Disabled' : 'Enabled',
				),
				array(
					'field' => 'Token',
					'value' => empty( $connection['token'] ) ? 'Missing' : 'Configured',
				),
				array(
					'field' => 'Handshake',
					'value' => $handshake_status,
				),
			),
			array( 'field', 'value' )
		);

		if ( null === $handshake ) {
			WP_CLI::warning( 'Handshake not attempted: Flux needs to be enabled with a configured URL and token.' );
			return;
		}

		if ( is_wp_error( $handshake ) ) {
			WP_CLI::error( $handshake->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Connected to Flux at %s.', (string) $connection['url'] ) );
	}

	/**
	 * Resolve the requested status target from the supported flags.
	 *
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @return string|null Environment override, or null for the configured target.
	 */
	private function requested_environment( array $assoc_args ): ?string {
		$flags     = array(
			'prod'  => 'production',
			'local' => 'local',
			'dev'   => 'development',
		);
		$requested = array();

		foreach ( $flags as $flag => $environment ) {
			if ( ! empty( $assoc_args[ $flag ] ) ) {
				$requested[ $flag ] = $environment;
			}
		}

		if ( isset( $assoc_args['environment'] ) ) {
			if ( $requested ) {
				WP_CLI::error( 'Use --environment or an environment alias, not both.' );
			}

			$environment = Environment::normalise_environment( sanitize_key( (string) $assoc_args['environment'] ) );
			if ( null === $environment ) {
				WP_CLI::error( 'Environment must be local, development or production.' );
			}

			return $environment;
		}

		if ( count( $requested ) > 1 ) {
			WP_CLI::error( 'Choose one environment alias.' );
		}

		return $requested ? (string) reset( $requested ) : null;
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
