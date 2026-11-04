<?php
/**
 * Native WordPress administration for Aeon settings.
 *
 * @package Aeon
 */

namespace Aeon\Admin;

use Aeon\Features\DefaultFeaturedImage;
use Aeon\Settings;

/**
 * Provides Settings → Aeon using the core Settings API.
 */
final class SettingsPage {
	// Settings page and option group identifier.
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
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
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
		register_setting(
			self::PAGE,
			DefaultFeaturedImage::OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( DefaultFeaturedImage::class, 'sanitise_image' ),
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'aeon_dashboard', __( 'Dashboard', 'aeon' ), '__return_null', self::PAGE );
		add_settings_section( 'aeon_content', __( 'Content', 'aeon' ), '__return_null', self::PAGE );
		add_settings_field( 'aeon_dashboard_environment', __( 'Dashboard Colour', 'aeon' ), array( $this, 'dashboard_environment_field' ), self::PAGE, 'aeon_dashboard' );
		$environments = Environment::ENVIRONMENTS;
		sort( $environments, SORT_STRING );
		foreach ( $environments as $environment ) {
			add_settings_field(
				'aeon_environment_url_' . $environment,
				sprintf(
					/* Translators: %s is an environment label. */
					__( '%s Site URL', 'aeon' ),
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
				'title'       => __( 'SEO and Sharing', 'aeon' ),
				'label'       => __( 'Manage SEO and sharing metadata with Aeon', 'aeon' ),
				'description' => __( 'Adds optional post/page fields and sharing tags. Turn this off before enabling another SEO metadata provider; saved overrides are retained.', 'aeon' ),
			),
		);
		foreach ( $fields as $field ) {
			if ( 'seo' === $field['key'] ) {
				add_settings_field( 'aeon_placeholder_image', __( 'Default Featured Image', 'aeon' ), array( $this, 'placeholder_image_field' ), self::PAGE, 'aeon_content' );
			}
			add_settings_field( 'aeon_' . $field['key'], $field['title'], array( $this, 'feature_field' ), self::PAGE, $field['section'], $field );
		}
	}

	/**
	 * Load the media picker only on Settings → Aeon.
	 *
	 * @param string $hook Current administration screen hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		$script = dirname( __DIR__ ) . '/build/placeholder-image.js';
		$style  = dirname( __DIR__ ) . '/build/placeholder-image.css';

		if ( ! is_file( $script ) || ! is_file( $style ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'aeon-placeholder-image',
			plugins_url( 'build/placeholder-image.js', dirname( __DIR__ ) . '/aeon.php' ),
			array( 'media-views' ),
			(string) filemtime( $script ),
			true
		);
		wp_enqueue_style(
			'aeon-placeholder-image',
			plugins_url( 'build/placeholder-image.css', dirname( __DIR__ ) . '/aeon.php' ),
			array(),
			(string) filemtime( $style )
		);
	}

	/**
	 * Render the dashboard colour selector.
	 *
	 * @return void
	 */
	public function dashboard_environment_field(): void {
		$selection = DashboardColour::selection();
		?>
		<select id="aeon-dashboard-environment" name="<?php printf( '%s', esc_attr( DashboardColour::OPTION ) ); ?>">
			<option value="default" <?php selected( $selection, 'default' ); ?>><?php esc_html_e( 'Default', 'aeon' ); ?></option>
			<option value="production" <?php selected( $selection, 'production' ); ?>><?php esc_html_e( 'Production', 'aeon' ); ?></option>
			<option value="development" <?php selected( $selection, 'development' ); ?>><?php esc_html_e( 'Development', 'aeon' ); ?></option>
			<option value="local" <?php selected( $selection, 'local' ); ?>><?php esc_html_e( 'Local', 'aeon' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Default uses the WordPress blue accent. Choose an environment to apply its dashboard colour.', 'aeon' ); ?></p>
		<?php
	}

	/**
	 * Render the fallback featured image selector.
	 *
	 * @return void
	 */
	public function placeholder_image_field(): void {
		$image_id  = DefaultFeaturedImage::image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : false;
		?>
		<div
			class="aeon-placeholder-image"
			data-image-title="<?php printf( '%s', esc_attr__( 'Choose the Default Featured Image', 'aeon' ) ); ?>"
			data-image-button="<?php printf( '%s', esc_attr__( 'Use as Default Featured Image', 'aeon' ) ); ?>"
			data-image-selected="<?php printf( '%s', esc_attr__( 'Default featured image selected.', 'aeon' ) ); ?>"
			data-image-removed="<?php printf( '%s', esc_attr__( 'Default featured image cleared.', 'aeon' ) ); ?>"
		>
			<input id="aeon-placeholder-image-id" type="hidden" name="<?php printf( '%s', esc_attr( DefaultFeaturedImage::OPTION ) ); ?>" value="<?php printf( '%s', esc_attr( (string) $image_id ) ); ?>">
			<img
				class="aeon-placeholder-image__preview"
				id="aeon-placeholder-image-preview"
				<?php if ( $image_url ) : ?>
				src="<?php printf( '%s', esc_url( $image_url ) ); ?>"
				<?php else : ?>
				hidden
				<?php endif; ?>
				alt=""
			>
			<p>
				<button class="button aeon-placeholder-image__choose" type="button"><?php esc_html_e( 'Choose Image', 'aeon' ); ?></button>
				<button class="button aeon-placeholder-image__clear" type="button"
					<?php if ( ! $image_id ) : ?>
					hidden
					<?php endif; ?>
				>
					<?php esc_html_e( 'Clear', 'aeon' ); ?>
				</button>
			</p>
			<p class="description"><?php esc_html_e( 'Used as the featured image on Posts and Pages that do not have one. The Media Library’s placeholder.png is selected automatically when available. An image selected on an individual item takes precedence; Clear disables the fallback. A missing attachment is never applied.', 'aeon' ); ?></p>
			<p class="aeon-placeholder-image__status screen-reader-text" aria-live="polite"></p>
		</div>
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
