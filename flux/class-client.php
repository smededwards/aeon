<?php
/**
 * Flux remote client.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use WP_Error;

/**
 * Sends signed server-to-server requests to the configured environment.
 */
final class Client {
	/**
	 * Remote environment URL.
	 *
	 * @var string
	 */
	private string $url;
	/**
	 * Shared request token.
	 *
	 * @var string
	 */
	private string $token;

	/**
	 * Create a client from a connection profile.
	 *
	 * @param array<string,string|int> $connection Connection settings.
	 */
	public function __construct( array $connection ) {
		$this->url   = untrailingslashit( (string) $connection['url'] );
		$this->token = (string) $connection['token'];
	}

	/**
	 * Test the signed connection to the remote environment.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function handshake() {
		$path     = '/flux/v1/handshake';
		$body     = '';
		$time     = (string) time();
		$id       = wp_generate_uuid4();
		$response = wp_remote_get(
			$this->url . '/wp-json' . $path,
			array(
				'timeout'             => 20,
				'limit_response_size' => 1 * MB_IN_BYTES,
				'sslverify'           => true,
				'headers'             => array(
					'X-Flux-Timestamp'  => $time,
					'X-Flux-Request-ID' => $id,
					'X-Flux-Signature'  => Crypto::sign( 'GET', $path, $body, $time, $id, $this->token ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 || ! is_array( $data ) ) {
			return new WP_Error(
				'flux_handshake_failed',
				sprintf(
					/* Translators: %d is the remote HTTP response status. */
					__( 'The remote Flux handshake failed with HTTP status %d.', 'aeon' ),
					$status
				),
				array( 'status' => $status )
			);
		}

		return $data;
	}

	/**
	 * Fetch a remote Post or Page payload.
	 *
	 * @param string $post_type Post type.
	 * @param int    $remote_id Remote post ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_record( string $post_type, int $remote_id ) {
		$path     = sprintf( '/flux/v1/records/%s/%d', rawurlencode( $post_type ), $remote_id );
		$body     = '';
		$time     = (string) time();
		$id       = wp_generate_uuid4();
		$response = wp_remote_get(
			$this->url . '/wp-json' . $path,
			array(
				'timeout'             => 20,
				'limit_response_size' => 5 * MB_IN_BYTES,
				'sslverify'           => true,
				'headers'             => array(
					'X-Flux-Timestamp'  => $time,
					'X-Flux-Request-ID' => $id,
					'X-Flux-Signature'  => Crypto::sign( 'GET', $path, $body, $time, $id, $this->token ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 || ! is_array( $data ) ) {
			return new WP_Error( 'flux_remote_error', __( 'The remote environment returned an invalid response.', 'aeon' ), array( 'status' => $status ) );
		}

		return $data;
	}

	/**
	 * Push a local payload to a remote Post or Page.
	 *
	 * @param string              $post_type Post type.
	 * @param int                 $remote_id Remote post ID.
	 * @param array<string,mixed> $payload Record payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_record( string $post_type, int $remote_id, array $payload ) {
		$path     = sprintf( '/flux/v1/records/%s/%d', rawurlencode( $post_type ), $remote_id );
		$body     = wp_json_encode( $payload );
		$time     = (string) time();
		$id       = wp_generate_uuid4();
		$response = wp_remote_post(
			$this->url . '/wp-json' . $path,
			array(
				'timeout'             => 20,
				'limit_response_size' => 5 * MB_IN_BYTES,
				'sslverify'           => true,
				'body'                => $body,
				'headers'             => array(
					'Content-Type'      => 'application/json',
					'X-Flux-Timestamp'  => $time,
					'X-Flux-Request-ID' => $id,
					'X-Flux-Signature'  => Crypto::sign( 'POST', $path, $body, $time, $id, $this->token ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 || ! is_array( $data ) ) {
			return new WP_Error( 'flux_remote_error', __( 'The remote environment rejected the update.', 'aeon' ), array( 'status' => $status ) );
		}

		return $data;
	}
}
