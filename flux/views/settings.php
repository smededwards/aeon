<?php
/**
 * Flux settings table.
 *
 * @package Aeon
 *
 * @var array<string, string> $environments Environment labels.
 * @var string                $environment Selected environment key.
 * @var string                $url         Resolved environment URL.
 * @var bool                  $enabled     Whether Flux is enabled.
 * @var bool                  $allow_push  Whether remote writes are enabled.
 * @var string                $token       Decrypted shared token.
	 * @var bool                  $token_set   Whether a shared token is configured.
 */
?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="aeon-flux-enabled"><?php esc_html_e( 'Enable Flux', 'aeon' ); ?></label></th>
		<td>
			<label for="aeon-flux-enabled">
				<input type="checkbox" id="aeon-flux-enabled" name="<?php echo esc_attr( \Aeon\Flux\Settings::OPTION ); ?>[enabled]" value="1" <?php checked( $enabled ); ?>>
				<span class="description"><?php esc_html_e( 'Allow the editor to connect to the configured environment.', 'aeon' ); ?></span>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="aeon-flux-push"><?php esc_html_e( 'Allow Remote Writes', 'aeon' ); ?></label></th>
		<td>
			<label for="aeon-flux-push">
				<input type="checkbox" id="aeon-flux-push" name="<?php echo esc_attr( \Aeon\Flux\Settings::OPTION ); ?>[allow_push]" value="1" <?php checked( $allow_push ); ?>>
				<span class="description"><?php esc_html_e( 'Permit signed local-to-production updates after explicit editor confirmation.', 'aeon' ); ?></span>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="aeon-flux-environment"><?php esc_html_e( 'Remote Environment', 'aeon' ); ?></label></th>
		<td>
			<select id="aeon-flux-environment" name="<?php echo esc_attr( \Aeon\Flux\Settings::OPTION ); ?>[environment]">
				<?php foreach ( $environments as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $environment, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Uses the corresponding URL configured in Aeon Dashboard settings.', 'aeon' ); ?></p>
			<?php if ( $url ) : ?>
				<p class="description"><strong><?php esc_html_e( 'Resolved URL:', 'aeon' ); ?></strong> <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No URL is configured for this environment.', 'aeon' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php if ( $url ) : ?>
		<tr>
			<th scope="row"><label for="aeon-flux-token"><?php esc_html_e( 'Shared Connection Token', 'aeon' ); ?></label></th>
			<td>
				<input class="regular-text" type="password" id="aeon-flux-token" name="<?php echo esc_attr( \Aeon\Flux\Settings::OPTION ); ?>[token]" value="<?php echo esc_attr( $token ); ?>" autocomplete="new-password" <?php echo $token_set ? 'readonly' : 'required'; ?>>
				<p class="description">
					<?php if ( $token_set ) : ?>
						<?php esc_html_e( 'A shared token is configured. This field is readonly.', 'aeon' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'The token is encrypted with the WordPress authentication salts before storage.', 'aeon' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
	<?php endif; ?>
</table>
