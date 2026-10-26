<?php
/**
 * Native inserter checks executed within WordPress.
 *
 * @package Aeon
 */

$aeon_simplified = 'simplified' === ( $args[0] ?? '' );
$aeon_assert     = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
};
// Check both Openverse preferences without changing persisted settings.
$aeon_media_base = array(
	'enableOpenverseMediaCategory' => true,
	'aeon_media_marker'            => 'preserved',
);
foreach ( array( true, false ) as $aeon_hide ) {
	$aeon_option = static fn () => array( 'hide_openverse' => $aeon_hide );
	add_filter( 'pre_option_aeon_core_features', $aeon_option );
	$aeon_media  = apply_filters( 'block_editor_settings_all', $aeon_media_base, new WP_Block_Editor_Context() );
	remove_filter( 'pre_option_aeon_core_features', $aeon_option );
	$aeon_assert( ! $aeon_hide === $aeon_media['enableOpenverseMediaCategory'], 'Openverse did not follow its preference.' );
	$aeon_assert( 'preserved' === $aeon_media['aeon_media_marker'], 'An unrelated media setting changed.' );
}
$aeon_registry = WP_Block_Type_Registry::get_instance();
foreach ( array( 'post', 'page', 'wp_block' ) as $aeon_type ) {
	$aeon_context = new WP_Block_Editor_Context(
		array(
			'post' => new WP_Post(
				(object) array(
					'ID'        => 0,
					'post_type' => $aeon_type,
				)
			),
		)
	);
	$aeon_allowed = get_allowed_block_types( $aeon_context );
	if ( $aeon_simplified ) {
		$aeon_assert( is_array( $aeon_allowed ) && ! in_array( 'core/embed', $aeon_allowed, true ), 'The inserter was not simplified.' );
		foreach ( array( 'core/code', 'core/table', 'core/list-item', 'core/column', 'core/button', 'core/block', 'core/query', 'core/post-template', 'core/query-pagination', 'core/query-pagination-next', 'core/query-pagination-numbers', 'core/query-pagination-previous' ) as $aeon_required ) {
			$aeon_assert( in_array( $aeon_required, $aeon_allowed, true ), 'Required block missing: ' . $aeon_required );
		}
		$aeon_filter = static fn () => array( 'core/paragraph', 'core/embed' );
		add_filter( 'allowed_block_types_all', $aeon_filter, 99 );
		$aeon_assert( array( 'core/paragraph' ) === get_allowed_block_types( $aeon_context ), 'Earlier restrictions were overridden.' );
		remove_filter( 'allowed_block_types_all', $aeon_filter, 99 );
		add_filter( 'allowed_block_types_all', '__return_false', 99 );
		$aeon_assert( false === get_allowed_block_types( $aeon_context ), 'A disabled inserter was re-enabled.' );
		remove_filter( 'allowed_block_types_all', '__return_false', 99 );
	} else {
		$aeon_assert( true === $aeon_allowed, 'Turning simplification off did not restore the inserter.' );
	}
}
foreach ( array(
	null,
	new WP_Post(
		(object) array(
			'ID'        => 0,
			'post_type' => 'attachment',
		)
	),
) as $aeon_post ) {
	$aeon_assert( true === get_allowed_block_types( new WP_Block_Editor_Context( array( 'post' => $aeon_post ) ) ), 'An unrelated editor was restricted.' );
}
$aeon_assert( $aeon_registry->is_registered( 'core/embed' ), 'Hidden blocks must remain registered for rendering.' );

// Audit actual saved blocks without altering any content or publication state.
if ( $aeon_simplified ) {
	$aeon_walk = static function ( array $blocks, array $allowed ) use ( &$aeon_walk, $aeon_assert ): void {
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] ) {
				$aeon_assert( in_array( $block['blockName'], $allowed, true ), 'Existing content uses an excluded block: ' . $block['blockName'] );
			}
			$aeon_walk( $block['innerBlocks'], $allowed );
		}
	};
	foreach ( get_posts(
		array(
			'post_type'   => array( 'post', 'page', 'wp_block' ),
			'post_status' => 'any',
			'numberposts' => -1,
		)
	) as $aeon_item ) {
		$aeon_walk( parse_blocks( $aeon_item->post_content ), get_allowed_block_types( new WP_Block_Editor_Context( array( 'post' => $aeon_item ) ) ) );
	}
}
WP_CLI::success( 'Editor controls passed: ' . ( $args[0] ?? '' ) );
