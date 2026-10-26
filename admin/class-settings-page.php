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
				'default'           => Settings::DEFAULTS,
				'sanitize_callback' => array( $this->settings, 'sanitise' ),
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'aeon_core', __( 'WordPress core features', 'aeon' ), '__return_null', self::PAGE );
		add_settings_field( 'aeon_navigation', __( 'Classic navigation menus', 'aeon' ), array( $this, 'navigation_field' ), self::PAGE, 'aeon_core' );
		$fields = array(
			array(
				'key'         => 'seo',
				'title'       => __( 'SEO and sharing', 'aeon' ),
				'label'       => __( 'Manage SEO and sharing metadata with Aeon', 'aeon' ),
				'description' => __( 'Adds optional post/page fields and sharing tags. Turn this off before enabling another SEO metadata provider; saved overrides are retained.', 'aeon' ),
			),
			array(
				'key'         => 'hide_openverse',
				'title'       => __( 'Openverse', 'aeon' ),
				'label'       => __( 'Hide Openverse in the editor', 'aeon' ),
				'description' => __( 'Removes Openverse from editor media sources. Uploads and the Media Library remain available.', 'aeon' ),
			),
			array(
				'key'         => 'simplify_blocks',
				'title'       => __( 'Block editor', 'aeon' ),
				'label'       => __( 'Simplify available blocks', 'aeon' ),
				'description' => __( 'Limits the Posts, Pages and saved patterns inserter to essential content and layout blocks. Turning this off restores the full selection.', 'aeon' ),
			),
			array(
				'key'         => 'disable_comments',
				'title'       => __( 'Comments', 'aeon' ),
				'label'       => __( 'Disable comments and pingbacks on Posts and Pages', 'aeon' ),
				'description' => __( 'Hides comment interfaces while retaining saved comments and Settings → Discussion.', 'aeon' ),
			),
			array(
				'key'         => 'patterns_shortcut',
				'title'       => __( 'Patterns shortcut', 'aeon' ),
				'label'       => __( 'Show Appearance → Patterns', 'aeon' ),
				'description' => __( 'Adds a direct link to the native pattern manager. Turning it off hides only Aeon’s shortcut; patterns remain available through WordPress.', 'aeon' ),
			),
			array(
				'key'         => 'core_patterns',
				'title'       => __( 'Bundled core patterns', 'aeon' ),
				'label'       => __( 'Show WordPress’s bundled patterns', 'aeon' ),
				'description' => __( 'Controls built-in core layouts. Theme, plugin, remote and saved user patterns are preserved.', 'aeon' ),
			),
			array(
				'key'         => 'remote_patterns',
				'title'       => __( 'Remote patterns', 'aeon' ),
				'label'       => __( 'Allow patterns from WordPress.org', 'aeon' ),
				'description' => __( 'Controls automatic remote patterns and directory queries. Patterns already saved or inserted into content are preserved.', 'aeon' ),
			),
		);
		foreach ( $fields as $field ) {
			add_settings_field( 'aeon_' . $field['key'], $field['title'], array( $this, 'feature_field' ), self::PAGE, 'aeon_core', $field );
		}
	}

	/**
	 * Render an accessible checkbox and explicit unchecked value.
	 *
	 * @return void
	 */
	public function navigation_field(): void {
		?>
		<input type="hidden" name="<?php printf( '%s', esc_attr( Settings::OPTION ) ); ?>[navigation]" value="0">
		<label for="aeon-navigation">
			<input id="aeon-navigation" type="checkbox" name="<?php printf( '%s', esc_attr( Settings::OPTION ) ); ?>[navigation]" value="1" <?php checked( $this->settings->navigation_enabled() ); ?>>
			<?php esc_html_e( 'Enable Appearance → Menus', 'aeon' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Adds classic menu support to the active theme. Turning this off stops Aeon adding support; existing menus and support provided by your theme are preserved.', 'aeon' ); ?></p>
		<?php
	}

	/**
	 * Render a feature control using a known setting key.
	 *
	 * @param array<string, string> $field Field labels and identifier.
	 * @return void
	 */
	public function feature_field( array $field ): void {
		$key = $field['key'];
		?>
		<input type="hidden" name="<?php printf( '%s', esc_attr( Settings::OPTION . '[' . $key . ']' ) ); ?>" value="0">
		<label for="<?php printf( '%s', esc_attr( 'aeon-' . $key ) ); ?>">
			<input id="<?php printf( '%s', esc_attr( 'aeon-' . $key ) ); ?>" type="checkbox" name="<?php printf( '%s', esc_attr( Settings::OPTION . '[' . $key . ']' ) ); ?>" value="1" <?php checked( $this->settings->is_enabled( $key ) ); ?>>
			<?php printf( '%s', esc_html( $field['label'] ) ); ?>
		</label>
		<p class="description"><?php printf( '%s', esc_html( $field['description'] ) ); ?></p>
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
