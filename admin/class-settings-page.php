<?php
/**
 * Native WordPress administration for Aeon settings.
 *
 * @package Aeon
 */

namespace Aeon\Admin;

use Aeon\Settings;

/**
 * Provides Settings → Aeon using the core Settings API.
 */
final class SettingsPage {
	/** Settings page and option group identifier. */
	private const PAGE = 'aeon';

	/**
	 * Receive the shared settings service.
	 *
	 * @param Settings $settings Site settings.
	 */
	public function __construct( private readonly Settings $settings ) {}

	/**
	 * Register administration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the page for site administrators.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_options_page( __( 'Aeon', 'aeon' ), __( 'Aeon', 'aeon' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Register the option, sanitisation and form controls.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::PAGE,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'default'           => array( 'navigation' => true ),
				'sanitize_callback' => array( $this->settings, 'sanitise' ),
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'aeon_core', __( 'WordPress core features', 'aeon' ), '__return_null', self::PAGE );
		add_settings_field( 'aeon_navigation', __( 'Classic navigation menus', 'aeon' ), array( $this, 'navigation_field' ), self::PAGE, 'aeon_core' );
	}

	/**
	 * Render an accessible checkbox and explicit unchecked value.
	 *
	 * @return void
	 */
	public function navigation_field(): void {
		?>
		<input type="hidden" name="<?php echo esc_attr( Settings::OPTION ); ?>[navigation]" value="0">
		<label for="aeon-navigation">
			<input id="aeon-navigation" type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[navigation]" value="1" <?php checked( $this->settings->navigation_enabled() ); ?>>
			<?php esc_html_e( 'Enable Appearance → Menus', 'aeon' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Adds classic menu support to the active theme. Turning this off stops Aeon adding support; existing menus and support provided by your theme are preserved.', 'aeon' ); ?></p>
		<?php
	}

	/**
	 * Render the form; WordPress handles capability and nonce checks on save.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Aeon settings.', 'aeon' ), '', array( 'response' => 403 ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Aeon', 'aeon' ); ?></h1>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
