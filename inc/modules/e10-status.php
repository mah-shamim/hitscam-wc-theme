<?php
/**
 * e10 — কাস্টম অর্ডার স্ট্যাটাস (একাধিক, অ্যাডমিন থেকে যোগ/বদল), রিকনফার্মে কোন স্ট্যাটাস বসবে, কোন কোন স্ট্যাটাসে নোটিফিকেশন যাবে।
 * কোড থেকে: add_filter('hitscam_custom_statuses', fn($a)=>$a+['slug'=>['label'=>'…','color'=>'#000']]);
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

const HITSCAM_ST_DEFAULT = "# স্লাগ|লেবেল|রং (প্রতি লাইনে একটি; স্লাগ ছোট হাতের ইংরেজি, - সহ)\nready-to-ship|Ready to ship|#0d9488";

/** কাস্টম স্ট্যাটাস: slug => [label,color] */
function hitscam_custom_statuses() {
	$out = array();
	foreach ( explode( "\n", (string) get_option( 'hitscam_statuses', HITSCAM_ST_DEFAULT ) ) as $l ) {
		$l = trim( $l );
		if ( '' === $l || '#' === $l[0] ) { continue; }
		$r = array_map( 'trim', explode( '|', $l ) );
		$k = sanitize_key( $r[0] );
		if ( ! $k || strlen( 'wc-' . $k ) > 20 ) { continue; }
		$out[ $k ] = array( 'label' => $r[1] ?? $k, 'color' => preg_match( '/^#[0-9a-f]{3,6}$/i', $r[2] ?? '' ) ? $r[2] : '#64748b' );
	}
	if ( ! isset( $out['ready-to-ship'] ) && false === strpos( (string) get_option( 'hitscam_statuses', '' ), 'ready-to-ship' ) && ! get_option( 'hitscam_statuses' ) ) {
		$out['ready-to-ship'] = array( 'label' => 'Ready to ship', 'color' => '#0d9488' );
	}
	return apply_filters( 'hitscam_custom_statuses', $out );
}
/** রিকনফার্মে যে স্ট্যাটাস বসবে (ফাঁকা = কিছু বদলাবে না) */
function hitscam_reconfirm_status() { return sanitize_key( get_option( 'hitscam_reconfirm_status', 'ready-to-ship' ) ); }
/** যেসব স্ট্যাটাসে গ্রাহককে নোটিফিকেশন যাবে */
function hitscam_notify_statuses() {
	$s = get_option( 'hitscam_notify_statuses', null );
	return is_array( $s ) ? $s : array( 'processing', 'ready-to-ship', 'shipped', 'out-for-delivery', 'completed' );
}

add_action( 'init', function () {
	foreach ( hitscam_custom_statuses() as $k => $s ) {
		register_post_status( 'wc-' . $k, array(
			'label'                     => $s['label'],
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( $s['label'] . ' <span class="count">(%s)</span>', $s['label'] . ' <span class="count">(%s)</span>' ),
		) );
	}
} );
add_filter( 'wc_order_statuses', function ( $st ) {
	$n = array();
	foreach ( $st as $k => $v ) {
		$n[ $k ] = $v;
		if ( 'wc-processing' === $k ) { foreach ( hitscam_custom_statuses() as $s => $d ) { $n[ 'wc-' . $s ] = $d['label']; } }
	}
	foreach ( hitscam_custom_statuses() as $s => $d ) { if ( ! isset( $n[ 'wc-' . $s ] ) ) { $n[ 'wc-' . $s ] = $d['label']; } }
	return $n;
} );
/* কাস্টম স্ট্যাটাস = পেইড/বিক্রি হিসেবে গণ্য (রিপোর্ট ও অ্যানালিটিক্স) */
add_filter( 'woocommerce_order_is_paid_statuses', function ( $s ) { return array_merge( $s, array_keys( hitscam_custom_statuses() ) ); } );
add_filter( 'woocommerce_reports_order_statuses', function ( $s ) { return is_array( $s ) ? array_merge( $s, array_keys( hitscam_custom_statuses() ) ) : $s; } );
add_filter( 'woocommerce_analytics_excluded_order_statuses', function ( $s ) { return array_diff( (array) $s, array_keys( hitscam_custom_statuses() ) ); } );
/* অর্ডার লিস্টে স্ট্যাটাসের রং */
add_action( 'admin_head', function () {
	$css = '';
	foreach ( hitscam_custom_statuses() as $k => $s ) { $css .= '.order-status.status-' . esc_attr( $k ) . '{background:' . esc_attr( $s['color'] ) . ';color:#fff}'; }
	if ( $css ) { echo '<style>' . $css . '</style>'; } // phpcs:ignore
} );

add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'অর্ডার স্ট্যাটাস', 'অর্ডার স্ট্যাটাস', 'manage_woocommerce', 'hitscam-statuses', 'hitscam_e10_status_admin', 27 );
}, 20 );
function hitscam_e10_status_admin() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	if ( isset( $_POST['hc_st'] ) && check_admin_referer( 'hc_st' ) ) {
		update_option( 'hitscam_statuses', sanitize_textarea_field( wp_unslash( $_POST['statuses'] ?? '' ) ) );
		update_option( 'hitscam_reconfirm_status', sanitize_key( $_POST['reconfirm'] ?? '' ) );
		update_option( 'hitscam_notify_statuses', array_map( 'sanitize_key', (array) ( $_POST['notify'] ?? array() ) ) );
		echo '<div class="notice notice-success"><p>সংরক্ষিত।</p></div>';
	}
	$all = wc_get_order_statuses(); $rs = hitscam_reconfirm_status(); $ns = hitscam_notify_statuses();
	echo '<div class="wrap"><h1>অর্ডার স্ট্যাটাস</h1><form method="post">'; wp_nonce_field( 'hc_st' );
	echo '<table class="form-table"><tr><th>কাস্টম স্ট্যাটাস</th><td><textarea name="statuses" rows="8" class="large-text code">' . esc_textarea( (string) get_option( 'hitscam_statuses', HITSCAM_ST_DEFAULT ) ) . '</textarea><p class="description">প্রতি লাইনে একটি: <code>স্লাগ|লেবেল|#রং</code> — যেমন <code>packed|Packed|#2563eb</code>। একাধিক যোগ করা যায়। স্লাগ সর্বোচ্চ ১৭ অক্ষর। মুছলে স্ট্যাটাসটি তালিকা থেকে সরে যায় (পুরনো অর্ডারের ডেটা থাকে)।</p></td></tr>';
	echo '<tr><th>রিকনফার্ম করলে স্ট্যাটাস</th><td><select name="reconfirm"><option value="">— বদলাবে না (শুধু নোট) —</option>';
	foreach ( $all as $k => $l ) { $s = 0 === strpos( $k, 'wc-' ) ? substr( $k, 3 ) : $k; echo '<option value="' . esc_attr( $s ) . '"' . selected( $rs, $s, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select><p class="description">কাস্টমার "অর্ডার রিকনফার্ম করুন" চাপলে অর্ডার এই স্ট্যাটাসে যাবে (শুধু pending/on-hold/processing/কাস্টম থাকলে)।</p></td></tr>';
	echo '<tr><th>নোটিফিকেশন যাবে যে স্ট্যাটাসে</th><td>';
	foreach ( $all as $k => $l ) { $s = 0 === strpos( $k, 'wc-' ) ? substr( $k, 3 ) : $k; echo '<label style="display:inline-block;min-width:200px"><input type="checkbox" name="notify[]" value="' . esc_attr( $s ) . '"' . checked( in_array( $s, $ns, true ), true, false ) . '> ' . esc_html( $l ) . '</label>'; }
	echo '<p class="description">গ্রাহক আপডেট চাইলে এই স্ট্যাটাসে গেলে SMS/WhatsApp/IMO-তে জানানো হবে।</p></td></tr></table>';
	echo '<p><button class="button button-primary" name="hc_st" value="1">সংরক্ষণ</button></p></form></div>';
}
