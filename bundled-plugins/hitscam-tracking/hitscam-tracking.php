<?php
/**
 * Plugin Name: HitsCam Tracking (Meta Pixel + CAPI / GA4 / GTM)
 * Description: WooCommerce ইভেন্ট ট্র্যাকিং — Meta Pixel + Conversions API (ডিডুপ্লিকেশন সহ), Google Tag (GA4/Ads), GTM dataLayer; কনসেন্ট, রোল-এক্সক্লুশন, হুক লিস্ট ও লগ ট্যাব।
 * Version: 1.0.0
 * Author: HitsCam
 * License: GPL-2.0-or-later
 * Text Domain: hitscam-tracking
 * Requires Plugins: woocommerce
 *
 * @package hitscam-tracking
 */
defined( 'ABSPATH' ) || exit;

define( 'HCTRK_VERSION', '1.0.0' );
define( 'HCTRK_DIR', __DIR__ );
define( 'HCTRK_URL', plugin_dir_url( __FILE__ ) );

require_once HCTRK_DIR . '/inc/settings.php';
require_once HCTRK_DIR . '/inc/log.php';
require_once HCTRK_DIR . '/inc/events.php';
require_once HCTRK_DIR . '/inc/capi.php';
require_once HCTRK_DIR . '/inc/admin.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
