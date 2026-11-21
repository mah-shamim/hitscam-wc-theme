<?php
/**
 * HitsCam থিম — লোডার।
 *
 * @package hitscam
 */

defined( 'ABSPATH' ) || exit;

define( 'HITSCAM_VERSION', '2.8.0' );
define( 'HITSCAM_DIR', get_template_directory() );
define( 'HITSCAM_URI', get_template_directory_uri() );

foreach ( array( 'setup', 'options', 'enqueue', 'plugins', 'store-setup', 'woocommerce', 'features', 'pdp', 'ajax', 'shortcodes', 'pages' ) as $hitscam_inc ) {
	require_once HITSCAM_DIR . '/inc/' . $hitscam_inc . '.php';
}
unset( $hitscam_inc );

// অটো-লোডার: inc/modules/*.php, assets/js/modules/*.js, assets/css/modules/*.css — ফাইল দিলেই লোড হয়।
foreach ( glob( HITSCAM_DIR . '/inc/modules/*.php' ) ?: array() as $hitscam_mod ) {
	require_once $hitscam_mod;
}
unset( $hitscam_mod );
add_action( 'wp_enqueue_scripts', function () {
	foreach ( glob( HITSCAM_DIR . '/assets/css/modules/*.css' ) ?: array() as $f ) {
		wp_enqueue_style( 'hcm-' . basename( $f, '.css' ), HITSCAM_URI . '/assets/css/modules/' . basename( $f ), array( 'hitscam-wc' ), filemtime( $f ) );
	}
	foreach ( glob( HITSCAM_DIR . '/assets/js/modules/*.js' ) ?: array() as $f ) {
		wp_enqueue_script( 'hcm-' . basename( $f, '.js' ), HITSCAM_URI . '/assets/js/modules/' . basename( $f ), array( 'hitscam-shop' ), filemtime( $f ), true );
	}
}, 30 );
