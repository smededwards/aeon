<?php
/**
 * Contact form processing without storing submissions.
 *
 * @package Aeon
 */

namespace Aeon\Features;

use Aeon\Features\Contact\Submission;

/** Validates public submissions and delivers plain-text email. */
final class Contact {

	/**
	 * Request-local values and errors; never persisted.
	 *
	 * @var array
	 */
	private array $state = array(
		'values' => array(),
		'errors' => array(),
	);

	/** Register the public page handler and block state contract. */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle' ) );
		add_filter( 'rune_contact_state', array( $this, 'state' ) );
	}

	/**
	 * Supply request-local validation state to Rune.
	 *
	 * @return array Form state.
	 */
	public function state(): array {
		return $this->state;
	}

	/** Handle the public form and its page-bound success receipt. */
	public function handle(): void {
		if ( ! is_singular( 'page' ) || ! has_block( 'rune/contact-form', get_queried_object_id() ) ) {
			return;
		}
		nocache_headers();
		$page_id = get_queried_object_id();
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			$this->state['sent'] = isset( $_GET['contact-sent'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['contact-sent'] ) ), 'aeon_contact_sent_' . $page_id );
			return;
		}
		if ( ! isset( $_POST['_aeon_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aeon_nonce'] ) ), 'aeon_contact_' . $page_id ) ) {
			$this->state['errors']['general'] = __( 'The form has expired. Reload the page and try again.', 'aeon' );
			status_header( 422 );
			return;
		}
		$submission  = aeon_sanitise_contact_submission(
			wp_unslash( $_POST['aeon_contact'] ?? array() ),
			sanitize_text_field( wp_unslash( $_POST['_aeon_nonce'] ) )
		);
		$this->state = $this->process( $submission, $page_id, sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		if ( $this->state['errors'] ) {
			status_header( 422 );
			return;
		}
		wp_safe_redirect( add_query_arg( 'contact-sent', wp_create_nonce( 'aeon_contact_sent_' . $page_id ), get_permalink() ) . '#contact-form', 303 );
		exit;
	}

	/**
	 * Deliver only sanitised submissions after page, nonce and rate checks.
	 *
	 * @param Submission $submission Immutable sanitised input and validation errors.
	 * @param int        $page_id Page containing the form.
	 * @param string     $address Direct peer address; forwarded headers are not trusted.
	 * @return array Values and field/general errors.
	 */
	public function process( Submission $submission, int $page_id, string $address ): array {
		$values = $submission->values;
		$errors = $submission->errors;
		if ( ! wp_verify_nonce( $submission->nonce, 'aeon_contact_' . $page_id ) || 'publish' !== get_post_status( $page_id ) || ! has_block( 'rune/contact-form', $page_id ) ) {
			$errors['general'] = __( 'The form has expired. Reload the page and try again.', 'aeon' );
			return compact( 'values', 'errors' );
		}
		if ( isset( $errors['general'] ) ) {
			return compact( 'values', 'errors' );
		}
		if ( ! $this->allow_attempt( $address ) ) {
			$errors['general'] = __( 'Too many attempts. Please wait 15 minutes before trying again.', 'aeon' );
			return compact( 'values', 'errors' );
		}
		if ( $errors ) {
			return compact( 'values', 'errors' );
		}
		$recipient = get_option( 'admin_email' );
		$domain    = wp_parse_url( home_url(), PHP_URL_HOST );
		$sent      = is_email( $recipient ) && is_email( 'wordpress@' . $domain ) && wp_mail(
			$recipient,
			__( 'Portfolio contact message', 'aeon' ),
			sprintf( "Name: %s\nEmail: %s\n\n%s", $values['name'], $values['email'], $values['message'] ),
			array( 'Content-Type: text/plain; charset=UTF-8', 'From: wordpress@' . $domain, 'Reply-To: ' . $values['email'] )
		);
		if ( ! $sent ) {
			$errors['general'] = __( 'Your message could not be sent. Please try again later.', 'aeon' );
		}
		return compact( 'values', 'errors' );
	}

	/**
	 * Apply a modest per-peer limit without retaining raw IP addresses or content.
	 *
	 * @param string $address Direct peer address.
	 * @return bool Whether an attempt is permitted.
	 */
	private function allow_attempt( string $address ): bool {
		$key   = 'aeon_contact_' . hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) );
		$limit = get_transient( $key );
		$limit = is_array( $limit ) ? $limit : array(
			'count' => 0,
			'until' => time() + 15 * MINUTE_IN_SECONDS,
		);
		if ( $limit['until'] <= time() ) {
			$limit = array(
				'count' => 0,
				'until' => time() + 15 * MINUTE_IN_SECONDS,
			);
		}
		if ( $limit['count'] >= 5 ) {
			return false;
		}
		++$limit['count'];
		set_transient( $key, $limit, max( 1, $limit['until'] - time() ) );
		return true;
	}
}
