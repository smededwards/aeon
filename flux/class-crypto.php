<?php
/**
 * Flux request signing and secret encryption.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

use WP_REST_Request;

/**
 * Provides the private protocol's cryptographic primitives.
 */
final class Crypto {
	private const CIPHER = 'aes-256-gcm';

	/**
	 * Encrypt a connection token with the site's WordPress salts.
	 *
	 * @param string $value Plain-text token.
	 * @return string
	 */
	public static function encrypt( string $value ): string {
		$key   = self::key();
		$iv    = random_bytes( 12 );
		$tag   = '';
		$value = openssl_encrypt( $value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( $value === false ) {
			return '';
		}

		return base64_encode( $iv . $tag . $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored connection token.
	 *
	 * @param string $value Encrypted token.
	 * @return string
	 */
	public static function decrypt( string $value ): string {
		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( $decoded === false || strlen( $decoded ) < 28 ) {
			return '';
		}

		$plain = openssl_decrypt(
			substr( $decoded, 28 ),
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			substr( $decoded, 0, 12 ),
			substr( $decoded, 12, 16 )
		);

		return $plain === false ? '' : $plain;
	}

	/**
	 * Sign a request using the canonical protocol string.
	 *
	 * @param string $method HTTP method.
	 * @param string $path Request path.
	 * @param string $body Request body.
	 * @param string $timestamp Unix timestamp.
	 * @param string $request_id Unique request identifier.
	 * @param string $token Shared secret.
	 * @return string
	 */
	public static function sign( string $method, string $path, string $body, string $timestamp, string $request_id, string $token ): string {
		$canonical = implode( "\n", array( strtoupper( $method ), $path, $timestamp, $request_id, hash( 'sha256', $body ) ) );

		return hash_hmac( 'sha256', $canonical, $token );
	}

	/**
	 * Verify a signed REST request and reject stale or replayed requests.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $token Shared secret.
	 * @return bool
	 */
	public static function verify( WP_REST_Request $request, string $token ): bool {
		$timestamp  = (string) $request->get_header( 'x-flux-timestamp' );
		$request_id = (string) $request->get_header( 'x-flux-request-id' );
		$signature  = (string) $request->get_header( 'x-flux-signature' );

		if ( ! $token || ! $timestamp || ! $request_id || ! $signature || abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}
		if ( get_transient( 'aeon_flux_request_' . sanitize_key( $request_id ) ) ) {
			return false;
		}

		$expected = self::sign( $request->get_method(), $request->get_route(), (string) $request->get_body(), $timestamp, $request_id, $token );

		$valid = hash_equals( $expected, $signature );
		if ( $valid ) {
			set_transient( 'aeon_flux_request_' . sanitize_key( $request_id ), 1, 5 * MINUTE_IN_SECONDS );
		}

		return $valid;
	}

	/**
	 * Derive the encryption and protocol key from WordPress salts.
	 *
	 * @return string
	 */
	private static function key(): string {
		return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY, true );
	}
}
