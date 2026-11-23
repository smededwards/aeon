<?php
/**
 * Flux REST protocol.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers the versioned, signed record endpoint.
 */
final class Rest {

	/**
	 * Flux connection settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Create the REST controller.
	 *
	 * @param Settings $settings Flux settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register Flux REST routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register record and handshake routes.
	 *
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			'flux/v1',
			'/handshake',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => static function () {
					return new WP_REST_Response(
						array(
							'protocol' => 'flux/v1',
							'site'     => home_url( '/' ),
						),
						200
					);
				},
			)
		);
		register_rest_route(
			'flux/v1',
			'/records/(?P<type>post|page)/(?P<id>\d+)',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => array( $this, 'record' ),
			)
		);
	}

	/**
	 * Authenticate a signed request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function permission( WP_REST_Request $request ) {
		$connection = $this->settings->connection();

		if ( empty( $connection['enabled'] ) || empty( $connection['token'] ) || ! Crypto::verify( $request, (string) $connection['token'] ) ) {
			return new WP_Error( 'flux_unauthorised', __( 'The Flux request could not be authenticated.', 'aeon' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Return a synchronisable Post or Page.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function record( WP_REST_Request $request ) {
		$id   = absint( $request['id'] );
		$type = sanitize_key( $request['type'] );
		$post = get_post( $id );

		if ( ! $post || $post->post_type !== $type || ! in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			return new WP_Error( 'flux_record_not_found', __( 'The requested record was not found.', 'aeon' ), array( 'status' => 404 ) );
		}

		if ( 'POST' === $request->get_method() ) {
			$connection = $this->settings->connection();
			if ( empty( $connection['allow_push'] ) ) {
				return new WP_Error( 'flux_push_disabled', __( 'Remote writes are disabled in Flux settings.', 'aeon' ), array( 'status' => 403 ) );
			}
			$result = Applier::apply( $id, (array) $request->get_json_params() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return new WP_REST_Response( Payload::from_post( get_post( $id ) ), 200 );
	}
}
