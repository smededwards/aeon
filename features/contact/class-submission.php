<?php
/**
 * Validated contact input boundary.
 *
 * @package Aeon
 */

namespace Aeon\Features\Contact;

/** Keeps raw input out of delivery code, even when validation fails. */
final class Submission {

	/**
	 * Only the sanitising factory can construct a submission.
	 *
	 * @param array<string, string> $values Sanitised, bounded field values.
	 * @param array<string, string> $errors Validation messages.
	 * @param string                $nonce Sanitised nonce for subsequent verification.
	 */
	private function __construct(
		public readonly array $values,
		public readonly array $errors,
		public readonly string $nonce
	) {}

	/**
	 * Check original input before producing safe values for display and delivery.
	 *
	 * @param mixed  $input Unslashed submitted fields.
	 * @param string $nonce Nonce supplied independently of the visitor fields.
	 * @return self Immutable sanitised submission, including any errors.
	 */
	public static function from_input( mixed $input, string $nonce = '' ): self {
		$input  = is_array( $input ) ? $input : array();
		$values = array();
		$errors = array();
		foreach ( array(
			'name'    => 100,
			'email'   => 254,
			'message' => 5000,
		) as $field => $limit ) {
			$raw = is_string( $input[ $field ] ?? null ) ? $input[ $field ] : '';
			// Retain only bounded, sanitised text when reporting a validation failure.
			$bounded          = mb_substr( $raw, 0, $limit + 1 );
			$values[ $field ] = 'message' === $field ? sanitize_textarea_field( $bounded ) : sanitize_text_field( $bounded );
			if ( '' === trim( $values[ $field ] ) ) {
				$errors[ $field ] = __( 'Please complete this field.', 'aeon' );
			} elseif ( mb_strlen( $raw ) > $limit ) {
				/* translators: %d: maximum number of characters. */
				$errors[ $field ] = sprintf( __( 'Use %d characters or fewer.', 'aeon' ), $limit );
			}
		}
		$raw_email = is_string( $input['email'] ?? null ) ? $input['email'] : '';
		$email     = trim( $raw_email );
		if ( ! isset( $errors['email'] ) ) {
			if ( preg_match( '/[\r\n]/', $raw_email ) || ! is_email( $email ) ) {
				$errors['email'] = __( 'Enter a valid email address.', 'aeon' );
			} else {
				$values['email'] = sanitize_email( $email );
			}
		}
		if ( isset( $input['website'] ) && ( ! is_string( $input['website'] ) || '' !== trim( $input['website'] ) ) ) {
			$errors['general'] = __( 'Your message could not be sent. Please try again.', 'aeon' );
		}
		return new self( $values, $errors, sanitize_text_field( $nonce ) );
	}
}
