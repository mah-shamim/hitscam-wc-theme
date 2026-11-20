<?php
/**
 * d2 — Banglish/বানান-সহনশীল সার্চ, প্রোডাক্টে ভিডিও ও ৩৬০° ভিউ, সোশ্যাল-প্রুফ পালস, "অন্য ক্রেতারা এগুলোও দেখেছেন" (সার্ভার-ভিত্তিক)।
 * টেমপ্লেট: support.js (সার্চ/coview), product.html (ভিডিও/স্পিন/পালস)।
 */
defined( 'ABSPATH' ) || exit;

/* ---------- Banglish সার্চ ---------- */
function hitscam_d2_dict() {
	return apply_filters( 'hitscam_search_dict', array( 'kamera' => 'ক্যামেরা', 'camera' => 'ক্যামেরা', 'camra' => 'ক্যামেরা', 'cam' => 'ক্যামেরা', 'cctv' => 'সিসি', 'sisi' => 'সিসি', 'cc' => 'সিসি', 'ip' => 'আইপি', 'aipi' => 'আইপি',
		'memory' => 'মেমোরি', 'memori' => 'মেমোরি', 'memry' => 'মেমোরি', 'card' => 'কার্ড', 'kard' => 'কার্ড', 'ups' => 'ইউপিএস', 'mini' => 'মিনি', 'solar' => 'সোলার', 'sola' => 'সোলার', 'phone' => 'ফোন', 'fon' => 'ফোন',
		'smartphone' => 'স্মার্টফোন', 'laptop' => 'ল্যাপটপ', 'mount' => 'মাউন্ট', 'security' => 'সিকিউরিটি', 'sikiuriti' => 'সিকিউরিটি', 'gimbal' => 'গিম্বল', 'accessories' => 'অ্যাক্সেসরিজ' ) );
}
/** টোকেন → বাংলা (সরাসরি বা ১-অক্ষর ভুলসহ); মিললে নতুন স্ট্রিং, নইলে মূল */
function hitscam_d2_expand_query( $q ) {
	$d = hitscam_d2_dict(); $out = array(); $hit = false;
	foreach ( preg_split( '/\s+/u', trim( $q ) ) as $t ) {
		$l = strtolower( $t );
		if ( isset( $d[ $l ] ) ) { $out[] = $d[ $l ]; $hit = true; continue; }
		$m = null;
		if ( strlen( $l ) >= 4 && preg_match( '/^[a-z]+$/', $l ) ) { foreach ( $d as $k => $bn ) { if ( strlen( $k ) >= 4 && levenshtein( $l, $k ) <= 1 ) { $m = $bn; break; } } }
		$out[] = $m ?: $t; $hit = $hit || (bool) $m;
	}
	return $hit ? implode( ' ', $out ) : $q;
}
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_search() || 'product' !== $q->get( 'post_type' ) ) { return; }
	$s = (string) $q->get( 's' ); if ( '' === $s ) { return; }
	$e = hitscam_d2_expand_query( $s );
	if ( $e !== $s ) { $q->set( 's', $e ); $q->set( 'hc_search_orig', $s ); }
} );
add_action( 'woocommerce_before_shop_loop', function () {
	global $wp_query; $o = $wp_query->get( 'hc_search_orig' );
	if ( $o ) { echo '<div class="small text-muted mb-2">দেখানো হচ্ছে: <b>' . esc_html( $wp_query->get( 's' ) ) . '</b> (আপনি লিখেছেন: ' . esc_html( $o ) . ')</div>'; }
}, 5 );

/* ---------- ভিডিও / ৩৬০° / পালস: মেটাবক্স ---------- */
add_action( 'woocommerce_product_options_general_product_data', function () {
	echo '<div class="options_group">';
	woocommerce_wp_text_input( array( 'id' => '_hc_video', 'label' => 'YouTube ভিডিও', 'description' => 'ID বা পুরো লিংক — গ্যালারিতে ভিডিও থাম্ব হিসেবে দেখাবে', 'desc_tip' => true ) );
	woocommerce_wp_checkbox( array( 'id' => '_hc_spin', 'label' => '৩৬০° ভিউ', 'description' => 'গ্যালারির ছবিগুলোকে ক্রমানুসারে ৩৬০° ফ্রেম হিসেবে ঘোরানো যাবে (কমপক্ষে ৬টি গ্যালারি-ছবি)' ) );
	echo '</div>';
} );
add_action( 'woocommerce_process_product_meta', function ( $id ) {
	$v = sanitize_text_field( wp_unslash( $_POST['_hc_video'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( preg_match( '~(?:v=|youtu\.be/|embed/)([A-Za-z0-9_-]{11})~', $v, $m ) ) { $v = $m[1]; }
	update_post_meta( $id, '_hc_video', preg_match( '/^[A-Za-z0-9_-]{11}$/', $v ) ? $v : '' );
	update_post_meta( $id, '_hc_spin', isset( $_POST['_hc_spin'] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification
} );

/* ---------- পালস: আজকের ভিউ + সর্বশেষ অর্ডার ---------- */
add_action( 'woocommerce_checkout_order_processed', function ( $oid ) {
	$o = wc_get_order( $oid ); if ( ! $o ) { return; }
	foreach ( $o->get_items() as $it ) { update_post_meta( $it->get_product_id(), '_hc_last_sold', time() ); }
} );
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	$id = get_the_ID(); $p = wc_get_product( $id ); if ( ! $p ) { return; }
	$key = '_hc_views_' . gmdate( 'Ymd' ); $n = (int) get_post_meta( $id, $key, true );
	if ( ! isset( $_COOKIE[ 'hcv_' . $id ] ) && ! is_user_logged_in() && ! wp_is_json_request() ) { update_post_meta( $id, $key, ++$n ); }
	$ls = (int) get_post_meta( $id, '_hc_last_sold', true );
	$d  = array( 'id' => $id, 'views' => $n, 'last' => $ls ? human_time_diff( $ls ) . ' আগে' : '', 'video' => (string) get_post_meta( $id, '_hc_video', true ), 'spin' => array() );
	if ( 'yes' === get_post_meta( $id, '_hc_spin', true ) ) {
		foreach ( $p->get_gallery_image_ids() as $g ) { $d['spin'][] = wp_get_attachment_image_url( $g, 'woocommerce_single' ); }
		if ( count( $d['spin'] ) < 6 ) { $d['spin'] = array(); }
	}
	echo '<script>window.HCB_PDP=' . wp_json_encode( $d ) . ';document.cookie="hcv_' . (int) $id . '=1;path=/;max-age=3600";</script>';
}, 6 );

/* ---------- "অন্য ক্রেতারা এগুলোও দেখেছেন": সহ-ভিউ (localStorage থেকে আসা আইডি) → কার্ড ---------- */
function hitscam_d2_coview() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$cur = absint( $_POST['id'] ?? 0 ); $near = array_filter( array_map( 'absint', (array) ( $_POST['near'] ?? array() ) ) );
	$p = wc_get_product( $cur ); if ( ! $p ) { wp_send_json_success( '' ); }
	$price = (float) $p->get_price() ?: 1; $score = array();
	$cands = wc_get_products( array( 'limit' => 40, 'status' => 'publish', 'exclude' => array( $cur ), 'return' => 'objects' ) );
	foreach ( $cands as $c ) {
		$s = ( in_array( $c->get_id(), $near, true ) ? 10 : 0 ) + ( array_intersect( $c->get_category_ids(), $p->get_category_ids() ) ? 3 : 0 ) + max( 0, 2 - abs( log( max( 1, (float) $c->get_price() ) / $price ) ) ) + (float) $c->get_average_rating() / 10;
		$score[ $c->get_id() ] = $s;
	}
	arsort( $score ); $ids = array_slice( array_keys( $score ), 0, 4 );
	global $product; $keep = $product; ob_start();
	echo '<div class="row g-3 mb-4" id="alsoViewed">';
	foreach ( $ids as $id ) { $product = wc_get_product( $id ); echo '<div class="col-6 col-lg-3">'; wc_get_template_part( 'content', 'product' ); echo '</div>'; }
	echo '</div>'; $product = $keep;
	wp_send_json_success( ob_get_clean() );
}
add_action( 'wp_ajax_hc_coview', 'hitscam_d2_coview' );
add_action( 'wp_ajax_nopriv_hc_coview', 'hitscam_d2_coview' );
