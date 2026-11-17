<?php
/**
 * Plugin Name: HitsCam SEO
 * Description: অল-ইন-ওয়ান SEO — টাইটেল/মেটা ভেরিয়েবল, ইউনিভার্সাল মেটাবক্স + কনটেন্ট অ্যানালাইসিস, সোশ্যাল (OG/X), স্কিমা + নলেজ গ্রাফ, ব্রেডক্রাম্ব, সাইটম্যাপ (প্রোডাক্ট/নিউজ/ভিডিও/RSS), IndexNow + Google Indexing, 404 মনিটর + রিডাইরেক্ট, robots/.htaccess এডিটর, ইমপোর্ট, PageSpeed, WooCommerce মেটা।
 * Version: 1.0.0
 * Author: HitsCam
 * License: GPL-2.0-or-later
 * Text Domain: hitscam-seo
 *
 * @package hitscam-seo
 */
defined( 'ABSPATH' ) || exit;

define( 'HCSEO_VERSION', '1.0.0' );
define( 'HCSEO_DIR', __DIR__ );
define( 'HCSEO_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'settings', 'meta', 'schema', 'metabox', 'sitemaps', 'indexing', 'redirects', 'files', 'import', 'google', 'admin' ) as $f ) {
	require_once HCSEO_DIR . "/inc/$f.php";
}

register_activation_hook( __FILE__, function () {
	hcseo_install_tables();
	if ( false === get_option( 'hcseo_wizard_done' ) ) { set_transient( 'hcseo_redirect_wizard', 1, 60 ); }
	hcseo_sitemap_rewrites();
	flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
add_action( 'plugins_loaded', function () { if ( get_option( 'hcseo_db' ) !== HCSEO_VERSION ) { hcseo_install_tables(); update_option( 'hcseo_db', HCSEO_VERSION ); } } );
