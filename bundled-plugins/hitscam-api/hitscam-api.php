<?php
/**
 * Plugin Name: HitsCam API
 * Description: যেকোনো থার্ড-পার্টির জন্য WooCommerce REST API (namespace hitscam/v1) — নিজস্ব API key + স্কোপ + রেট-লিমিট, HMAC-সাইনড ওয়েবহুক, OpenAPI স্পেক।
 * Version: 1.0.0
 * Author: HitsCam
 * License: GPL-2.0-or-later
 * Text Domain: hitscam-api
 * Requires Plugins: woocommerce
 *
 * @package hitscam-api
 */
defined( 'ABSPATH' ) || exit;

define( 'HCAPI_VERSION', '1.0.0' );
define( 'HCAPI_DIR', __DIR__ );

require_once HCAPI_DIR . '/inc/keys.php';
require_once HCAPI_DIR . '/inc/webhooks.php';
require_once HCAPI_DIR . '/inc/rest.php';
require_once HCAPI_DIR . '/inc/openapi.php';
require_once HCAPI_DIR . '/inc/admin.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
