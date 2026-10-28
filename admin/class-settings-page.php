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
		register_setting(
			self::PAGE,
			DashboardColour::OPTION,
			array(
				'type'              => 'string',
				'default'           => DashboardColour::DEFAULT_SELECTION,
				'sanitize_callback' => array( DashboardColour::class, 'sanitise_selection' ),
				'show_in_rest'      => false,
			)
		);
		register_setting(
			self::PAGE,
			Environment::OPTION,
			array(
				'type'              => 'array',
				'default'           => Environment::DEFAULT_URLS,
				'sanitize_callback' => array( Environment::class, 'sanitise_urls' ),
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'aeon_dashboard', __( 'Dashboard', 'aeon' ), '__return_null', self::PAGE );
		add_settings_section( 'aeon_content', __( 'Content', 'aeon' ), '__return_null', self::PAGE );
		add_settings_section( 'aeon_editor', __( 'Editor', 'aeon' ), '__return_null', self::PAGE );
		add_settings_field( 'aeon_dashboard_environment', __( 'Dashboard colour', 'aeon' ), array( $this, 'dashboard_environment_field' ), self::PAGE, 'aeon_dashboard' );
		$environments = Environment::ENVIRONMENTS;
		sort( $environments, SORT_STRING );
		foreach ( $environments as $environment ) {
			add_settings_field(
				'aeon_environment_url_' . $environment,
				sprintf(
					/* Translators: %s is an environment label. */
					__( '%s site URL', 'aeon' ),
					Environment::environment_label( $environment )
				),
				array( $this, 'environment_url_field' ),
				self::PAGE,
				'aeon_dashboard',
				array( 'environment' => $environment )
			);
		}
		$fields = array(
			array(
				'section'     => 'aeon_content',
				'key'         => 'disable_comments',
				'title'       => __( 'Comments', 'aeon' ),
				'label'       => __( 'Disable comments and pingbacks on Posts and Pages', 'aeon' ),
				'description' => __( 'Hides comment interfaces while retaining saved comments and Settings → Discussion.', 'aeon' ),
			),
			array(
				'section'     => 'aeon_content',
				'key'         => 'seo',
				'title'       => __( 'SEO and sharing', 'aeon' ),
				'label'       => __( 'Manage SEO and sharing metadata with Aeon', 'aeon' ),
				'description' => __( 'Adds optional post/page fields and sharing tags. Turn this off before enabling another SEO metadata provider; saved overrides are retained.', 'aeon' ),
			),
			array(
				'section'     => 'aeon_editor',
				'key'         => 'simplify_blocks',
				'title'       => __( 'Block editor', 'aeon' ),
				'label'       => __( 'Simplify available blocks', 'aeon' ),
				'description' => __( 'Limits the Posts, Pages and saved patterns inserter to essential content and layout blocks. Turning this off restores the full selection.', 'aeon' ),
			),
			array(
				'section'     => 'aeon_editor',
				'key'         => 'hide_openverse',
				'title'       => __( 'Openverse', 'aeon' ),
				'label'       => __( 'Hide Openverse in the editor', 'aeon' ),
				'description' => __( 'Removes Openverse from editor media sources. Uploads and the Media Library remain available.', 'aeon' ),
			),
			array(
				'section'     => 'aeon_editor',
				'key'         => 'core_patterns',
				'title'       => __( 'Bundled core patterns', 'aeon' ),
				'label'       => __( 'Show WordPress’s bundled patterns', 'aeon' ),
				'description' => __( 'Controls built-in core layouts. Theme, plugin, remote and saved user patterns are preserved.', 'aeon' ),
			),
			array(
				'section'     => 'aeon_editor',
				'key'         => 'patterns_shortcut',
				'title'       => __( 'Patterns shortcut', 'aeon' ),
				'label'       => __( 'Show Appearance → Patterns', 'aeon' ),
				'description' => __( 'Adds a direct link to the native pattern manager. Turning it off hides only Aeon’s shortcut; patterns remain available through WordPress.', 'aeon' ),
			),
			array(
				'section'     => 'aeon_editor',
				'key'         => 'remote_patterns',
				'title'       => __( 'Remote patterns', 'aeon' ),
				'label'       => __( 'Allow patterns from WordPress.org', 'aeon' ),
				'description' => __( 'Controls automatic remote patterns and directory queries. Patterns already saved or inserted into content are preserved.', 'aeon' ),
			),
		);
		foreach ( $fields as $field ) {
			add_settings_field( 'aeon_' . $field['key'], $field['title'], array( $this, 'feature_field' ), self::PAGE, $field['section'], $field );
		}
	}

	/** Render the dashboard colour selector. */
	public function dashboard_environment_field(): void {
		$selection = DashboardColour::selection();
		?>
		<select id="aeon-dashboard-environment" name="<?php printf( '%s', esc_attr( DashboardColour::OPTION ) ); ?>">
			<option value="default" <?php selected( $selection, 'default' ); ?>><?php esc_html_e( 'Default', 'aeon' ); ?></option>
			<option value="production" <?php selected( $selection, 'production' ); ?>><?php esc_html_e( 'Production', 'aeon' ); ?></option>
			<option value="development" <?php selected( $selection, 'development' ); ?>><?php esc_html_e( 'Development', 'aeon' ); ?></option>
			<option value="local" <?php selected( $selection, 'local' ); ?>><?php esc_html_e( 'Local', 'aeon' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Default follows Bedrock WP_ENV, then WP_ENVIRONMENT_TYPE. Unsupported or unset environments use WordPress blue. Choose an environment to override detection.', 'aeon' ); ?></p>
		<?php
	}

	/**
	 * Render a URL field for an environment destination.
	 *
	 * @param array<string, string> $field Field context.
	 * @return void
	 */
	public function environment_url_field( array $field ): void {
		$environment = $field['environment'];
		$urls        = Environment::urls();
		$name        = Environment::OPTION . '[' . $environment . ']';
		$id          = 'aeon-environment-url-' . $environment;
		$value       = $urls[ $environment ];

		if ( '' === $value && DashboardColour::detected_environment() === $environment ) {
			$value = home_url( '/' );
		}
		?>
		<input id="<?php printf( '%s', esc_attr( $id ) ); ?>" class="regular-text" type="url" name="<?php printf( '%s', esc_attr( $name ) ); ?>" value="<?php printf( '%s', esc_attr( $value ) ); ?>" placeholder="https://example.com">
		<p class="description"><?php esc_html_e( 'Enter each environment’s base URL. The active site URL is used automatically when its field is blank.', 'aeon' ); ?></p>
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
