<?php
/**
 * e9 — বিবিধ: চেকআউট ফিল্ড ক্রম + ছবি, মেনু ডিডুপ, স্পেসিফিকেশন (DB), সাবস্ক্রাইব সেকশন, কোভিউ ফিক্স।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

/* ---------- চেকআউট ফিল্ড ক্রম: নাম, শেষাংশ, ফোন, ইমেইল, ঠিকানা, শহর, জেলা, পোস্টকোড, দেশ, নোট ---------- */
add_filter( 'woocommerce_checkout_fields', function ( $f ) {
	$pr = array( 'billing_first_name' => 10, 'billing_last_name' => 20, 'billing_phone' => 30, 'billing_email' => 40, 'billing_address_1' => 50, 'billing_city' => 60, 'billing_state' => 70, 'billing_postcode' => 80, 'billing_country' => 90 );
	foreach ( $pr as $k => $p ) { if ( isset( $f['billing'][ $k ] ) ) { $f['billing'][ $k ]['priority'] = $p; } }
	foreach ( array( 'billing_phone', 'billing_email' ) as $k ) { if ( isset( $f['billing'][ $k ] ) ) { $f['billing'][ $k ]['class'] = array( 'form-row-wide' ); } }
	if ( isset( $f['billing']['billing_phone'] ) ) { $f['billing']['billing_phone']['required'] = true; }
	return $f;
}, 999 );

/* ---------- রিভিউ অর্ডারে পণ্যের ছবি ---------- */
add_filter( 'woocommerce_cart_item_name', function ( $name, $item ) {
	if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) { return $name; }
	$p = $item['data'] ?? null; if ( ! $p ) { return $name; }
	return $p->get_image( array( 52, 52 ), array( 'class' => 'hc-thumb' ) ) . '<span>' . $name . '</span>';
}, 20, 2 );

/* ---------- কোভিউ/আপসেল: লুকানো (hidden) পণ্য বাদ ---------- */
add_filter( 'hitscam_visible_only', '__return_true' );

/* ---------- মেনু ডিডুপ (object_id/URL/শিরোনাম একই হলে প্রথমটি রেখে বাকি মুছে) ---------- */
function hitscam_e9_dedupe_menus() {
	foreach ( wp_get_nav_menus() as $m ) {
		$seen = array();
		foreach ( (array) wp_get_nav_menu_items( $m->term_id ) as $it ) {
			if ( (int) $it->menu_item_parent ) { continue; }
			$k = ( $it->object_id && 'custom' !== $it->type ? $it->type . ':' . $it->object_id : untrailingslashit( preg_replace( '#^https?://#', '', $it->url ) ) );
			$t = mb_strtolower( trim( $it->title ) );
			if ( isset( $seen[ $k ] ) || isset( $seen[ 't:' . $t ] ) ) { wp_delete_post( $it->ID, true ); continue; }
			$seen[ $k ] = 1; $seen[ 't:' . $t ] = 1;
		}
	}
}
add_action( 'init', function () {
	if ( (int) get_option( 'hitscam_e9_menu', 0 ) >= 1 || ! function_exists( 'hitscam_e8_sync' ) ) { return; }
	hitscam_e9_dedupe_menus(); update_option( 'hitscam_e9_menu', 1 );
}, 40 );
// সিঙ্কের পর প্রতিবার ডিডুপ
add_action( 'update_option_hitscam_pages_x', 'hitscam_e9_dedupe_menus' );

/* ---------- স্পেসিফিকেশন / ফিচার (DB: _hc_specs, এক লাইনে এক ফিচার) ---------- */
const HITSCAM_SPEC_LABEL = 'Specifications / ফিচার';
function hitscam_specs( $pid ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $pid, '_hc_specs', true ) ) ) ) );
}
/** বর্ণনা থেকে "Specifications / ফিচার" ব্লক ও বুলেট আলাদা করে (clean_description, specs[]) */
function hitscam_e9_extract_specs( $html ) {
	$txt = preg_replace( '#<\s*br\s*/?>#i', "\n", $html );
	$txt = preg_replace( '#</(p|li|h[1-6]|div|ul|ol)>#i', "\n", $txt );
	$txt = html_entity_decode( wp_strip_all_tags( $txt ) );
	if ( ! preg_match( '/^\s*(?:specifications?\s*(?:\/|&|and)?\s*(?:ফিচার|features?)?|features?\s*\/?\s*ফিচার|ফিচার|specifications?)\s*:?\s*$/imu', $txt, $m, PREG_OFFSET_CAPTURE ) ) { return array( $html, array() ); }
	$after = substr( $txt, $m[0][1] + strlen( $m[0][0] ) );
	$lines = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $after ) as $l ) {
		$l = trim( preg_replace( '/^[\s\-\*•·▪●✓✔]+/u', '', $l ) );
		if ( '' !== $l ) { $lines[] = $l; }
	}
	if ( ! $lines ) { return array( $html, array() ); }
	$before = trim( substr( $txt, 0, $m[0][1] ) );
	$clean  = $before ? '<p>' . nl2br( esc_html( $before ) ) . '</p>' : '';
	return array( $clean, $lines );
}
function hitscam_e9_migrate_product( $pid ) {
	$p = wc_get_product( $pid ); if ( ! $p || get_post_meta( $pid, '_hc_specs', true ) ) { return false; }
	foreach ( array( 'description', 'short_description' ) as $fld ) {
		$get = 'get_' . $fld; $set = 'set_' . $fld;
		list( $clean, $specs ) = hitscam_e9_extract_specs( $p->$get() );
		if ( $specs ) { update_post_meta( $pid, '_hc_specs', implode( "\n", $specs ) ); $p->$set( $clean ); $p->save(); return true; }
	}
	return false;
}
add_action( 'save_post_product', function ( $id ) {
	static $busy = false; if ( $busy || wp_is_post_revision( $id ) ) { return; }
	$busy = true; hitscam_e9_migrate_product( $id ); $busy = false;
}, 30 );
add_action( 'init', function () { // একবার: বিদ্যমান সব পণ্য
	if ( get_option( 'hitscam_e9_specs' ) || ! function_exists( 'wc_get_products' ) ) { return; }
	foreach ( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => array( 'publish', 'draft', 'private' ) ) ) as $id ) { hitscam_e9_migrate_product( $id ); }
	update_option( 'hitscam_e9_specs', 1 );
}, 50 );
add_action( 'woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_textarea_input( array( 'id' => '_hc_specs', 'label' => 'Specifications / ফিচার', 'description' => 'প্রতি লাইনে একটি ফিচার। পণ্য পেজের "ডিটেলস" ও কার্ডে দেখায়।', 'rows' => 7 ) );
} );
add_action( 'woocommerce_process_product_meta', function ( $id ) {
	if ( isset( $_POST['_hc_specs'] ) ) { update_post_meta( $id, '_hc_specs', sanitize_textarea_field( wp_unslash( $_POST['_hc_specs'] ) ) ); } // phpcs:ignore WordPress.Security.NonceVerification
} );
/** পণ্য পেজে ডিটেলস টেবিলের নিচে স্পেস লিস্ট */
function hitscam_specs_html( $pid ) {
	$s = hitscam_specs( $pid ); if ( ! $s ) { return ''; }
	return '<h6 class="fw-bold mt-3">' . esc_html( HITSCAM_SPEC_LABEL ) . '</h6><ul class="spec-list">' . implode( '', array_map( fn( $l ) => '<li><i class="bi bi-check2-circle text-brand"></i> ' . esc_html( $l ) . '</li>', $s ) ) . '</ul>';
}
// কার্ডের info: ভিজিবল অ্যাট্রিবিউট না থাকলে প্রথম ৪ স্পেস
add_filter( 'hitscam_card_info_fallback', function ( $out, $p ) {
	if ( $out ) { return $out; }
	foreach ( array_slice( hitscam_specs( $p->get_id() ), 0, 4 ) as $l ) { $out[] = array( 'bi-check2-circle', $l ); }
	return $out;
}, 10, 2 );

/* ---------- সাবস্ক্রাইব সেকশন (হোম) ---------- */
function hitscam_subscribe_html() {
	return '<section class="container section" id="hcSubscribe"><div class="newsletter"><div class="row align-items-center g-3"><div class="col-lg-6"><h3 class="fw-bold">' . esc_html( hitscam_opt( 'nl_title', 'নতুন পণ্য ও অফারের খবর সবার আগে পান' ) ) . '</h3><p class="mb-0 opacity-75">নতুন পণ্য বা পরিষেবা চালু হলে এবং বিশেষ অফারের সতর্কবার্তা সরাসরি আপনার ইনবক্সে।</p></div><div class="col-lg-6"><form class="input-group input-group-lg" id="nlForm" novalidate><input type="text" class="form-control" name="contact" placeholder="আপনার ই-মেইল বা মোবাইল নাম্বার" required aria-label="ই-মেইল বা মোবাইল"><input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"><button class="btn btn-brand">সাবস্ক্রাইব</button></form><div class="small mt-2" id="nlMsg"></div></div></div></div></section>';
}
add_action( 'hitscam_after_home', function () { if ( get_option( 'hitscam_show_subscribe', 1 ) ) { echo hitscam_subscribe_html(); } } ); // phpcs:ignore
add_shortcode( 'hitscam_subscribe', 'hitscam_subscribe_html' );
add_action( 'wp_ajax_hc_subscribe', 'hitscam_e9_subscribe' );
add_action( 'wp_ajax_nopriv_hc_subscribe', 'hitscam_e9_subscribe' );
function hitscam_e9_subscribe() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! empty( $_POST['website'] ) ) { wp_send_json_success(); }
	$c = sanitize_text_field( wp_unslash( $_POST['contact'] ?? '' ) );
	$em = is_email( $c ); $ph = hitscam_normalize_bd_phone( $c );
	if ( ! $em && ! $ph ) { wp_send_json_error( array( 'msg' => 'সঠিক ই-মেইল বা মোবাইল নাম্বার দিন।' ) ); }
	if ( function_exists( 'hitscam_a2_limit' ) && ! hitscam_a2_limit( 'sub', 5, 600 ) ) { wp_send_json_error( array( 'msg' => 'একটু পরে চেষ্টা করুন।' ) ); }
	$key = $em ? strtolower( $c ) : $ph;
	if ( get_posts( array( 'post_type' => 'hc_lead', 'meta_key' => '_hc_sub', 'meta_value' => $key, 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) { wp_send_json_success( array( 'msg' => 'আপনি আগেই সাবস্ক্রাইব করেছেন ✓' ) ); }
	$id = wp_insert_post( array( 'post_type' => 'hc_lead', 'post_status' => 'publish', 'post_title' => 'সাবস্ক্রাইব: ' . $key ) );
	if ( $id ) { update_post_meta( $id, '_hc_sub', $key ); update_post_meta( $id, '_hc_type', 'subscribe' ); }
	wp_send_json_success( array( 'msg' => 'ধন্যবাদ! সাবস্ক্রাইব সম্পন্ন ✓' ) );
}

/* WooCommerce চেকআউট JS দেশের লোকেল দিয়ে লেবেল/ক্রম বদলায় — BD লোকেলেও আমাদের লেবেল ও ক্রম */
add_filter( 'woocommerce_get_country_locale', function ( $l ) {
	$l['BD'] = array_replace_recursive( $l['BD'] ?? array(), array(
		'first_name' => array( 'priority' => 10 ), 'last_name' => array( 'priority' => 20 ),
		'phone'      => array( 'label' => 'মোবাইল নম্বর', 'required' => true, 'priority' => 30 ),
		'email'      => array( 'priority' => 40 ),
		'address_1'  => array( 'label' => 'পূর্ণ ঠিকানা (বাসা/রোড/এলাকা)', 'priority' => 50 ),
		'city'       => array( 'label' => 'থানা/উপজেলা', 'priority' => 60, 'required' => true ),
		'state'      => array( 'label' => 'জেলা', 'priority' => 70, 'required' => true ),
		'postcode'   => array( 'priority' => 80, 'required' => false ),
		'country'    => array( 'priority' => 90 ),
	) );
	return $l;
} );
add_filter( 'woocommerce_default_address_fields', function ( $f ) {
	foreach ( array( 'first_name' => 10, 'last_name' => 20, 'address_1' => 50, 'city' => 60, 'state' => 70, 'postcode' => 80, 'country' => 90 ) as $k => $p ) { if ( isset( $f[ $k ] ) ) { $f[ $k ]['priority'] = $p; } }
	return $f;
}, 99 );

add_filter( 'woocommerce_account_menu_items', function ( $i ) {
	$bn = array( 'dashboard' => 'ড্যাশবোর্ড', 'orders' => 'অর্ডারসমূহ', 'downloads' => 'ডাউনলোড', 'edit-address' => 'ঠিকানা', 'payment-methods' => 'পেমেন্ট মেথড', 'edit-account' => 'অ্যাকাউন্ট ডিটেইলস', 'customer-logout' => 'লগআউট' );
	foreach ( $bn as $k => $v ) { if ( isset( $i[ $k ] ) ) { $i[ $k ] = $v; } }
	return $i;
} );
