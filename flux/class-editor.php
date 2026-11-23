<?php
/**
 * Flux editor controls.
 *
 * @package Aeon
 */

namespace Aeon\Flux;

/**
 * Adds a small, native metabox for single-record pull previews.
 */
final class Editor {
	/**
	 * Flux connection settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Create editor controls.
	 *
	 * @param Settings $settings Flux settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register editor hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'preview_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'admin_post_aeon_flux_preview', array( $this, 'preview' ) );
		add_action( 'admin_post_aeon_flux_apply', array( $this, 'apply' ) );
		add_action( 'admin_post_aeon_flux_push', array( $this, 'push' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Register the hidden preview screen.
	 *
	 * @return void
	 */
	public function preview_page(): void {
		add_submenu_page( null, __( 'Flux Preview', 'aeon' ), __( 'Flux Preview', 'aeon' ), 'edit_posts', 'aeon-flux-preview', array( $this, 'render_preview' ) );
	}

	/**
	 * Add the Flux metabox to Posts and Pages.
	 *
	 * @return void
	 */
	public function meta_box(): void {
		add_meta_box( 'aeon-flux', __( 'Flux Synchronisation', 'aeon' ), array( $this, 'render' ), array( 'post', 'page' ), 'side' );
	}

	/**
	 * Render the connection and remote ID controls.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$connection = $this->settings->connection();
		if ( empty( $connection['enabled'] ) ) {
			printf( '<p>%s</p>', esc_html__( 'Enable Flux in Settings to connect this record.', 'aeon' ) );
			return;
		}

		$mapping   = Mapping::find_local( $post->post_type, $post->ID, (string) $connection['url'] );
		$remote_id = $mapping ? (int) $mapping->remote_id : 0;
		printf(
			'<p><label for="aeon-flux-remote-id">%1$s</label><input class="widefat" type="number" min="1" id="aeon-flux-remote-id" name="aeon_flux_remote_id" value="%2$d" /></p><p class="description">%3$s</p>',
			esc_html__( 'Remote Record ID', 'aeon' ),
			esc_attr( $remote_id ),
			esc_html__( 'Use the source Post or Page ID until the mapping is established.', 'aeon' )
		);
		wp_nonce_field( 'aeon_flux_preview_' . $post->ID, 'aeon_flux_nonce' );
		printf(
			'<p><button type="submit" class="button" formaction="%1$s" formmethod="post">%2$s</button></p>',
			esc_url( admin_url( 'admin-post.php?action=aeon_flux_preview&post_id=' . $post->ID ) ),
			esc_html__( 'Preview Remote Changes', 'aeon' )
		);
		if ( $mapping && ! empty( $connection['allow_push'] ) && current_user_can( 'manage_options' ) ) {
			printf(
				'<p><button type="submit" class="button" formaction="%1$s" formmethod="post" onclick="return confirm(\'%2$s\');">%3$s</button></p>',
				esc_url( admin_url( 'admin-post.php?action=aeon_flux_push&post_id=' . $post->ID ) ),
				esc_js( __( 'Push this local record to the configured Production environment?', 'aeon' ) ),
				esc_html__( 'Push to Production', 'aeon' )
			);
		}
	}

	/**
	 * Fetch and store a remote preview, then redirect to the diff screen.
	 *
	 * @return void
	 */
	public function preview(): void {
		$post_id = absint( $_REQUEST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'aeon_flux_preview_' . $post_id, 'aeon_flux_nonce' );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to sync this record.', 'aeon' ) );
		}
		$remote_id  = absint( $_REQUEST['aeon_flux_remote_id'] ?? 0 );
		$connection = $this->settings->connection();
		$remote     = ( new Client( $connection ) )->get_record( get_post_type( $post_id ), $remote_id );
		if ( is_wp_error( $remote ) ) {
			wp_safe_redirect( add_query_arg( 'aeon_flux_error', rawurlencode( $remote->get_error_message() ), wp_get_referer() ) );
			exit;
		}
		$token = wp_generate_password( 32, false );
		set_transient(
			'aeon_flux_preview_' . get_current_user_id() . '_' . $token,
			array(
				'post_id' => $post_id,
				'remote'  => $remote,
			),
			10 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'admin.php?page=aeon-flux-preview&token=' . rawurlencode( $token ) ) );
		exit;
	}

	/**
	 * Render the field-level preview and guarded apply action.
	 *
	 * @return void
	 */
	public function render_preview(): void {
		$token = sanitize_key( $_GET['token'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data  = get_transient( 'aeon_flux_preview_' . get_current_user_id() . '_' . $token );
		if ( ! is_array( $data ) || ! current_user_can( 'edit_post', $data['post_id'] ) ) {
			wp_die( esc_html__( 'The Flux preview has expired or is not available.', 'aeon' ) );
		}

		$local  = Payload::from_post( get_post( $data['post_id'] ) );
		$remote = $data['remote'];
		$diff   = Diff::compare( $local, $remote );
		printf( '<div class="wrap"><h1>%s</h1><p>%s</p>', esc_html__( 'Flux Preview', 'aeon' ), esc_html__( 'Review the source and local values before applying the remote record.', 'aeon' ) );
		if ( empty( $diff ) ) {
			printf( '<p>%s</p></div>', esc_html__( 'No field changes were found.', 'aeon' ) );
			return;
		}
		printf( '<table class="widefat striped"><thead><tr><th>%1$s</th><th>%2$s</th><th>%3$s</th></tr></thead><tbody>', esc_html__( 'Field', 'aeon' ), esc_html__( 'Local', 'aeon' ), esc_html__( 'Remote', 'aeon' ) );
		foreach ( $diff as $field => $values ) {
			printf( '<tr><td><label><input type="checkbox" name="fields[]" value="%1$s" checked /> <code>%1$s</code></label></td><td><pre>%2$s</pre></td><td><pre>%3$s</pre></td></tr>', esc_attr( $field ), esc_html( wp_json_encode( $values['local'], JSON_PRETTY_PRINT ) ), esc_html( wp_json_encode( $values['remote'], JSON_PRETTY_PRINT ) ) );
		}
		printf( '</tbody></table><form method="post" action="%1$s">%2$s<input type="hidden" name="action" value="aeon_flux_apply" /><input type="hidden" name="token" value="%3$s" /><p><button type="submit" class="button button-primary">%4$s</button></p></form></div>', esc_url( admin_url( 'admin-post.php' ) ), wp_nonce_field( 'aeon_flux_apply_' . $token, '_wpnonce', true, false ), esc_attr( $token ), esc_html__( 'Apply Selected Changes', 'aeon' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Apply the stored preview to the local record.
	 *
	 * @return void
	 */
	public function apply(): void {
		$token = sanitize_key( $_REQUEST['token'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data  = get_transient( 'aeon_flux_preview_' . get_current_user_id() . '_' . $token );
		if ( ! is_array( $data ) || ! current_user_can( 'edit_post', $data['post_id'] ) ) {
			wp_die( esc_html__( 'The Flux preview has expired or is not available.', 'aeon' ) );
		}
		check_admin_referer( 'aeon_flux_apply_' . $token );
		$raw_fields = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$selected   = array();
		foreach ( (array) $raw_fields as $field ) {
			$selected[] = sanitize_text_field( $field );
		}
		$result = Applier::apply( (int) $data['post_id'], $data['remote'], $selected );
		delete_transient( 'aeon_flux_preview_' . get_current_user_id() . '_' . $token );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		$connection = $this->settings->connection();
		Database::log( $token, 'pull', get_post_type( $data['post_id'] ), (int) $data['post_id'], (int) $data['remote']['source_id'], 'success' );
		Mapping::save( get_post_type( $data['post_id'] ), (int) $data['post_id'], (int) $data['remote']['source_id'], (string) $connection['url'], Payload::snapshot( $data['remote'] ) );
		wp_safe_redirect( add_query_arg( 'aeon_flux_synced', 1, get_edit_post_link( $data['post_id'], 'url' ) ) );
		exit;
	}

	/**
	 * Push a local record after an explicit administrator confirmation.
	 *
	 * @return void
	 */
	public function push(): void {
		$post_id = absint( $_REQUEST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'aeon_flux_preview_' . $post_id, 'aeon_flux_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only an administrator can push records to Production.', 'aeon' ) );
		}
		$connection = $this->settings->connection();
		$mapping    = Mapping::find_local( get_post_type( $post_id ), $post_id, (string) $connection['url'] );
		if ( ! $mapping || empty( $connection['allow_push'] ) ) {
			wp_die( esc_html__( 'Flux remote writes are not enabled for this record.', 'aeon' ) );
		}
		$response = ( new Client( $connection ) )->update_record( get_post_type( $post_id ), (int) $mapping->remote_id, Payload::from_post( get_post( $post_id ) ) );
		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( add_query_arg( 'aeon_flux_error', rawurlencode( $response->get_error_message() ), get_edit_post_link( $post_id, 'url' ) ) );
			exit;
		}
		Mapping::save( get_post_type( $post_id ), $post_id, (int) $mapping->remote_id, (string) $connection['url'], Payload::snapshot( Payload::from_post( get_post( $post_id ) ) ) );
		Database::log( wp_generate_uuid4(), 'push', get_post_type( $post_id ), $post_id, (int) $mapping->remote_id, 'success' );
		wp_safe_redirect( add_query_arg( 'aeon_flux_pushed', 1, get_edit_post_link( $post_id, 'url' ) ) );
		exit;
	}

	/**
	 * Display the result of an apply operation.
	 *
	 * @return void
	 */
	public function notice(): void {
		if ( isset( $_GET['aeon_flux_synced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Flux applied the remote record and created a local revision.', 'aeon' ) );
		}
		if ( isset( $_GET['aeon_flux_pushed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Flux pushed the local record to Production.', 'aeon' ) );
		}
		if ( isset( $_GET['aeon_flux_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( $_GET['aeon_flux_error'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
}
