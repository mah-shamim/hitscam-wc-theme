<?php
defined( 'ABSPATH' ) || exit;

function hctrk_defaults() {
	return array(
		'meta'    => array(
			'enabled' => 0, 'pixel_id' => '', 'capi' => 0, 'token' => '', 'test_code' => '', 'adv_match' => 1, 'content_id' => 'id',
			'events'  => array( 'pageview' => 1, 'viewcontent' => 1, 'viewcategory' => 1, 'search' => 1, 'addtocart' => 1, 'initiatecheckout' => 1, 'addpaymentinfo' => 1, 'purchase' => 1 ),
		),
		'google'  => array(
			'enabled' => 0, 'ga4_id' => '', 'ads_id' => '', 'ads_label' => '', 'anonymize' => 1, 'item_id' => 'id',
			'events'  => array( 'view_item' => 1, 'view_item_list' => 1, 'search' => 1, 'add_to_cart' => 1, 'begin_checkout' => 1, 'add_payment_info' => 1, 'purchase' => 1 ),
		),
		'gtm'     => array( 'enabled' => 0, 'container_id' => '', 'dl_name' => 'dataLayer', 'suppress_gtag' => 1, 'item_id' => 'id' ),
		'general' => array( 'exclude_roles' => array( 'administrator' ), 'require_consent' => 0, 'consent_cookie' => 'hc_consent', 'consent_mode' => 0, 'purchase_once' => 1, 'logging' => 1, 'log_ok' => 0 ),
	);
}

function hctrk_opts() {
	static $cache = null;
	if ( null === $cache ) {
		$cache = array_replace_recursive( hctrk_defaults(), (array) get_option( 'hctrk', array() ) );
	}
	return $cache;
}
function hctrk_opt( $group, $key = null ) {
	$o = hctrk_opts();
	return null === $key ? ( $o[ $group ] ?? array() ) : ( $o[ $group ][ $key ] ?? null );
}

/** ইউজার-রোল বা ফিল্টার দিয়ে ট্র্যাকিং বন্ধ? */
function hctrk_skip() {
	if ( is_admin() || wp_doing_ajax() && false ) {
		return true;
	}
	$skip = false;
	if ( is_user_logged_in() ) {
		$u = wp_get_current_user();
		if ( array_intersect( (array) hctrk_opt( 'general', 'exclude_roles' ), (array) $u->roles ) ) {
			$skip = true;
		}
	}
	return (bool) apply_filters( 'hitscam_tracking_skip', $skip );
}

/** সার্ভার-সাইডে কনসেন্ট আছে কি (CAPI/স্ক্রিপ্ট লোড) */
function hctrk_has_consent() {
	$ok = true;
	if ( hctrk_opt( 'general', 'require_consent' ) ) {
		$c  = $_COOKIE[ hctrk_opt( 'general', 'consent_cookie' ) ] ?? '';
		$ok = in_array( strtolower( (string) $c ), array( '1', 'true', 'all', 'granted', 'marketing', 'accepted' ), true );
	}
	return (bool) apply_filters( 'hitscam_tracking_has_consent', $ok );
}

function hctrk_sanitize( $in ) {
	$d   = hctrk_defaults();
	$out = $d;
	$b   = fn( $a, $k ) => empty( $a[ $k ] ) ? 0 : 1;
	$t   = fn( $a, $k ) => sanitize_text_field( $a[ $k ] ?? '' );
	$m   = $in['meta'] ?? array(); $g = $in['google'] ?? array(); $x = $in['gtm'] ?? array(); $n = $in['general'] ?? array();
	$out['meta'] = array(
		'enabled' => $b( $m, 'enabled' ), 'pixel_id' => preg_replace( '/[^0-9,]/', '', $m['pixel_id'] ?? '' ), 'capi' => $b( $m, 'capi' ), 'token' => $t( $m, 'token' ),
		'test_code' => $t( $m, 'test_code' ), 'adv_match' => $b( $m, 'adv_match' ), 'content_id' => 'sku' === ( $m['content_id'] ?? '' ) ? 'sku' : 'id', 'events' => array(),
	);
	foreach ( $d['meta']['events'] as $k => $_ ) { $out['meta']['events'][ $k ] = $b( $m['events'] ?? array(), $k ); }
	$out['google'] = array(
		'enabled' => $b( $g, 'enabled' ), 'ga4_id' => strtoupper( $t( $g, 'ga4_id' ) ), 'ads_id' => strtoupper( $t( $g, 'ads_id' ) ), 'ads_label' => $t( $g, 'ads_label' ),
		'anonymize' => $b( $g, 'anonymize' ), 'item_id' => 'sku' === ( $g['item_id'] ?? '' ) ? 'sku' : 'id', 'events' => array(),
	);
	foreach ( $d['google']['events'] as $k => $_ ) { $out['google']['events'][ $k ] = $b( $g['events'] ?? array(), $k ); }
	$out['gtm'] = array( 'enabled' => $b( $x, 'enabled' ), 'container_id' => strtoupper( $t( $x, 'container_id' ) ), 'dl_name' => preg_replace( '/[^A-Za-z0-9_]/', '', $x['dl_name'] ?? 'dataLayer' ) ?: 'dataLayer', 'suppress_gtag' => $b( $x, 'suppress_gtag' ), 'item_id' => 'sku' === ( $x['item_id'] ?? '' ) ? 'sku' : 'id' );
	$out['general'] = array(
		'exclude_roles' => array_map( 'sanitize_key', (array) ( $n['exclude_roles'] ?? array() ) ), 'require_consent' => $b( $n, 'require_consent' ),
		'consent_cookie' => preg_replace( '/[^A-Za-z0-9_\-]/', '', $n['consent_cookie'] ?? 'hc_consent' ) ?: 'hc_consent', 'consent_mode' => $b( $n, 'consent_mode' ),
		'purchase_once' => $b( $n, 'purchase_once' ), 'logging' => $b( $n, 'logging' ), 'log_ok' => $b( $n, 'log_ok' ),
	);
	return $out;
}
