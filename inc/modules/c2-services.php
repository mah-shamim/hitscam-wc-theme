<?php
/**
 * c2 — সেবা ও পুরস্কার: প্রফেশনাল ইনস্টলেশন (এলাকা + সময়-স্লট), এক্সটেন্ডেড ওয়ারেন্টি/দুর্ঘটনা কভার, লয়ালটি পয়েন্ট (আয় + রিডিম), রেফারেল কোড।
 * কার্টে প্যানেল (#svcPanel), মোট হিসাবে WC ফি, অর্ডারে মেটা, অ্যাডমিনে দেখা। সেটিংস: WooCommerce → HitsCam সেবা।
 * টেমপ্লেট: services.js
 */
defined( 'ABSPATH' ) || exit;

function hitscam_svc_cfg() {
	$d = array(
		'cats' => '', 'install_dhaka' => 500, 'install_out' => 900,
		'slots' => "সকাল ১০টা – দুপুর ১২টা\nদুপুর ১২টা – ৩টা\nবিকাল ৩টা – ৬টা",
		'ext_pct' => 8, 'acc_pct' => 14,
		'earn_per100' => 2, 'point_value' => 1, 'min_redeem' => 50, 'max_pct' => 20,
		'ref_discount' => 200, 'ref_min' => 2000, 'ref_reward' => 100,
	);
	return wp_parse_args( (array) get_option( 'hitscam_svc', array() ), $d );
}
function hitscam_svc_slots() { return array_values( array_filter( array_map( 'trim', explode( "\n", hitscam_svc_cfg()['slots'] ) ) ) ); }
function hitscam_svc_warranty() {
	$c = hitscam_svc_cfg();
	return array( 'none' => array( 'কোনোটি নয়', 0 ), 'ext' => array( '+১ বছর এক্সটেন্ডেড ওয়ারেন্টি', (float) $c['ext_pct'] ), 'acc' => array( '+১ বছর ওয়ারেন্টি + দুর্ঘটনাজনিত ক্ষতি কভার', (float) $c['acc_pct'] ) );
}

/* ---------- রেফারেল কোড ---------- */
function hitscam_ref_code( $uid ) {
	$c = get_user_meta( $uid, 'hc_referral_code', true );
	if ( $c ) { return $c; }
	$a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	do {
		$c = 'HC-'; for ( $i = 0; $i < 6; $i++ ) { $c .= $a[ wp_rand( 0, strlen( $a ) - 1 ) ]; }
	} while ( get_users( array( 'meta_key' => 'hc_referral_code', 'meta_value' => $c, 'number' => 1, 'fields' => 'ID' ) ) );
	update_user_meta( $uid, 'hc_referral_code', $c );
	return $c;
}
add_action( 'user_register', 'hitscam_ref_code' );
function hitscam_points( $uid ) { return max( 0, (int) get_user_meta( $uid, 'hc_loyalty_points', true ) ); }
function hitscam_points_add( $uid, $n, $why = '' ) {
	update_user_meta( $uid, 'hc_loyalty_points', max( 0, hitscam_points( $uid ) + (int) $n ) );
	$log = (array) get_user_meta( $uid, 'hc_points_log', true ); $log[] = array( time(), (int) $n, $why ); update_user_meta( $uid, 'hc_points_log', array_slice( $log, -50 ) );
}
function hitscam_has_orders( $uid ) {
	return $uid && (bool) wc_get_orders( array( 'customer_id' => $uid, 'limit' => 1, 'status' => array( 'processing', 'completed', 'on-hold' ), 'return' => 'ids' ) );
}

/* ---------- সেশন নির্বাচন ---------- */
function hitscam_svc_get() {
	$s = WC()->session ? WC()->session->get( 'hc_svc' ) : null;
	return wp_parse_args( is_array( $s ) ? $s : array(), array( 'install' => false, 'slot' => '', 'warranty' => 'none', 'pts' => false, 'ref' => '' ) );
}
function hitscam_svc_cam_lines( WC_Cart $cart ) {
	$cats = array_filter( array_map( 'trim', explode( ',', hitscam_svc_cfg()['cats'] ) ) ); $sum = 0;
	foreach ( $cart->get_cart() as $it ) {
		if ( ! $cats || has_term( $cats, 'product_cat', $it['product_id'] ) ) { $sum += (float) $it['line_subtotal']; }
	}
	return $sum;
}
function hitscam_svc_is_dhaka() {
	$st = WC()->customer ? ( WC()->customer->get_shipping_state() ?: WC()->customer->get_billing_state() ) : '';
	return ! $st || 'BD-13' === $st;
}
function hitscam_svc_calc( WC_Cart $cart ) {
	$c = hitscam_svc_cfg(); $s = hitscam_svc_get(); $cam = hitscam_svc_cam_lines( $cart ); $w = hitscam_svc_warranty();
	$sub = (float) $cart->get_subtotal(); $disc = (float) $cart->get_discount_total();
	$r = array( 'cam' => $cam, 'inst' => 0, 'warr' => 0, 'pts' => 0, 'loy' => 0, 'refd' => 0, 'earn' => 0 );
	if ( $s['install'] && $cam ) { $r['inst'] = (float) ( hitscam_svc_is_dhaka() ? $c['install_dhaka'] : $c['install_out'] ); }
	if ( $cam && isset( $w[ $s['warranty'] ] ) ) { $r['warr'] = round( $cam * $w[ $s['warranty'] ][1] / 100 / 10 ) * 10; }
	$uid = get_current_user_id();
	if ( $uid && $s['pts'] ) {
		$bal = hitscam_points( $uid ); $room = max( 0, floor( ( $sub - $disc ) * $c['max_pct'] / 100 ) );
		if ( $bal >= $c['min_redeem'] ) { $r['pts'] = (int) min( $bal, floor( $room / max( 0.01, $c['point_value'] ) ) ); $r['loy'] = $r['pts'] * $c['point_value']; }
	}
	if ( $s['ref'] && $sub - $disc >= $c['ref_min'] && hitscam_ref_valid( $s['ref'] ) === true ) { $r['refd'] = (float) $c['ref_discount']; }
	$r['earn'] = (int) floor( max( 0, $sub - $disc ) / 100 ) * (int) $c['earn_per100'];
	return $r;
}
/** কোড বৈধ কিনা: true বা ত্রুটির বার্তা */
function hitscam_ref_valid( $code ) {
	$code = strtoupper( trim( $code ) );
	if ( ! preg_match( '/^HC-[A-Z0-9]{6}$/', $code ) ) { return 'কোডের ফরম্যাট ঠিক নয় (যেমন HC-AB12CD)'; }
	$u = get_users( array( 'meta_key' => 'hc_referral_code', 'meta_value' => $code, 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $u ) { return 'কোডটি পাওয়া যায়নি'; }
	if ( (int) $u[0] === get_current_user_id() ) { return 'নিজের কোড নিজে ব্যবহার করা যায় না'; }
	if ( hitscam_has_orders( get_current_user_id() ) ) { return 'রেফারেল ছাড় শুধু প্রথম অর্ডারে'; }
	return true;
}

add_action( 'woocommerce_cart_calculate_fees', function ( WC_Cart $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) { return; }
	$r = hitscam_svc_calc( $cart );
	if ( $r['inst'] ) { $cart->add_fee( 'ইনস্টলেশন সেবা', $r['inst'], false ); }
	if ( $r['warr'] ) { $w = hitscam_svc_warranty(); $cart->add_fee( trim( str_replace( '+১ বছর ', '', $w[ hitscam_svc_get()['warranty'] ][0] ) ), $r['warr'], false ); }
	if ( $r['loy'] ) { $cart->add_fee( 'লয়ালটি পয়েন্ট ছাড় (' . hitscam_bn_digits( $r['pts'] ) . ')', -$r['loy'], false ); }
	if ( $r['refd'] ) { $cart->add_fee( 'রেফারেল ছাড়', -$r['refd'], false ); }
}, 30 );

/* ---------- কার্ট প্যানেল ---------- */
add_action( 'woocommerce_before_cart_collaterals', function () {
	if ( WC()->cart->is_empty() ) { return; }
	$c = hitscam_svc_cfg(); $s = hitscam_svc_get(); $r = hitscam_svc_calc( WC()->cart ); $uid = get_current_user_id();
	$m = function ( $n ) { return wp_kses_post( wc_price( $n ) ); };
	$slots = hitscam_svc_slots(); $w = hitscam_svc_warranty(); $bal = $uid ? hitscam_points( $uid ) : 0;
	echo '<div class="panel mb-3" id="svcPanel"><h2 class="h6 fw-bold mb-3"><i class="bi bi-stars text-brand"></i> অতিরিক্ত সেবা ও পুরস্কার</h2>';
	if ( $r['cam'] ) {
		echo '<div class="svc-block"><label class="svc-row"><input type="checkbox" class="form-check-input" id="svcInstall"' . checked( $s['install'], true, false ) . '><span><b>প্রফেশনাল ইনস্টলেশন</b> <small class="text-muted">— টেকনিশিয়ান গিয়ে সেটআপ করে দেবেন</small></span><b class="ms-auto text-brand">' . $m( hitscam_svc_is_dhaka() ? $c['install_dhaka'] : $c['install_out'] ) . '</b></label>';
		echo '<div class="svc-sub" id="svcSlotWrap"' . ( $s['install'] ? '' : ' hidden' ) . '><label class="small text-muted" for="svcSlot">পছন্দের সময়</label><select class="form-select form-select-sm" id="svcSlot">';
		foreach ( $slots as $x ) { echo '<option' . selected( $x, $s['slot'] ?: $slots[0], false ) . '>' . esc_html( $x ) . '</option>'; }
		echo '</select></div></div><div class="svc-block"><div class="small fw-semibold mb-1">ওয়ারেন্টি কভার</div>';
		foreach ( $w as $id => $x ) {
			echo '<label class="svc-row"><input type="radio" class="form-check-input svc-w" name="svcW" value="' . esc_attr( $id ) . '"' . checked( $s['warranty'], $id, false ) . '><span>' . esc_html( $x[0] ) . '</span><b class="ms-auto">' . ( $x[1] ? '+' . $m( round( $r['cam'] * $x[1] / 100 / 10 ) * 10 ) : '' ) . '</b></label>';
		}
		echo '</div>';
	}
	$can = $uid && $bal >= $c['min_redeem'];
	echo '<div class="svc-block"><label class="svc-row ' . ( $can ? '' : 'text-muted' ) . '"><input type="checkbox" class="form-check-input" id="svcPts"' . checked( $s['pts'] && $can, true, false ) . ( $can ? '' : ' disabled' ) . '><span><b>লয়ালটি পয়েন্ট</b> — ' . ( $uid ? 'আপনার ব্যালেন্স: <b>' . esc_html( hitscam_bn_digits( $bal ) ) . '</b> পয়েন্ট ' . ( $can ? '' : '<small>(কমপক্ষে ' . esc_html( hitscam_bn_digits( $c['min_redeem'] ) ) . ' পয়েন্ট লাগে)</small>' ) : '<small>পয়েন্ট ব্যবহার করতে <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">লগইন করুন</a></small>' ) . '</span><b class="ms-auto text-success">' . ( $r['loy'] ? '-' . $m( $r['loy'] ) : '' ) . '</b></label>';
	echo '<div class="small text-muted ps-4">এই অর্ডারে পাবেন <b>' . esc_html( hitscam_bn_digits( $r['earn'] ) ) . '</b> পয়েন্ট (ডেলিভারির পর) · ১ পয়েন্ট = ' . $m( $c['point_value'] ) . ', সর্বোচ্চ ' . esc_html( hitscam_bn_digits( $c['max_pct'] ) ) . '% পর্যন্ত ব্যবহার করা যায়।</div></div>';
	echo '<div class="svc-block"><div class="small fw-semibold mb-1">রেফারেল</div>';
	if ( $s['ref'] ) {
		echo '<div class="small text-success mb-1"><i class="bi bi-check-circle-fill"></i> কোড <b>' . esc_html( $s['ref'] ) . '</b> যুক্ত' . ( $r['refd'] ? ' — ' . $m( $r['refd'] ) . ' ছাড়' : ' (ছাড় পেতে অর্ডার কমপক্ষে ' . $m( $c['ref_min'] ) . ', শুধু প্রথম অর্ডারে)' ) . ' <button class="btn btn-link btn-sm p-0 ms-1" id="refClear" type="button">সরান</button></div>';
	} else {
		echo '<div class="input-group input-group-sm"><input class="form-control" id="refInput" placeholder="বন্ধুর কোড (HC-XXXXXX)" maxlength="9" autocapitalize="characters" aria-label="রেফারেল কোড"><button class="btn btn-brand" id="refApply" type="button">যুক্ত করুন</button></div><div class="small text-danger mt-1" id="refMsg"></div>';
	}
	if ( $uid ) { echo '<div class="small text-muted mt-2">আপনার কোড: <b class="text-brand" id="myRef">' . esc_html( hitscam_ref_code( $uid ) ) . '</b> — বন্ধু প্রথম অর্ডারে ' . $m( $c['ref_discount'] ) . ' ছাড় পাবেন (অর্ডার ' . $m( $c['ref_min'] ) . '+)। <a href="#" id="refShare" data-text="' . esc_attr( 'HitsCam থেকে কিনলে প্রথম অর্ডারে ' . wp_strip_all_tags( wc_price( $c['ref_discount'] ) ) . ' ছাড়! আমার কোড: ' . hitscam_ref_code( $uid ) . ' — ' . home_url( '/' ) ) . '">WhatsApp এ শেয়ার</a></div>'; }
	echo '</div></div>';
} );

function hitscam_svc_ajax() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! WC()->session ) { wp_send_json_error(); }
	if ( ! WC()->session->has_session() ) { WC()->session->set_customer_session_cookie( true ); }
	$s = hitscam_svc_get(); $k = sanitize_key( $_POST['k'] ?? '' ); $v = wp_unslash( $_POST['v'] ?? '' ); $msg = '';
	switch ( $k ) {
		case 'install': $s['install'] = '1' === $v; break;
		case 'slot': $s['slot'] = in_array( $v, hitscam_svc_slots(), true ) ? $v : ''; break;
		case 'warranty': $s['warranty'] = isset( hitscam_svc_warranty()[ $v ] ) ? $v : 'none'; break;
		case 'pts': $s['pts'] = '1' === $v && is_user_logged_in(); break;
		case 'ref':
			$v = strtoupper( sanitize_text_field( $v ) );
			if ( '' === $v ) { $s['ref'] = ''; break; }
			$ok = hitscam_ref_valid( $v );
			if ( true !== $ok ) { wp_send_json_error( array( 'msg' => $ok ) ); }
			$s['ref'] = $v; $msg = 'কোড যুক্ত হয়েছে ✓'; break;
	}
	WC()->session->set( 'hc_svc', $s );
	wp_send_json_success( array( 'msg' => $msg ) );
}
add_action( 'wp_ajax_hc_svc', 'hitscam_svc_ajax' );
add_action( 'wp_ajax_nopriv_hc_svc', 'hitscam_svc_ajax' );

/* ---------- অর্ডারে সংরক্ষণ, পয়েন্ট খরচ/আয়/ফেরত, রেফারেল পুরস্কার ---------- */
add_action( 'woocommerce_checkout_create_order', function ( WC_Order $o ) {
	if ( ! WC()->cart ) { return; }
	$r = hitscam_svc_calc( WC()->cart ); $s = hitscam_svc_get(); $w = hitscam_svc_warranty();
	if ( $r['inst'] ) { $o->update_meta_data( '_hc_install_slot', $s['slot'] ?: ( hitscam_svc_slots()[0] ?? '' ) ); $o->update_meta_data( '_hc_install_fee', $r['inst'] ); }
	if ( $r['warr'] ) { $o->update_meta_data( '_hc_warranty_cover', $w[ $s['warranty'] ][0] ); }
	if ( $r['pts'] ) { $o->update_meta_data( '_hc_points_used', $r['pts'] ); }
	if ( $r['refd'] ) { $o->update_meta_data( '_hc_ref_code', $s['ref'] ); }
	$o->update_meta_data( '_hc_points_earn', $r['earn'] );
} );
add_action( 'woocommerce_checkout_order_processed', function ( $id ) {
	$o = wc_get_order( $id ); $uid = $o ? $o->get_customer_id() : 0;
	if ( ! $o || ! $uid ) { return; }
	$u = (int) $o->get_meta( '_hc_points_used' );
	if ( $u && ! $o->get_meta( '_hc_points_deducted' ) ) { hitscam_points_add( $uid, -$u, 'অর্ডার #' . $o->get_order_number() ); $o->update_meta_data( '_hc_points_deducted', 1 ); $o->save_meta_data(); }
	if ( WC()->session ) { WC()->session->set( 'hc_svc', null ); }
} );
add_action( 'woocommerce_checkout_process', function () {
	$s = hitscam_svc_get();
	if ( $s['ref'] ) {
		$ph = hitscam_normalize_bd_phone( wp_unslash( $_POST['billing_phone'] ?? '' ) ); // phpcs:ignore
		if ( $ph && wc_get_orders( array( 'billing_phone' => $ph, 'limit' => 1, 'return' => 'ids', 'status' => array( 'processing', 'completed', 'on-hold' ) ) ) ) {
			wc_add_notice( 'রেফারেল ছাড় শুধু প্রথম অর্ডারে — এই ফোনে আগে অর্ডার আছে। কার্টে কোডটি সরিয়ে আবার চেষ্টা করুন।', 'error' );
		}
	}
} );
function hitscam_svc_restore( $id ) {
	$o = wc_get_order( $id ); $uid = $o ? $o->get_customer_id() : 0;
	if ( ! $o || ! $uid || ! $o->get_meta( '_hc_points_deducted' ) || $o->get_meta( '_hc_points_restored' ) ) { return; }
	hitscam_points_add( $uid, (int) $o->get_meta( '_hc_points_used' ), 'বাতিল অর্ডার #' . $o->get_order_number() );
	$o->update_meta_data( '_hc_points_restored', 1 ); $o->save_meta_data();
}
add_action( 'woocommerce_order_status_cancelled', 'hitscam_svc_restore' );
add_action( 'woocommerce_order_status_failed', 'hitscam_svc_restore' );
add_action( 'woocommerce_order_status_completed', function ( $id ) {
	$o = wc_get_order( $id ); $uid = $o ? $o->get_customer_id() : 0;
	if ( ! $o || ! $uid || $o->get_meta( '_hc_points_credited' ) ) { return; }
	$e = (int) $o->get_meta( '_hc_points_earn' );
	if ( $e ) { hitscam_points_add( $uid, $e, 'অর্ডার #' . $o->get_order_number() ); }
	if ( $code = $o->get_meta( '_hc_ref_code' ) ) {
		$ref = get_users( array( 'meta_key' => 'hc_referral_code', 'meta_value' => $code, 'number' => 1, 'fields' => 'ID' ) );
		if ( $ref ) { hitscam_points_add( (int) $ref[0], (int) hitscam_svc_cfg()['ref_reward'], 'রেফারেল পুরস্কার #' . $o->get_order_number() ); }
	}
	$o->update_meta_data( '_hc_points_credited', 1 ); $o->save_meta_data();
} );

/* ---------- অ্যাডমিন অর্ডারে দেখা ---------- */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $o ) {
	$a = array( '_hc_install_slot' => 'ইনস্টলেশন সময়', '_hc_warranty_cover' => 'ওয়ারেন্টি কভার', '_hc_points_used' => 'ব্যবহৃত পয়েন্ট', '_hc_ref_code' => 'রেফারেল কোড' ); $h = '';
	foreach ( $a as $k => $l ) { if ( $v = $o->get_meta( $k ) ) { $h .= '<br><b>' . esc_html( $l ) . ':</b> ' . esc_html( $v ); } }
	if ( $h ) { echo '<h3>HitsCam সেবা</h3><p>' . substr( $h, 4 ) . '</p>'; }
} );

/* ---------- সেটিংস পেজ ---------- */
add_action( 'admin_menu', function () { add_submenu_page( 'woocommerce', 'HitsCam সেবা', 'HitsCam সেবা', 'manage_woocommerce', 'hitscam-svc', 'hitscam_svc_admin' ); } );
function hitscam_svc_admin() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	if ( isset( $_POST['hc_svc_n'] ) && wp_verify_nonce( sanitize_key( $_POST['hc_svc_n'] ), 'hc_svc_save' ) ) {
		$in = wp_unslash( $_POST['svc'] ?? array() ); $o = array();
		foreach ( array( 'cats' => 't', 'slots' => 'a', 'install_dhaka' => 'n', 'install_out' => 'n', 'ext_pct' => 'n', 'acc_pct' => 'n', 'earn_per100' => 'n', 'point_value' => 'n', 'min_redeem' => 'n', 'max_pct' => 'n', 'ref_discount' => 'n', 'ref_min' => 'n', 'ref_reward' => 'n' ) as $k => $t ) {
			$v = $in[ $k ] ?? ''; $o[ $k ] = 'n' === $t ? max( 0, (float) $v ) : ( 'a' === $t ? sanitize_textarea_field( $v ) : sanitize_text_field( $v ) );
		}
		update_option( 'hitscam_svc', $o ); echo '<div class="updated"><p>সংরক্ষিত।</p></div>';
	}
	$c = hitscam_svc_cfg();
	$f = array( 'cats' => 'যেসব ক্যাটাগরিতে প্রযোজ্য (স্লাগ, কমা দিয়ে; ফাঁকা = সব পণ্য)', 'install_dhaka' => 'ইনস্টলেশন — ঢাকা (৳)', 'install_out' => 'ইনস্টলেশন — ঢাকার বাইরে (৳)', 'ext_pct' => 'এক্সটেন্ডেড ওয়ারেন্টি (% পণ্যমূল্যের)', 'acc_pct' => 'ওয়ারেন্টি + দুর্ঘটনা কভার (%)', 'earn_per100' => '৳১০০ এ পয়েন্ট আয়', 'point_value' => '১ পয়েন্টের মূল্য (৳)', 'min_redeem' => 'ন্যূনতম রিডিম পয়েন্ট', 'max_pct' => 'সর্বোচ্চ ছাড় (% সাবটোটালের)', 'ref_discount' => 'রেফারেল ছাড় (৳)', 'ref_min' => 'রেফারেল ছাড়ের ন্যূনতম অর্ডার (৳)', 'ref_reward' => 'রেফারারের পুরস্কার (পয়েন্ট)' );
	echo '<div class="wrap"><h1>HitsCam সেবা ও পুরস্কার</h1><form method="post">'; wp_nonce_field( 'hc_svc_save', 'hc_svc_n' ); echo '<table class="form-table">';
	foreach ( $f as $k => $l ) { echo '<tr><th>' . esc_html( $l ) . '</th><td><input name="svc[' . esc_attr( $k ) . ']" value="' . esc_attr( $c[ $k ] ) . '" class="regular-text"></td></tr>'; }
	echo '<tr><th>ইনস্টলেশন সময়-স্লট (প্রতি লাইনে একটি)</th><td><textarea name="svc[slots]" rows="4" class="large-text">' . esc_textarea( $c['slots'] ) . '</textarea></td></tr></table><p><button class="button button-primary">সংরক্ষণ</button></p></form></div>';
}
