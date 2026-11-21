<?php
/**
 * b2 — কুপন ওয়ালেট + সেরা কুপন অটো-অ্যাপ্লাই (WooCommerce কুপন থেকে), কর্মঘণ্টা ব্যাজ, দাম-কমলে/স্টকে-এলে অ্যালার্ট পাঠানো।
 * কুপনকে ওয়ালেটে দেখাতে: কুপন সম্পাদনায় কাস্টম ফিল্ড `_hc_wallet` = yes (ডেমো কুপন HITS10 / WELCOME100 / CAM500 নিজে তৈরি হয়)।
 * টেমপ্লেট: extras.js
 */
defined( 'ABSPATH' ) || exit;

/* ---------- ডেমো কুপন (একবার) ---------- */
add_action( 'init', function () {
	if ( get_option( 'hitscam_b2_coupons' ) || ! class_exists( 'WC_Coupon' ) ) { return; }
	update_option( 'hitscam_b2_coupons', 1 );
	foreach ( array( 'HITS10' => array( 'percent', 10, 0, '১০% ছাড়' ), 'WELCOME100' => array( 'fixed_cart', 100, 0, '৳১০০ ছাড়' ), 'CAM500' => array( 'fixed_cart', 500, 15000, '৳৫০০ ছাড় (৳১৫,০০০+ অর্ডারে)' ) ) as $code => $d ) {
		if ( wc_get_coupon_id_by_code( $code ) ) { continue; }
		$c = new WC_Coupon(); $c->set_code( $code ); $c->set_discount_type( $d[0] ); $c->set_amount( $d[1] ); $c->set_minimum_amount( $d[2] ); $c->set_description( $d[3] );
		$c->update_meta_data( '_hc_wallet', 'yes' ); $c->save();
	}
}, 50 );

add_action( 'woocommerce_coupon_options', function () {
	woocommerce_wp_checkbox( array( 'id' => '_hc_wallet', 'label' => 'ওয়ালেটে দেখান', 'description' => 'HitsCam কুপন ওয়ালেটে (কার্ট/চেকআউট) গ্রাহককে দেখাবে' ) );
} );
add_action( 'woocommerce_coupon_options_save', function ( $id ) {
	update_post_meta( $id, '_hc_wallet', isset( $_POST['_hc_wallet'] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification
} );

function hitscam_wallet_items() {
	$ids = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids', 'meta_key' => '_hc_wallet', 'meta_value' => 'yes' ) );
	$sub = WC()->cart ? (float) WC()->cart->get_subtotal() : 0; $out = array();
	foreach ( $ids as $id ) {
		$c = new WC_Coupon( $id );
		if ( $c->get_date_expires() && $c->get_date_expires()->getTimestamp() < time() ) { continue; }
		if ( $c->get_usage_limit() && $c->get_usage_count() >= $c->get_usage_limit() ) { continue; }
		$min = (float) $c->get_minimum_amount();
		$s   = $sub < $min ? 0 : ( 'percent' === $c->get_discount_type() ? round( $sub * $c->get_amount() / 100 ) : min( (float) $c->get_amount(), $sub ) );
		$out[] = array( 'code' => strtoupper( $c->get_code() ), 'label' => $c->get_description() ?: $c->get_code(), 's' => $s, 'need' => max( 0, $min - $sub ) );
	}
	return $out;
}
function hitscam_wallet_html() {
	if ( ! is_user_logged_in() && ! get_option( 'hitscam_coupon_guest', 0 ) ) {
		return '<div class="wallet" id="couponWallet"><div class="small"><i class="bi bi-ticket-perforated text-brand"></i> কুপন ব্যবহার করতে <a class="text-brand fw-semibold" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">লগইন / রেজিস্টার করুন</a>।</div></div>';
	}
	if ( ! WC()->cart || WC()->cart->is_empty() ) { return ''; }
	$items = hitscam_wallet_items(); if ( ! $items ) { return ''; }
	$best = null; foreach ( $items as $i ) { if ( $i['s'] > 0 && ( ! $best || $i['s'] > $best ) ) { $best = $i['s']; $bc = $i['code']; } }
	$applied = array_map( 'strtoupper', WC()->cart->get_applied_coupons() ); $auto = '1' === ( WC()->session ? (string) WC()->session->get( 'hc_autocoupon' ) : '' );
	$h = '<div class="wallet" id="couponWallet"><div class="small fw-semibold mb-1"><i class="bi bi-ticket-perforated text-brand"></i> আপনার কুপন</div>';
	foreach ( $items as $i ) {
		$ok = $i['s'] > 0; $on = $ok && in_array( $i['code'], $applied, true );
		$h .= '<div class="wal-item ' . ( $on ? 'on' : '' ) . ' ' . ( $ok ? '' : 'dis' ) . '"><div><b>' . esc_html( $i['code'] ) . '</b> <small>' . esc_html( $i['label'] ) . '</small>' . ( isset( $bc ) && $bc === $i['code'] ? ' <span class="badge bg-success">সেরা</span>' : '' )
			. '<div class="small text-muted">' . ( $ok ? 'সাশ্রয় ' . wp_kses_post( wc_price( $i['s'] ) ) : 'আরও ' . wp_kses_post( wc_price( $i['need'] ) ) . ' এর পণ্য লাগবে' ) . '</div></div>'
			. '<button type="button" class="btn btn-sm ' . ( $on ? 'btn-soft' : 'btn-brand' ) . ' wal-use" data-code="' . esc_attr( $i['code'] ) . '" data-on="' . ( $on ? 1 : 0 ) . '"' . ( $ok ? '' : ' disabled' ) . '>' . ( $on ? 'বাদ দিন' : 'ব্যবহার' ) . '</button></div>';
	}
	return $h . '<label class="form-check small mt-1"><input type="checkbox" class="form-check-input" id="walAuto"' . checked( $auto, true, false ) . '> <span class="form-check-label">সেরা কুপন নিজে বসান</span></label></div>';
}
add_action( 'woocommerce_before_cart_collaterals', function () { echo hitscam_wallet_html(); }, 8 ); // phpcs:ignore
add_action( 'woocommerce_checkout_before_customer_details', function () { echo hitscam_wallet_html(); } ); // phpcs:ignore

function hitscam_wallet_ajax() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! WC()->cart ) { wp_send_json_error(); }
	if ( WC()->session && ! WC()->session->has_session() ) { WC()->session->set_customer_session_cookie( true ); }
	$code = wc_format_coupon_code( wp_unslash( $_POST['code'] ?? '' ) ); $do = sanitize_key( $_POST['do'] ?? '' );
	if ( 'auto' === $do ) { WC()->session->set( 'hc_autocoupon', '1' === ( $_POST['v'] ?? '' ) ? '1' : '0' ); hitscam_wallet_autoapply(); wp_send_json_success(); }
	foreach ( hitscam_wallet_items() as $i ) { if ( $i['code'] === strtoupper( $code ) ) { $found = $i; } }
	if ( empty( $found ) ) { wp_send_json_error( array( 'msg' => 'কুপনটি পাওয়া যায়নি' ) ); }
	if ( 'remove' === $do ) { WC()->cart->remove_coupon( $code ); WC()->session->set( 'hc_autocoupon', '0' ); wp_send_json_success(); }
	if ( $found['need'] > 0 ) { wp_send_json_error( array( 'msg' => 'আরও ' . wp_strip_all_tags( wc_price( $found['need'] ) ) . ' এর পণ্য লাগবে' ) ); }
	foreach ( WC()->cart->get_applied_coupons() as $a ) { WC()->cart->remove_coupon( $a ); }
	WC()->cart->add_discount( $code ); wc_clear_notices();
	wp_send_json_success( array( 'msg' => 'কুপন প্রয়োগ হয়েছে: ' . $found['label'] ) );
}
add_action( 'wp_ajax_hc_wallet', 'hitscam_wallet_ajax' );
add_action( 'wp_ajax_nopriv_hc_wallet', 'hitscam_wallet_ajax' );

function hitscam_wallet_autoapply() {
	if ( ! WC()->cart || ! WC()->session || '1' !== (string) WC()->session->get( 'hc_autocoupon' ) || WC()->cart->get_applied_coupons() ) { return; }
	$best = null;
	foreach ( hitscam_wallet_items() as $i ) { if ( $i['s'] > 0 && ( ! $best || $i['s'] > $best['s'] ) ) { $best = $i; } }
	if ( $best ) { WC()->cart->add_discount( $best['code'] ); wc_clear_notices(); }
}
add_action( 'woocommerce_cart_loaded_from_session', function () { static $busy = false; if ( $busy ) { return; } $busy = true; hitscam_wallet_autoapply(); $busy = false; }, 99 );

/* ---------- কর্মঘণ্টা ---------- */
add_action( 'wp_footer', function () {
	echo '<script>window.HCB_HOURS=' . wp_json_encode( array( 'open' => (int) get_option( 'hitscam_hours_open', 10 ), 'close' => (int) get_option( 'hitscam_hours_close', 22 ), 'tz' => wp_timezone_string() && false === strpos( wp_timezone_string(), ':' ) ? wp_timezone_string() : 'Asia/Dhaka' ) ) . ';</script>';
}, 5 );
add_action( 'customize_register', function ( WP_Customize_Manager $c ) {
	foreach ( array( 'hours_open' => array( 'কর্মঘণ্টা শুরু (ঘণ্টা, ২৪-ঘণ্টা)', 10 ), 'hours_close' => array( 'কর্মঘণ্টা শেষ (ঘণ্টা)', 22 ) ) as $k => $x ) {
		$c->add_setting( 'hitscam_' . $k, array( 'type' => 'option', 'default' => $x[1], 'sanitize_callback' => 'absint' ) );
		$c->add_control( 'hitscam_' . $k, array( 'label' => $x[0], 'section' => 'hitscam', 'type' => 'number' ) );
	}
} );

/* ---------- অ্যালার্ট পাঠানো: দাম কমলে / স্টকে এলে (লিড: hc_lead) ---------- */
function hitscam_b2_notify( $pid, $kind, $price = 0 ) {
	$leads = get_posts( array( 'post_type' => 'hc_lead', 'numberposts' => 50, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_product', 'value' => $pid ), array( 'key' => '_notified', 'compare' => 'NOT EXISTS' ) ) ) );
	foreach ( $leads as $lid ) {
		$t = get_the_title( $lid ); $is_price = false !== mb_strpos( $t, 'দাম কমলে' );
		if ( ( 'price' === $kind ) !== $is_price ) { continue; }
		if ( $is_price && preg_match( '/৳\s*([\d.]+)/u', (string) get_post_field( 'post_content', $lid ), $m ) && $price > (float) $m[1] ) { continue; } // কাঙ্ক্ষিত দামে নামেনি
		$ph  = get_post_meta( $lid, '_phone', true );
		$msg = ( $is_price ? 'দাম কমেছে! ' : 'স্টকে এসেছে! ' ) . get_the_title( $pid ) . ( $is_price ? ' এখন ' . wp_strip_all_tags( html_entity_decode( wc_price( $price ) ) ) : '' ) . ' — ' . get_permalink( $pid );
		$sent = apply_filters( 'hitscam_send_sms', false, $ph, $msg );
		update_post_meta( $lid, '_notified', $sent ? 'sms' : 'queued' );
		wp_mail( get_option( 'admin_email' ), '[HitsCam] অ্যালার্ট ' . ( $sent ? 'পাঠানো হয়েছে' : 'পাঠানোর অপেক্ষায় (SMS গেটওয়ে নেই)' ), "$ph\n$msg" );
	}
}
add_action( 'woocommerce_product_object_updated_props', function ( $p, $props ) {
	if ( in_array( 'price', $props, true ) || in_array( 'regular_price', $props, true ) || in_array( 'sale_price', $props, true ) ) {
		$old = (float) $p->get_meta( '_hc_last_price' ); $now = (float) $p->get_price();
		if ( $now > 0 ) { if ( $old && $now < $old ) { hitscam_b2_notify( $p->get_id(), 'price', $now ); } $p->update_meta_data( '_hc_last_price', $now ); $p->save_meta_data(); }
	}
	if ( in_array( 'stock_status', $props, true ) && 'instock' === $p->get_stock_status() ) { hitscam_b2_notify( $p->get_id(), 'stock' ); }
}, 10, 2 );
