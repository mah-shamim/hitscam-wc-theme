<?php
/**
 * e9 — অ্যাডমিন সেটিংস: অর্ডার-সম্পন্ন পেজের প্যানেল চালু/বন্ধ + মেসেজিং মোড, পেমেন্ট মেথড কাস্টমাইজ,
 * "টোটাল বিক্রি"-র স্ট্যাটাস সিলেকশন, কুপন (শুধু রেজিস্টার্ড), ফোন ভেরিফাই ডিফল্ট।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

/* ---------- অর্ডার-সম্পন্ন পেজ প্যানেল ---------- */
function hitscam_ty_panels() {
	return apply_filters( 'hitscam_ty_panels', array(
		'reconfirm' => 'অর্ডার রিকনফার্ম',
		'manage'    => 'অর্ডার ম্যানেজ (বাতিল/ঠিকানা বদল/রিটার্ন)',
		'addon'     => 'অর্ডারের সাথে যোগ করুন (অ্যাড-অন)',
		'referral'  => 'বন্ধুকে ছাড় দিন (রেফারেল)',
		'points'    => 'অর্জিত পয়েন্ট সারি',
		'notify'    => 'অর্ডারের আপডেট নোটিফিকেশনে পান',
		'cross'     => 'আপনার অর্ডারের সাথে মানানসই (পোস্ট-অর্ডার আপসেল)',
		'courier'   => 'কুরিয়ার ট্র্যাকার',
	) );
}
function hitscam_ty_on( $k ) {
	$o = (array) get_option( 'hitscam_ty_panels', array() );
	return ! isset( $o[ $k ] ) || ! empty( $o[ $k ] );
}
/** মোড: auto|sms|channel|both|off → [sms=>bool, ch=>bool] ; $which = reconfirm|notify */
function hitscam_ty_mode( $which ) {
	$m = get_option( 'hitscam_ty_' . $which . '_mode', 'auto' );
	$sms_ready = function_exists( 'hitscam_sms_ready' ) && hitscam_sms_ready();
	switch ( $m ) {
		case 'sms': return array( 'sms' => true, 'ch' => false, 'off' => false );
		case 'channel': return array( 'sms' => false, 'ch' => true, 'off' => false );
		case 'both': return array( 'sms' => true, 'ch' => true, 'off' => false );
		case 'off': return array( 'sms' => false, 'ch' => false, 'off' => true );
	}
	return array( 'sms' => $sms_ready, 'ch' => ! $sms_ready, 'off' => false );
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'অর্ডার-সম্পন্ন পেজ', 'অর্ডার-সম্পন্ন পেজ', 'manage_woocommerce', 'hitscam-ty', 'hitscam_e9_ty_admin' );
	add_submenu_page( 'hitscam-home', 'পেমেন্ট মেথড', 'পেমেন্ট মেথড', 'manage_woocommerce', 'hitscam-paym', 'hitscam_e9_pay_admin' );
	add_submenu_page( 'hitscam-home', 'বিক্রি ও কুপন', 'বিক্রি ও কুপন', 'manage_woocommerce', 'hitscam-sales', 'hitscam_e9_sales_admin' );
}, 25 );

function hitscam_e9_ty_admin() {
	if ( isset( $_POST['hc_ty'] ) && check_admin_referer( 'hc_ty' ) ) {
		$on = array(); foreach ( array_keys( hitscam_ty_panels() ) as $k ) { $on[ $k ] = empty( $_POST['p'][ $k ] ) ? 0 : 1; }
		update_option( 'hitscam_ty_panels', $on );
		foreach ( array( 'reconfirm', 'notify' ) as $w ) {
			$v = sanitize_key( $_POST[ $w . '_mode' ] ?? 'auto' );
			update_option( 'hitscam_ty_' . $w . '_mode', in_array( $v, array( 'auto', 'sms', 'channel', 'both', 'off' ), true ) ? $v : 'auto' );
		}
		update_option( 'hitscam_cancel_min', max( 0, (int) ( $_POST['win'] ?? 60 ) ) );
		update_option( 'hitscam_verify', in_array( $_POST['verify'] ?? '', array( 'off', 'optional', 'required' ), true ) ? sanitize_key( $_POST['verify'] ) : 'required' );
		echo '<div class="notice notice-success"><p>সেভ হয়েছে।</p></div>';
	}
	$modes = array( 'auto' => 'অটো (SMS গেটওয়ে থাকলে SMS, না থাকলে চ্যানেল)', 'sms' => 'শুধু SMS', 'channel' => 'শুধু চ্যানেল (WhatsApp/IMO…)', 'both' => 'দুটোই (বাটন পাশাপাশি)', 'off' => 'বন্ধ' );
	echo '<div class="wrap"><h1>অর্ডার-সম্পন্ন পেজ সেটিংস</h1><form method="post">'; wp_nonce_field( 'hc_ty' );
	echo '<h2>প্যানেল চালু/বন্ধ</h2><table class="form-table">';
	$o = (array) get_option( 'hitscam_ty_panels', array() );
	foreach ( hitscam_ty_panels() as $k => $l ) { echo '<tr><th>' . esc_html( $l ) . '</th><td><label><input type="checkbox" name="p[' . esc_attr( $k ) . ']" value="1"' . checked( ! isset( $o[ $k ] ) || ! empty( $o[ $k ] ), true, false ) . '> দেখান</label></td></tr>'; }
	echo '</table><h2>মেসেজিং</h2><table class="form-table">';
	foreach ( array( 'reconfirm' => 'অর্ডার রিকনফার্ম', 'notify' => 'আপডেট নোটিফিকেশন' ) as $w => $l ) {
		$cur = get_option( 'hitscam_ty_' . $w . '_mode', 'auto' );
		echo '<tr><th>' . esc_html( $l ) . '</th><td><select name="' . esc_attr( $w ) . '_mode">'; foreach ( $modes as $mk => $ml ) { echo '<option value="' . esc_attr( $mk ) . '"' . selected( $cur, $mk, false ) . '>' . esc_html( $ml ) . '</option>'; } echo '</select></td></tr>';
	}
	echo '<tr><th>সেলফ-সার্ভিস সময় (মিনিট)</th><td><input type="number" name="win" value="' . esc_attr( (int) get_option( 'hitscam_cancel_min', 60 ) ) . '" min="0"> <span class="description">বাতিল/বদল/অ্যাড-অন এই সময়ের মধ্যে</span></td></tr>';
	$v = get_option( 'hitscam_verify', 'required' );
	echo '<tr><th>চেকআউটে ফোন ভেরিফাই</th><td><select name="verify">'; foreach ( array( 'required' => 'বাধ্যতামূলক (WhatsApp/IMO/SMS আগে যাচাই)', 'optional' => 'ঐচ্ছিক', 'off' => 'বন্ধ' ) as $a => $b ) { echo '<option value="' . esc_attr( $a ) . '"' . selected( $v, $a, false ) . '>' . esc_html( $b ) . '</option>'; } echo '</select></td></tr>';
	echo '</table><p><button class="button button-primary" name="hc_ty" value="1">সেভ</button></p></form><p>চ্যানেল তালিকা বদলাতে: HitsCam → মেসেজিং চ্যানেল।</p></div>';
}

/* ---------- পেমেন্ট মেথড: চালু/বন্ধ + টাইটেল/বর্ণনা/আইকন/ক্রম ---------- */
function hitscam_e9_pay_admin() {
	$gws = WC()->payment_gateways()->payment_gateways();
	if ( isset( $_POST['hc_pm'] ) && check_admin_referer( 'hc_pm' ) ) {
		$cfg = array();
		foreach ( $gws as $id => $g ) {
			$r = $_POST['pm'][ $id ] ?? array(); // phpcs:ignore
			$cfg[ $id ] = array( 'on' => empty( $r['on'] ) ? 0 : 1, 'title' => sanitize_text_field( wp_unslash( $r['title'] ?? '' ) ), 'desc' => sanitize_textarea_field( wp_unslash( $r['desc'] ?? '' ) ), 'icon' => sanitize_text_field( wp_unslash( $r['icon'] ?? '' ) ), 'order' => (int) ( $r['order'] ?? 0 ) );
		}
		update_option( 'hitscam_pay_cfg', $cfg );
		echo '<div class="notice notice-success"><p>সেভ হয়েছে।</p></div>';
	}
	$cfg = (array) get_option( 'hitscam_pay_cfg', array() );
	echo '<div class="wrap"><h1>পেমেন্ট মেথড (থিম-স্তরের কাস্টমাইজেশন)</h1><p>চেকআউটে কোন মেথড দেখাবে, নাম, বর্ণনা, আইকন (bootstrap-icons ক্লাস যেমন <code>bi-cash</code>) ও ক্রম। টিক না দিলে চেকআউটে লুকানো থাকবে (WooCommerce সেটিংস অপরিবর্তিত)।</p><form method="post">'; wp_nonce_field( 'hc_pm' );
	echo '<table class="widefat striped"><thead><tr><th>দেখান</th><th>মেথড</th><th>টাইটেল</th><th>বর্ণনা</th><th>আইকন</th><th>ক্রম</th></tr></thead><tbody>';
	$i = 0;
	foreach ( $gws as $id => $g ) {
		$c = $cfg[ $id ] ?? array( 'on' => 'yes' === $g->enabled ? 1 : 0, 'title' => '', 'desc' => '', 'icon' => '', 'order' => $i * 10 ); $i++;
		echo '<tr><td><input type="checkbox" name="pm[' . esc_attr( $id ) . '][on]" value="1"' . checked( ! empty( $c['on'] ), true, false ) . '></td><td><b>' . esc_html( $g->get_method_title() ) . '</b><br><code>' . esc_html( $id ) . '</code>' . ( 'yes' === $g->enabled ? '' : '<br><small>(WooCommerce-এ বন্ধ)</small>' ) . '</td>';
		echo '<td><input type="text" class="regular-text" name="pm[' . esc_attr( $id ) . '][title]" value="' . esc_attr( $c['title'] ) . '" placeholder="' . esc_attr( $g->get_title() ) . '"></td><td><textarea rows="2" name="pm[' . esc_attr( $id ) . '][desc]" style="width:100%">' . esc_textarea( $c['desc'] ) . '</textarea></td><td><input type="text" size="12" name="pm[' . esc_attr( $id ) . '][icon]" value="' . esc_attr( $c['icon'] ) . '"></td><td><input type="number" style="width:70px" name="pm[' . esc_attr( $id ) . '][order]" value="' . esc_attr( (int) $c['order'] ) . '"></td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary" name="hc_pm" value="1">সেভ</button></p></form></div>';
}
add_filter( 'woocommerce_available_payment_gateways', function ( $gws ) {
	if ( is_admin() && ! wp_doing_ajax() ) { return $gws; }
	$cfg = (array) get_option( 'hitscam_pay_cfg', array() );
	if ( ! $cfg ) { return $gws; }
	foreach ( $gws as $id => $g ) {
		$c = $cfg[ $id ] ?? null; if ( ! $c ) { continue; }
		if ( empty( $c['on'] ) ) { unset( $gws[ $id ] ); continue; }
		if ( '' !== $c['title'] ) { $g->title = $c['title']; }
		if ( '' !== $c['desc'] ) { $g->description = $c['desc']; }
		if ( '' !== $c['icon'] ) { $g->icon = ''; $g->hc_icon = $c['icon']; }
	}
	uasort( $gws, function ( $a, $b ) use ( $cfg ) { return (int) ( $cfg[ $a->id ]['order'] ?? 999 ) <=> (int) ( $cfg[ $b->id ]['order'] ?? 999 ); } );
	return $gws;
}, 99 );
add_filter( 'woocommerce_gateway_icon', function ( $icon, $id ) {
	$cfg = (array) get_option( 'hitscam_pay_cfg', array() );
	return ! empty( $cfg[ $id ]['icon'] ) ? '<i class="bi ' . esc_attr( $cfg[ $id ]['icon'] ) . ' text-brand ms-1"></i>' : $icon;
}, 10, 2 );

/* ---------- টোটাল বিক্রি: অ্যাডমিন-নির্বাচিত স্ট্যাটাসের যোগফল ---------- */
function hitscam_sales_statuses() {
	$s = get_option( 'hitscam_sales_statuses', null );
	return is_array( $s ) && $s ? $s : array_keys( array_diff_key( wc_get_order_statuses(), array_flip( array( 'wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-checkout-draft', 'wc-pending' ) ) ) );
}
function hitscam_sold_count( $pid ) {
	$pid = (int) $pid; $ck = 'hc_sold_' . $pid; $v = get_transient( $ck );
	if ( false !== $v ) { return (int) $v; }
	global $wpdb;
	$st = array_map( fn( $s ) => 0 === strpos( $s, 'wc-' ) ? $s : 'wc-' . $s, hitscam_sales_statuses() );
	$tbl = $wpdb->prefix . 'wc_order_product_lookup'; $stat = $wpdb->prefix . 'wc_order_stats'; $n = null;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) { // phpcs:ignore
		$in = implode( ',', array_fill( 0, count( $st ), '%s' ) );
		$q  = $wpdb->prepare( "SELECT SUM(l.product_qty) FROM {$tbl} l JOIN {$stat} s ON s.order_id = l.order_id WHERE l.product_id = %d AND s.status IN ($in)", array_merge( array( $pid ), $st ) ); // phpcs:ignore
		$n  = $wpdb->get_var( $q ); // phpcs:ignore
	}
	$meta = (int) get_post_meta( $pid, 'total_sales', true );
	$out  = null === $n || (int) $n === 0 ? $meta : (int) $n; // বিশ্লেষণ টেবিল খালি/পিছিয়ে থাকলে মেটা
	set_transient( $ck, $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}
function hitscam_e9_sales_admin() {
	if ( isset( $_POST['hc_sl'] ) && check_admin_referer( 'hc_sl' ) ) {
		update_option( 'hitscam_sales_statuses', array_map( 'sanitize_text_field', (array) ( $_POST['st'] ?? array() ) ) );
		update_option( 'hitscam_coupon_guest', empty( $_POST['guest_coupon'] ) ? 0 : 1 );
		global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_hc\_sold\_%' OR option_name LIKE '\_transient\_timeout\_hc\_sold\_%'" ); // phpcs:ignore
		echo '<div class="notice notice-success"><p>সেভ হয়েছে।</p></div>';
	}
	$cur = hitscam_sales_statuses();
	echo '<div class="wrap"><h1>বিক্রি ও কুপন</h1><form method="post">'; wp_nonce_field( 'hc_sl' );
	echo '<h2>প্রোডাক্ট কার্ডের "টোটাল বিক্রি" কোন স্ট্যাটাসের অর্ডার ধরে</h2>';
	foreach ( wc_get_order_statuses() as $k => $l ) { echo '<label style="display:block"><input type="checkbox" name="st[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $cur, true ), true, false ) . '> ' . esc_html( $l ) . '</label>'; }
	echo '<h2>কুপন</h2><label><input type="checkbox" name="guest_coupon" value="1"' . checked( (int) get_option( 'hitscam_coupon_guest', 0 ), 1, false ) . '> গেস্ট (লগইন ছাড়া) ইউজারকেও কুপন ব্যবহার করতে দিন</label><p class="description">ডিফল্ট: শুধু রেজিস্টার্ড ইউজার।</p>';
	echo '<p><button class="button button-primary" name="hc_sl" value="1">সেভ</button></p></form></div>';
}
add_action( 'woocommerce_order_status_changed', function ( $id ) {
	$o = wc_get_order( $id ); if ( ! $o ) { return; }
	foreach ( $o->get_items() as $it ) { delete_transient( 'hc_sold_' . $it->get_product_id() ); }
} );

/* ---------- কুপন: শুধু রেজিস্টার্ড ---------- */
add_filter( 'woocommerce_coupon_is_valid', function ( $ok, $coupon ) {
	if ( ! get_option( 'hitscam_coupon_guest', 0 ) && ! is_user_logged_in() ) {
		throw new Exception( 'কুপন ব্যবহার করতে আগে লগইন/রেজিস্টার করুন।' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $ok;
}, 5, 2 );
add_filter( 'woocommerce_coupons_enabled', function ( $on ) { return ( is_user_logged_in() || get_option( 'hitscam_coupon_guest', 0 ) ) ? $on : false; } );
