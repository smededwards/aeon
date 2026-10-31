<?php
/**
 * Additional media upload formats.
 *
 * @package Aeon
 */

namespace Aeon\Features;

/** Enables WebP uploads and restricted SVG uploads for trusted administrators. */
final class MediaUploads {
	/** Register upload format filters. */
	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'add_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_filetype' ), 10, 5 );
	}

	/**
	 * Add supported image MIME types; SVG remains limited to trusted users.
	 *
	 * @param array<string, string> $mimes Allowed MIME types.
	 * @return array<string, string>
	 */
	public function add_mimes( array $mimes ): array {
		$mimes['webp'] = 'image/webp';

		if ( $this->can_upload_svg() ) {
			$mimes['svg'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/**
	 * Correct WordPress MIME detection for SVG and WebP.
	 *
	 * @param array<string, mixed> $data File type data.
	 * @param string               $file Full path to the file.
	 * @param string               $filename Original filename.
	 * @param string[]             $mimes Allowed MIME types.
	 * @param string|false         $real_mime Detected MIME type.
	 * @return array<string, mixed> Corrected MIME data for supported formats.
	 */
	public function check_filetype( array $data, string $file, string $filename, ?array $mimes, string|false $real_mime ): array {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'webp' === $extension && 'image/webp' === $real_mime ) {
			return array(
				'ext'             => 'webp',
				'type'            => 'image/webp',
				'proper_filename' => false,
			);
		}

		if ( 'svg' === $extension && $this->can_upload_svg() && $this->is_svg( $file ) ) {
			return array(
				'ext'             => 'svg',
				'type'            => 'image/svg+xml',
				'proper_filename' => false,
			);
		}

		return $data;
	}

	/** Check whether the current user can upload unfiltered SVG markup. */
	private function can_upload_svg(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * Verify the XML root without loading external entities or DTDs.
	 *
	 * @param string $file Upload temporary path.
	 * @return bool Whether the file is a valid SVG document.
	 */
	private function is_svg( string $file ): bool {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		if ( ! WP_Filesystem() || ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return false;
		}

		$contents = $wp_filesystem->get_contents( $file );
		if ( false === $contents || preg_match( '/<!DOCTYPE|<!ENTITY/i', $contents ) ) {
			return false;
		}

		$previous = libxml_use_internal_errors( true );
		$document = simplexml_load_string( $contents, \SimpleXMLElement::class, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return false !== $document && 'svg' === $document->getName()
			&& in_array( 'http://www.w3.org/2000/svg', $document->getDocNamespaces(), true );
	}
}
