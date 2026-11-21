<?php
/**
 * e4 — অর্ডার-রিসিভড পেজের সাপোর্ট: অ্যাড-অন (সময়ের মধ্যে অর্ডারে যোগ), রেফারেল/পয়েন্ট ব্লক, কুরিয়ার ট্র্যাকার প্যানেল।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

/** অর্ডারে যোগ করার মতো একটি অ্যাড-অন পণ্য (আপসেল → একই ক্যাটাগরি) */
function hitscam_e4_addon_product( WC_Order $o ) {
	$in = array();
	foreach ( $o->get_items() as $it ) { $in[] = $it->get_product_id(); }
	$cands = array();
	foreach ( $in as $pid ) {
		$p = wc_get_product( $pid );
		if ( $p ) { $cands = array_merge( $cands, $p->get_upsell_ids() ); }
	}
	$cands = array_merge( $cands, wc_get_products( array( 'limit' => 12, 'status' => 'publish', 'return' => 'ids', 'orderby' => 'popularity' ) ) );
	foreach ( array_unique( $cands ) as $id ) {
		$p = wc_get_product( $id );
		if ( $p && $p->is_visible() && ! in_array( $id, $in, true ) && $p->is_type( 'simple' ) && $p->is_in_stock() && $p->is_purchasable() && (float) $p->get_price() > 0 ) { return $p; }
	}
	return null;
}

/** রেফারেল কোড (লগইন থাকলে ইউজারের; নইলে অর্ডার-ভিত্তিক) */
function hitscam_e4_ref_for( WC_Order $o ) {
	$uid = $o->get_customer_id();
	if ( $uid && function_exists( 'hitscam_ref_code' ) ) { return hitscam_ref_code( $uid ); }
	return 'HC-' . strtoupper( substr( md5( $o->get_order_key() ), 0, 6 ) );
}

/** অর্ডারে আইটেম যোগ (শুধু সেলফ-সার্ভিস উইন্ডোর মধ্যে, pending/processing/on-hold) */
add_action( 'wp_ajax_hc_addon', 'hitscam_e4_ajax_addon' );
add_action( 'wp_ajax_nopriv_hc_addon', 'hitscam_e4_ajax_addon' );
function hitscam_e4_ajax_addon() {
	$o = hitscam_order_from_post();
	$p = wc_get_product( absint( $_POST['pid'] ?? 0 ) );
	$left = ( $o->get_date_created()->getTimestamp() + hitscam_a2_window() * 60 ) - time();
	if ( ! $p || ! $p->is_in_stock() || ! $p->is_type( 'simple' ) || $left <= 0 || ! $o->has_status( array( 'pending', 'processing', 'on-hold' ) ) ) {
		wp_send_json_error( array( 'msg' => 'এখন অর্ডারে যোগ করা যাচ্ছে না।' ) );
	}
	$o->add_product( $p, 1 );
	$o->calculate_totals();
	$o->add_order_note( 'গ্রাহক অর্ডারের সাথে যোগ করেছেন: ' . $p->get_name() );
	$o->save();
	wp_send_json_success( array( 'msg' => 'অর্ডারে যোগ হয়েছে ✓' ) );
}

/** কুরিয়ার ট্র্যাকার প্যানেল (কনসাইনমেন্ট থাকলে) */
function hitscam_e4_courier_panel( WC_Order $o ) {
	if ( ! function_exists( 'hitscam_courier_info' ) ) { return ''; }
	$c = hitscam_courier_info( $o );
	if ( ! $c ) { return ''; }
	$h = '<div class="courier-box mt-3 p-3 rounded-4 border"><div class="fw-semibold"><i class="bi bi-truck text-brand"></i> কুরিয়ার: ' . esc_html( $c['name'] ) . ' · ট্র্যাকিং নং <b>' . esc_html( $c['cn'] ) . '</b></div>';
	if ( $c['events'] ) {
		$h .= '<ul class="small mt-2 mb-2 ps-3">';
		foreach ( $c['events'] as $e ) { $h .= '<li>' . esc_html( ( $e['time'] ?? '' ) . ' — ' . ( $e['text'] ?? '' ) ) . '</li>'; }
		$h .= '</ul>';
	}
	if ( $c['url'] ) { $h .= '<a class="btn btn-brand btn-sm rounded-pill mt-2" target="_blank" rel="noopener" href="' . esc_url( $c['url'] ) . '"><i class="bi bi-geo-alt"></i> কুরিয়ারের ট্র্যাকারে দেখুন</a>'; }
	return $h . '</div>';
}

/* Instant order মডালের iframe (?hc_modal=1): body ক্লাস */
add_filter( 'body_class', function ( $c ) {
	if ( isset( $_GET['hc_modal'] ) ) { $c[] = 'hc-in-modal'; } // phpcs:ignore WordPress.Security.NonceVerification
	return $c;
} );
add_filter( 'show_admin_bar', function ( $s ) { return isset( $_GET['hc_modal'] ) ? false : $s; } ); // phpcs:ignore WordPress.Security.NonceVerification
add_filter( 'woocommerce_get_checkout_order_received_url', function ( $url ) {
	$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore
	return false !== strpos( $ref, 'hc_modal=1' ) ? add_query_arg( 'hc_modal', '1', $url ) : $url;
} );

/* ফোন বাধ্যতামূলক (টেমপ্লেটের মতো) */
add_filter( 'woocommerce_billing_fields', function ( $f ) {
	if ( isset( $f['billing_phone'] ) ) { $f['billing_phone']['required'] = true; $f['billing_phone']['label'] = 'মোবাইল নাম্বার'; $f['billing_phone']['priority'] = 25; }
	return $f;
}, 99 );
add_action( 'wp_head', function () { echo '<script>window.HC_VERIFY=' . wp_json_encode( get_option( 'hitscam_verify', 'required' ) ) . ';</script>'; }, 1 );
