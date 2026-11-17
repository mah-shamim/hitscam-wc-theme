<?php
/**
 * HitsCam থিম — লোডার।
 *
 * @package hitscam
 */

defined( 'ABSPATH' ) || exit;

define( 'HITSCAM_VERSION', '2.0.0' );
define( 'HITSCAM_DIR', get_template_directory() );
define( 'HITSCAM_URI', get_template_directory_uri() );

foreach ( array( 'setup', 'options', 'enqueue', 'plugins', 'store-setup', 'woocommerce', 'features', 'ajax', 'shortcodes' ) as $hitscam_inc ) {
	require_once HITSCAM_DIR . '/inc/' . $hitscam_inc . '.php';
}
unset( $hitscam_inc );
