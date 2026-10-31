<?php
/**
 * Verify environment aliases, precedence and unsupported-value handling.
 *
 * @package Aeon
 */

$aeon_scenarios = array(
	'priority'    => array(
		'wp_env'              => 'dev',
		'wp_environment_type' => 'production',
		'expected'            => 'development',
	),
	'fallback'    => array(
		'wp_env'              => 'staging',
		'wp_environment_type' => 'local',
		'expected'            => 'local',
	),
	'unsupported' => array(
		'wp_env'              => 'staging',
		'wp_environment_type' => 'preview',
		'expected'            => null,
	),
);
$aeon_scenario  = $aeon_scenarios[ $argv[1] ?? '' ] ?? null;

if ( ! $aeon_scenario ) {
	exit( 2 );
}

define( 'WP_ENV', $aeon_scenario['wp_env'] );
define( 'WP_ENVIRONMENT_TYPE', $aeon_scenario['wp_environment_type'] );

require dirname( __DIR__ ) . '/admin/class-environment.php';

$aeon_environment = \Aeon\Admin\Environment::class;
$aeon_detected    = $aeon_environment::detected_environment();

if ( $aeon_scenario['expected'] !== $aeon_detected ) {
	exit( 1 );
}

if (
	'development' !== $aeon_environment::normalise_environment( 'DEV' ) ||
	'production' !== $aeon_environment::normalise_environment( 'prod' ) ||
	null !== $aeon_environment::normalise_environment( 'preview' )
) {
	exit( 1 );
}
