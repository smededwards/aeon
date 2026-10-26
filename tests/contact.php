<?php
/**
 * Contact validation and delivery checks within WordPress.
 *
 * @package Aeon
 */

$aeon_assert    = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
};
$aeon_page      = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Contact test fixture',
		'post_content' => '<!-- wp:rune/contact-form /-->',
	)
);
$aeon_mail      = array();
$aeon_fail      = false;
$aeon_intercept = static function ( $result, array $mail ) use ( &$aeon_mail, &$aeon_fail ) {
	$aeon_mail[] = $mail;
	return ! $aeon_fail;
};
add_filter( 'pre_wp_mail', $aeon_intercept, 10, 2 );
$aeon_addresses = array();
$aeon_address   = static function () use ( &$aeon_addresses ): string {
	$address          = 'test-' . wp_generate_uuid4();
	$aeon_addresses[] = $address;
	return $address;
};
try {
	$aeon_service    = new \Aeon\Features\Contact();
	$aeon_process    = static function ( array $input, int $page_id, string $address ) use ( $aeon_service ): array {
		$nonce = is_string( $input['_aeon_nonce'] ?? null ) ? $input['_aeon_nonce'] : '';
		return $aeon_service->process( aeon_sanitise_contact_submission( $input, $nonce ), $page_id, $address );
	};
	$aeon_input      = array(
		'_aeon_nonce' => wp_create_nonce( 'aeon_contact_' . $aeon_page ),
		'name'        => '<b>Visitor</b>',
		'email'       => 'visitor@example.test',
		'message'     => "Hello <script>bad()</script>\nSecond line",
		'website'     => '',
	);
	$aeon_submission = aeon_sanitise_contact_submission(
		array_replace(
			$aeon_input,
			array(
				'name'       => str_repeat( 'x', 500 ),
				'unexpected' => '<script>unsafe</script>',
			)
		)
	);
	$aeon_assert( isset( $aeon_submission->errors['name'] ) && 101 === mb_strlen( $aeon_submission->values['name'] ), 'Invalid input must be rejected and retained text bounded.' );
	$aeon_assert( array( 'name', 'email', 'message' ) === array_keys( $aeon_submission->values ), 'Unknown fields must not escape the input boundary.' );
	$aeon_immutable = false;
	try {
		$aeon_submission->values['name'] = '<script>unsafe</script>';
	} catch ( Error $aeon_exception ) {
		$aeon_immutable = true;
	}
	$aeon_assert( $aeon_immutable, 'Sanitised submissions must remain immutable.' );
	$aeon_submission = aeon_sanitise_contact_submission( array( 'invalid' ) );
	$aeon_assert( 3 === count( $aeon_submission->errors ), 'Malformed submissions must fail validation.' );
	$aeon_result = $aeon_process( array_replace( $aeon_input, array( '_aeon_nonce' => 'invalid' ) ), $aeon_page, $aeon_address() );
	$aeon_assert( isset( $aeon_result['errors']['general'] ) && ! $aeon_mail, 'Invalid nonces must not send mail.' );
	$aeon_result = $aeon_process( array_replace( $aeon_input, array( 'website' => 'https://spam.test' ) ), $aeon_page, $aeon_address() );
	$aeon_assert( isset( $aeon_result['errors']['general'] ) && ! $aeon_mail, 'Honeypot submissions must not send mail.' );
	foreach ( array( array( 'name' => '' ), array( 'email' => array( 'invalid' ) ), array( 'message' => '' ), array( 'name' => str_repeat( 'a', 101 ) ), array( 'message' => str_repeat( 'a', 5001 ) ), array( 'name' => str_repeat( '<b>', 40 ) . 'Visitor' ), array( 'email' => "visitor@example.test\n" ), array( 'email' => "visitor@example.test\r\nBcc: other@example.test" ) ) as $aeon_invalid ) {
		$aeon_field  = array_key_first( $aeon_invalid );
		$aeon_result = $aeon_process( array_replace( $aeon_input, $aeon_invalid ), $aeon_page, $aeon_address() );
		$aeon_assert( isset( $aeon_result['errors'][ $aeon_field ] ) && ! $aeon_mail, 'Invalid field accepted: ' . $aeon_field );
	}
	$aeon_result = $aeon_process( $aeon_input, $aeon_page, $aeon_address() );
	$aeon_assert( ! $aeon_result['errors'] && 1 === count( $aeon_mail ), 'Valid submission must send once.' );
	$aeon_assert( get_option( 'admin_email' ) === $aeon_mail[0]['to'], 'Recipient must remain server-side.' );
	$aeon_assert( ! str_contains( $aeon_mail[0]['message'], '<script>' ) && str_contains( $aeon_mail[0]['message'], 'Name: Visitor' ), 'Mail content was not sanitised.' );
	$aeon_assert( in_array( 'Reply-To: visitor@example.test', $aeon_mail[0]['headers'], true ), 'Reply-To must use the validated address.' );
	$aeon_fail   = true;
	$aeon_result = $aeon_process( $aeon_input, $aeon_page, $aeon_address() );
	$aeon_assert( isset( $aeon_result['errors']['general'] ) && 'Visitor' === $aeon_result['values']['name'], 'Delivery failures must retain safe input.' );
	$aeon_fail = false;
	$aeon_peer = $aeon_address();
	for ( $aeon_i = 0; $aeon_i < 5; ++$aeon_i ) {
		$aeon_process( array_replace( $aeon_input, array( 'email' => 'invalid' ) ), $aeon_page, $aeon_peer );
	}
	$aeon_count  = count( $aeon_mail );
	$aeon_result = $aeon_process( $aeon_input, $aeon_page, $aeon_peer );
	$aeon_assert( isset( $aeon_result['errors']['general'] ) && count( $aeon_mail ) === $aeon_count, 'Rate-limited attempts must not send mail.' );
	$aeon_assert( '<!-- wp:rune/contact-form /-->' === get_post_field( 'post_content', $aeon_page ), 'Submissions must not alter page content.' );
	WP_CLI::success( 'Contact validation, sanitisation, honeypot, rate limiting and delivery failure checks passed.' );
} finally {
	remove_filter( 'pre_wp_mail', $aeon_intercept );
	wp_delete_post( $aeon_page, true );
	foreach ( $aeon_addresses as $aeon_peer ) {
		delete_transient( 'aeon_contact_' . hash_hmac( 'sha256', $aeon_peer, wp_salt( 'nonce' ) ) );
	}
}
