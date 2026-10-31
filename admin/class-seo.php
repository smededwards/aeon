<?php
/**
 * Native post/page SEO controls.
 *
 * @package Aeon
 */

namespace Aeon\Admin;

use Aeon\Features\Seo as Metadata;
use Aeon\Settings;

/**
 * Uses native meta boxes and the WordPress media picker in both editors.
 */
final class Seo {
	/**
	 * Receive site preferences.
	 *
	 * @param Settings $settings Feature settings.
	 */
	public function __construct( private readonly Settings $settings ) {}

	/**
	 * Register only while Aeon manages SEO output.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->settings->is_enabled( 'seo' ) ) {
			return;
		}
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post_post', array( $this, 'save' ) );
		add_action( 'save_post_page', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Add a single native panel below the post/page editor.
	 *
	 * @return void
	 */
	public function add_box(): void {
		add_meta_box( 'aeon-seo', __( 'SEO and sharing', 'aeon' ), array( $this, 'render' ), array( 'post', 'page' ), 'normal', 'default' );
	}

	/**
	 * Load the native media picker only on post/page editing screens.
	 *
	 * @param string $hook Current admin screen hook.
	 */
	public function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		$build_dir   = dirname( __DIR__ ) . '/build';
		$asset_file  = $build_dir . '/seo.asset.php';
		$script_file = $build_dir . '/seo.js';
		$style_file  = $build_dir . '/seo.css';

		if ( ! is_file( $script_file ) || ! is_file( $style_file ) ) {
			return;
		}

		wp_enqueue_media();
		$asset = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => '0.0.0',
		);

		wp_enqueue_script(
			'aeon-seo',
			plugins_url( 'build/seo.js', dirname( __DIR__ ) . '/aeon.php' ),
			array_merge( $asset['dependencies'], array( 'media-views' ) ),
			(string) filemtime( $script_file ),
			true
		);
		wp_enqueue_style(
			'aeon-seo',
			plugins_url( 'build/seo.css', dirname( __DIR__ ) . '/aeon.php' ),
			array(),
			(string) filemtime( $style_file )
		);
		wp_localize_script(
			'aeon-seo',
			'aeonSeo',
			array(
				'title'    => __( 'Choose a social image', 'aeon' ),
				'button'   => __( 'Use this image', 'aeon' ),
				'selected' => __( 'Social image selected.', 'aeon' ),
				'removed'  => __( 'Using the featured image.', 'aeon' ),
			)
		);
	}

	/**
	 * Render escaped fields; WordPress controls editor placement and persistence.
	 *
	 * @param \WP_Post $post Content being edited.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'aeon_seo_' . $post->ID, '_aeon_seo_nonce' );
		?>
		<div class="aeon-seo">
			<p><label for="aeon-seo-title-input"><?php esc_html_e( 'SEO title', 'aeon' ); ?></label></p>
			<input class="widefat" id="aeon-seo-title-input" name="_aeon_seo_title" type="text" value="<?php printf( '%s', esc_attr( get_post_meta( $post->ID, Metadata::TITLE, true ) ) ); ?>" aria-describedby="aeon-seo-title-help">
			<p class="description" id="aeon-seo-title-help"><?php esc_html_e( 'Optional. Leave blank for the WordPress title. Site name/tagline and pagination remain managed by WordPress.', 'aeon' ); ?></p>
			<p><label for="aeon-seo-description"><?php esc_html_e( 'Meta description', 'aeon' ); ?></label></p>
			<textarea class="widefat" id="aeon-seo-description" name="_aeon_seo_description" rows="3" aria-describedby="aeon-seo-description-help"><?php printf( '%s', esc_textarea( get_post_meta( $post->ID, Metadata::DESCRIPTION, true ) ) ); ?></textarea>
			<p class="description" id="aeon-seo-description-help"><?php esc_html_e( 'Optional. Leave blank for a short excerpt or text summary. Aim for a concise description of this page.', 'aeon' ); ?></p>
			<p><strong><?php esc_html_e( 'Social image', 'aeon' ); ?></strong></p>
			<?php
			$image_id = (int) get_post_meta( $post->ID, Metadata::IMAGE, true );
			$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
			?>
			<input id="aeon-social-image" name="_aeon_social_image" type="hidden" value="<?php printf( '%s', esc_attr( (string) $image_id ) ); ?>">
			<p><img class="aeon-seo__preview" id="aeon-social-preview" 
			<?php
			if ( $image ) :
				?>
				src="<?php printf( '%s', esc_url( $image ) ); ?>"<?php endif; ?> width="240" alt="" 
				<?php
				if ( ! $image ) :
					?>
					hidden<?php endif; ?>></p>
			<p>
				<button class="button aeon-seo__choose" type="button"><?php esc_html_e( 'Choose social image', 'aeon' ); ?></button>
				<button class="button aeon-seo__remove" type="button" 
				<?php
				if ( ! $image_id ) :
					?>
					hidden<?php endif; ?>><?php esc_html_e( 'Clear', 'aeon' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'The featured image is used unless you choose a different image here. Use a page-specific image around 1200 × 630 px and add meaningful alt text in the Media Library for the best social previews.', 'aeon' ); ?></p>
			<p class="aeon-seo__status screen-reader-text" aria-live="polite"></p>
		</div>
		<?php
	}

	/**
	 * Save only explicit, authorised submissions; absent fields preserve existing meta.
	 *
	 * @param int $post_id Content identifier.
	 */
	public function save( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['_aeon_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aeon_seo_nonce'] ) ), 'aeon_seo_' . $post_id ) ) {
			return;
		}
		foreach ( array( Metadata::TITLE, Metadata::DESCRIPTION, Metadata::IMAGE ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}
}
