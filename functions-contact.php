<?php
/**
 * WordPress-compatible contact sanitisation adapter.
 *
 * @package Aeon
 */

/**
 * Delegate to the immutable input boundary recognised by Aeon's PHPCS ruleset.
 *
 * WPCS custom sanitiser configuration recognises global functions, not methods.
 * Keep validation in the namespaced class and keep this adapter free of effects.
 *
 * @param mixed  $input Unslashed submitted fields.
 * @param string $nonce Nonce supplied separately from visitor fields.
 * @return \Aeon\Features\Contact\Submission Sanitised values and validation errors.
 */
function aeon_sanitise_contact_submission( mixed $input, string $nonce = '' ): \Aeon\Features\Contact\Submission {
	return \Aeon\Features\Contact\Submission::from_input( $input, $nonce );
}
