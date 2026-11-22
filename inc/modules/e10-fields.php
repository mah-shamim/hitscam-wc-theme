<?php
/**
 * e10 — চেকআউট / ইন্সট্যান্ট অর্ডারের "Your details" ফিল্ড সেটিং: কোনটা থাকবে/লুকাবে, বাধ্যতামূলক, পাশাপাশি (অর্ধেক) না পুরো, ক্রম, লেবেল।
 * প্রয়োগ: শুধু ইন্সট্যান্ট অর্ডার মডাল, অথবা সব চেকআউট (অ্যাডমিন বাছবে)।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

function hitscam_cof() {
	$c = get_option( 'hitscam_cof', null );
	return is_array( $c ) && ! empty( $c['f'] ) ? $c : null;
}
function hitscam_cof_in_modal() {
	if ( isset( $_GET['hc_modal'] ) ) { return true; } // phpcs:ignore WordPress.Security.NonceVerification
	$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore
	return false !== strpos( $ref, 'hc_modal=1' );
}
function hitscam_cof_active() {
	$c = hitscam_cof();
	if ( ! $c || ( is_admin() && ! wp_doing_ajax() ) ) { return null; }
	if ( 'modal' === ( $c['scope'] ?? 'modal' ) && ! hitscam_cof_in_modal() ) { return null; }
	return $c;
}

add_filter( 'woocommerce_checkout_fields', function ( $fs ) {
	$c = hitscam_cof_active();
	if ( ! $c ) { return $fs; }
	foreach ( array( 'billing', 'order' ) as $grp ) {
		if ( empty( $fs[ $grp ] ) ) { continue; }
		foreach ( $fs[ $grp ] as $k => $f ) {
			$s = $c['f'][ $k ] ?? null; if ( ! $s ) { continue; }
			if ( empty( $s['show'] ) && 'billing_phone' !== $k ) {
				if ( 'billing_country' === $k ) { $fs[ $grp ][ $k ]['type'] = 'hidden'; $fs[ $grp ][ $k ]['default'] = 'BD'; $fs[ $grp ][ $k ]['label'] = ''; $fs[ $grp ][ $k ]['required'] = false; }
				else { unset( $fs[ $grp ][ $k ] ); }
				continue;
			}
			$fs[ $grp ][ $k ]['required'] = ! empty( $s['req'] ) || 'billing_phone' === $k;
			if ( '' !== ( $s['label'] ?? '' ) ) { $fs[ $grp ][ $k ]['label'] = $s['label']; }
			$fs[ $grp ][ $k ]['priority'] = (int) ( $s['pr'] ?? $f['priority'] ?? 50 );
		}
		/* প্রস্থ: ক্রম অনুযায়ী পাশাপাশি জোড়া (অর্ধেক+অর্ধেক); পুরো ফিল্ড জোড়া ভাঙে */
		uasort( $fs[ $grp ], fn( $a, $b ) => ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 ) );
		$left = true;
		foreach ( $fs[ $grp ] as $k => $f ) {
			$s = $c['f'][ $k ] ?? null; if ( ! $s || 'hidden' === ( $f['type'] ?? '' ) ) { continue; }
			if ( 'half' === ( $s['w'] ?? 'full' ) ) { $fs[ $grp ][ $k ]['class'] = array( $left ? 'form-row-first' : 'form-row-last' ); $left = ! $left; }
			else { $fs[ $grp ][ $k ]['class'] = array( 'form-row-wide' ); $left = true; }
		}
	}
	return $fs;
}, 1000 );

/* WooCommerce চেকআউট JS লোকেল দিয়ে ক্রম/বাধ্যতামূলক আবার বসায় — সেটিংই যেন জেতে */
/** ক্রম ও প্রস্থ থেকে বিলিং ফিল্ডের ক্লাস (পরপর দুটো অর্ধেক = পাশাপাশি) */
function hitscam_cof_classes( array $c ) {
	$rows = array();
	foreach ( $c['f'] as $k => $s ) { if ( 0 === strpos( $k, 'billing_' ) && ! empty( $s['show'] ) ) { $rows[ $k ] = (int) ( $s['pr'] ?? 50 ); } }
	asort( $rows ); $out = array(); $left = true;
	foreach ( array_keys( $rows ) as $k ) {
		if ( 'half' === ( $c['f'][ $k ]['w'] ?? 'full' ) ) { $out[ $k ] = $left ? 'form-row-first' : 'form-row-last'; $left = ! $left; }
		else { $out[ $k ] = 'form-row-wide'; $left = true; }
	}
	return $out;
}

add_filter( 'woocommerce_get_country_locale', function ( $l ) {
	$c = hitscam_cof_active(); if ( ! $c ) { return $l; }
	$map = array( 'billing_first_name' => 'first_name', 'billing_last_name' => 'last_name', 'billing_phone' => 'phone', 'billing_email' => 'email', 'billing_address_1' => 'address_1', 'billing_address_2' => 'address_2', 'billing_city' => 'city', 'billing_state' => 'state', 'billing_postcode' => 'postcode', 'billing_country' => 'country', 'billing_company' => 'company' );
	$o = array(); $cl = hitscam_cof_classes( $c );
	foreach ( $map as $k => $loc ) {
		$s = $c['f'][ $k ] ?? null; if ( ! $s ) { continue; }
		$o[ $loc ] = array( 'priority' => (int) ( $s['pr'] ?? 50 ), 'required' => ! empty( $s['req'] ) || 'billing_phone' === $k );
		if ( isset( $cl[ $k ] ) ) { $o[ $loc ]['class'] = array( $cl[ $k ] ); }
		if ( '' !== ( $s['label'] ?? '' ) ) { $o[ $loc ]['label'] = $s['label']; }
	}
	foreach ( array( 'BD', 'default' ) as $cc ) { $l[ $cc ] = array_replace_recursive( $l[ $cc ] ?? array(), $o ); }
	return $l;
}, 100 );

/* ===== অ্যাডমিন ===== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'চেকআউট ফিল্ড', 'চেকআউট ফিল্ড', 'manage_woocommerce', 'hitscam-cof', 'hitscam_cof_admin', 28 );
}, 20 );

function hitscam_cof_admin() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	if ( isset( $_POST['hc_cof_reset'] ) && check_admin_referer( 'hc_cof' ) ) { delete_option( 'hitscam_cof' ); echo '<div class="notice notice-success"><p>ডিফল্টে ফেরানো হয়েছে।</p></div>'; }
	if ( isset( $_POST['hc_cof'] ) && check_admin_referer( 'hc_cof' ) ) {
		$f = array();
		foreach ( (array) ( $_POST['f'] ?? array() ) as $k => $r ) {
			$f[ sanitize_key( $k ) ] = array(
				'show' => ! empty( $r['show'] ), 'req' => ! empty( $r['req'] ), 'w' => 'half' === ( $r['w'] ?? '' ) ? 'half' : 'full',
				'pr' => (int) ( $r['pr'] ?? 50 ), 'label' => sanitize_text_field( wp_unslash( $r['label'] ?? '' ) ),
			);
		}
		update_option( 'hitscam_cof', array( 'scope' => 'all' === ( $_POST['scope'] ?? '' ) ? 'all' : 'modal', 'f' => $f ) );
		echo '<div class="notice notice-success"><p>সংরক্ষিত।</p></div>';
	}
	$cfg = get_option( 'hitscam_cof', array() ); $cfg = is_array( $cfg ) ? $cfg : array(); $saved = $cfg['f'] ?? array();
	$all = array();
	if ( function_exists( 'WC' ) && WC()->checkout() ) { $fs = WC()->checkout()->get_checkout_fields(); foreach ( array( 'billing', 'order' ) as $g ) { foreach ( (array) ( $fs[ $g ] ?? array() ) as $k => $f ) { $all[ $k ] = $f; } } }
	if ( isset( $saved['billing_country'] ) && empty( $all['billing_country'] ) ) { $all['billing_country'] = array( 'label' => 'Country' ); }
	uasort( $all, fn( $a, $b ) => ( $a['priority'] ?? 50 ) <=> ( $b['priority'] ?? 50 ) );
	$scope = $cfg['scope'] ?? 'modal';
	echo '<div class="wrap"><h1>চেকআউট ফিল্ড ("Your details")</h1><p>কোন ফিল্ড থাকবে/লুকাবে, বাধ্যতামূলক কি না, পাশাপাশি (অর্ধেক) না পুরো প্রস্থ, ক্রম ও লেবেল ঠিক করুন। পাশাপাশি বসবে: পরপর দুটো "অর্ধেক" ফিল্ড (ক্রম অনুযায়ী)। মোবাইল নাম্বার সবসময় থাকবে ও বাধ্যতামূলক (OTP ভেরিফিকেশনের জন্য)।</p>';
	echo '<form method="post">'; wp_nonce_field( 'hc_cof' );
	echo '<p><b>কোথায় প্রযোজ্য:</b> <label><input type="radio" name="scope" value="modal"' . checked( $scope, 'modal', false ) . '> শুধু ইন্সট্যান্ট অর্ডার পপআপ</label> &nbsp; <label><input type="radio" name="scope" value="all"' . checked( $scope, 'all', false ) . '> সব চেকআউট (পপআপ + সাধারণ পেজ)</label></p>';
	echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>ফিল্ড</th><th>দেখাবে</th><th>বাধ্যতামূলক</th><th>প্রস্থ</th><th>ক্রম</th><th>লেবেল (ফাঁকা = ডিফল্ট)</th></tr></thead><tbody>';
	foreach ( $all as $k => $f ) {
		$s = $saved[ $k ] ?? array( 'show' => 1, 'req' => ! empty( $f['required'] ), 'w' => ( in_array( 'form-row-first', (array) ( $f['class'] ?? array() ), true ) || in_array( 'form-row-last', (array) ( $f['class'] ?? array() ), true ) ) ? 'half' : 'full', 'pr' => (int) ( $f['priority'] ?? 50 ), 'label' => '' );
		$n = 'f[' . esc_attr( $k ) . ']'; $ph = 'billing_phone' === $k;
		echo '<tr><td><b>' . esc_html( wp_strip_all_tags( $f['label'] ?? $k ) ) . '</b><br><code>' . esc_html( $k ) . '</code></td>';
		echo '<td><input type="checkbox" name="' . $n . '[show]" value="1"' . checked( ! empty( $s['show'] ) || $ph, true, false ) . ( $ph ? ' disabled' : '' ) . '>' . ( $ph ? '<input type="hidden" name="' . $n . '[show]" value="1">' : '' ) . '</td>';
		echo '<td><input type="checkbox" name="' . $n . '[req]" value="1"' . checked( ! empty( $s['req'] ) || $ph, true, false ) . ( $ph ? ' disabled' : '' ) . '>' . ( $ph ? '<input type="hidden" name="' . $n . '[req]" value="1">' : '' ) . '</td>';
		echo '<td><select name="' . $n . '[w]"><option value="full"' . selected( $s['w'], 'full', false ) . '>পুরো</option><option value="half"' . selected( $s['w'], 'half', false ) . '>অর্ধেক (পাশাপাশি)</option></select></td>';
		echo '<td><input type="number" name="' . $n . '[pr]" value="' . (int) $s['pr'] . '" step="1" style="width:70px"></td>';
		echo '<td><input type="text" name="' . $n . '[label]" value="' . esc_attr( $s['label'] ) . '" class="regular-text"></td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary" name="hc_cof" value="1">সংরক্ষণ</button> <button class="button" name="hc_cof_reset" value="1" onclick="return confirm(\'ডিফল্টে ফিরবেন?\')">ডিফল্টে ফেরান</button></p></form></div>';
}
