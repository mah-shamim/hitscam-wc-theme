<?php
/**
 * e3 — মেসেজিং চ্যানেল রেজিস্ট্রি (WhatsApp, IMO, Telegram, Viber… অ্যাডমিন নিজে নতুন যোগ করতে পারে),
 * SMS গেটওয়ে না থাকলে সেগুলো দিয়ে: ফোন ভেরিফাই, অর্ডার রিকনফার্ম, আপডেট নোটিফিকেশন।
 * কুরিয়ার ট্র্যাকার (লিংক/API ওয়েবহুক) ও অ্যাডমিন-সংজ্ঞায়িত কুরিয়ার।
 *
 * কোড থেকে: add_filter('hitscam_channels', fn($c)=>…); add_filter('hitscam_send_sms', …) (SMS গেটওয়ে)।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

/* ================= রেজিস্ট্রি ================= */
const HITSCAM_CH_DEFAULT = "# কী|লেবেল|আইকন|লিংক প্যাটার্ন|নাম্বার/ইউজারনেম|ব্যবহার (verify,reconfirm,notify)\n# লিংকে {num}=শপের নাম্বার, {phone}=গ্রাহকের নাম্বার, {text}=মেসেজ। লিংকে {text} না থাকলে মেসেজ কপি হয়ে অ্যাপ খুলবে (IMO-র মতো)।\n# লাইনের শুরুতে # দিলে চ্যানেল বন্ধ।\nwhatsapp|WhatsApp|bi-whatsapp|https://wa.me/{num}?text={text}|{wa}|verify,reconfirm,notify\nimo|IMO|bi-chat-heart-fill|https://imo.im/|{wa}|verify,reconfirm,notify\n#telegram|Telegram|bi-telegram|https://t.me/{num}?text={text}|your_username|verify,reconfirm,notify\n#viber|Viber|bi-telephone-inbound-fill|viber://chat?number=%2B{num}|8801XXXXXXXXX|reconfirm,notify\n#messenger|Messenger|bi-messenger|https://m.me/{num}?text={text}|your_page|reconfirm,notify";

function hitscam_sms_ready() { return (bool) has_filter( 'hitscam_send_sms' ); }

/** চালু চ্যানেল; $use = verify|reconfirm|notify (ফাঁকা = সব) */
function hitscam_channels( $use = '' ) {
	$out = array();
	foreach ( hitscam_e0_lines( 'hitscam_channels', HITSCAM_CH_DEFAULT ) as $r ) {
		if ( count( $r ) < 4 || '' === $r[0] ) { continue; }
		$num  = $r[4] ?? '';
		$num  = '{wa}' === $num || '' === $num ? hitscam_opt( 'whatsapp', '8801953073445' ) : $num;
		$uses = array_map( 'trim', explode( ',', $r[5] ?? 'verify,reconfirm,notify' ) );
		$out[ $r[0] ] = array( 'key' => $r[0], 'label' => $r[1], 'icon' => $r[2] ?: 'bi-chat-dots', 'url' => $r[3], 'num' => preg_match( '/^[\d+ -]+$/', $num ) ? preg_replace( '/\D/', '', $num ) : $num, 'uses' => $uses );
	}
	$out = apply_filters( 'hitscam_channels', $out );
	return $use ? array_filter( $out, fn( $c ) => in_array( $use, $c['uses'], true ) ) : $out;
}

function hitscam_channel_link( array $c, $text, $phone = '' ) {
	return str_replace( array( '{num}', '{phone}', '{text}' ), array( rawurlencode( $c['num'] ), rawurlencode( $phone ), rawurlencode( $text ) ), $c['url'] );
}

/** গ্রাহক কোন পথে? 'sms' = গেটওয়ে আছে; 'channel' = চ্যানেল বাটন। অ্যাডমিন সেটিং: auto|both|channel */
function hitscam_msg_mode() {
	$m = get_option( 'hitscam_chan_mode', 'auto' );
	if ( 'channel' === $m ) { return 'channel'; }
	return hitscam_sms_ready() ? 'sms' : 'channel';
}

function hitscam_channel_buttons( $use, $text, $phone = '', $cls = 'btn btn-soft rounded-pill btn-sm' ) {
	$h = '';
	foreach ( hitscam_channels( $use ) as $c ) {
		$copy = false === strpos( $c['url'], '{text}' );
		$h   .= '<a class="' . esc_attr( $cls ) . ' hc-chan" target="_blank" rel="noopener" href="' . esc_url( hitscam_channel_link( $c, $text, $phone ) ) . '" data-ch="' . esc_attr( $c['key'] ) . '"' . ( $copy ? ' data-copy="' . esc_attr( $text ) . '"' : '' ) . '><i class="bi ' . esc_attr( $c['icon'] ) . '"></i> ' . esc_html( $c['label'] ) . '</a> ';
	}
	return $h;
}

/** SMS পাঠানোর চেষ্টা (গেটওয়ে থাকলে) — সফল হলে true */
function hitscam_try_sms( $phone, $msg ) { return hitscam_sms_ready() && (bool) apply_filters( 'hitscam_send_sms', false, $phone, $msg ); }

/* ================= অ্যাডমিন পেজ ================= */
add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'চ্যানেল ও ভেরিফিকেশন', 'চ্যানেল ও ভেরিফিকেশন', 'manage_woocommerce', 'hitscam-channels', 'hitscam_e3_admin', 25 );
}, 20 );

function hitscam_e3_secret() {
	$s = get_option( 'hitscam_hook_secret' );
	if ( ! $s ) { $s = wp_generate_password( 24, false ); update_option( 'hitscam_hook_secret', $s, false ); }
	return $s;
}

function hitscam_e3_admin() {
	if ( isset( $_POST['hc_e3'] ) && check_admin_referer( 'hc_e3' ) ) {
		update_option( 'hitscam_channels', sanitize_textarea_field( wp_unslash( $_POST['channels'] ?? '' ) ) );
		update_option( 'hitscam_couriers_x', sanitize_textarea_field( wp_unslash( $_POST['couriers'] ?? '' ) ) );
		update_option( 'hitscam_chan_mode', in_array( $_POST['mode'] ?? '', array( 'auto', 'channel' ), true ) ? sanitize_key( $_POST['mode'] ) : 'auto' );
		update_option( 'hitscam_verify', in_array( $_POST['verify'] ?? '', array( 'off', 'optional', 'required' ), true ) ? sanitize_key( $_POST['verify'] ) : 'optional' );
		update_option( 'hitscam_verify_hard', empty( $_POST['verify_hard'] ) ? 0 : 1 );
		echo '<div class="notice notice-success"><p>সেভ হয়েছে।</p></div>';
	}
	$mode = get_option( 'hitscam_chan_mode', 'auto' ); $ver = get_option( 'hitscam_verify', 'required' );
	$hook = rest_url( 'hitscam/v1/' );
	echo '<div class="wrap"><h1>চ্যানেল ও ভেরিফিকেশন</h1><form method="post">'; wp_nonce_field( 'hc_e3' );
	echo '<p><b>SMS গেটওয়ে:</b> ' . ( hitscam_sms_ready() ? '<span style="color:#1a7f37">যুক্ত আছে (hitscam_send_sms ফিল্টার)</span>' : '<span style="color:#b32d2e">যুক্ত নেই — নিচের চ্যানেলগুলো ব্যবহার হবে</span>' ) . '</p>';
	echo '<table class="form-table"><tr><th>মেসেজ মোড</th><td><select name="mode"><option value="auto"' . selected( $mode, 'auto', false ) . '>অটো — গেটওয়ে থাকলে SMS, না থাকলে চ্যানেল</option><option value="channel"' . selected( $mode, 'channel', false ) . '>সবসময় চ্যানেল (WhatsApp/IMO…)</option></select><p class="description">রিকনফার্ম, ফোন ভেরিফাই ও "অর্ডারের আপডেট নোটিফিকেশনে পান" এ প্রযোজ্য।</p></td></tr>';
	echo '<tr><th>ফোন ভেরিফাই (চেকআউট/ইনস্ট্যান্ট অর্ডার)</th><td><select name="verify"><option value="off"' . selected( $ver, 'off', false ) . '>বন্ধ</option><option value="optional"' . selected( $ver, 'optional', false ) . '>ঐচ্ছিক (বাটন দেখাবে)</option><option value="required"' . selected( $ver, 'required', false ) . '>বাধ্যতামূলক</option></select><br><label><input type="checkbox" name="verify_hard" value="1"' . checked( 1, (int) get_option( 'hitscam_verify_hard', 0 ), false ) . '> বাধ্যতামূলকে শুধু SMS কোড/ওয়েবহুকে যাচাই গ্রহণযোগ্য (গ্রাহকের "আমি পাঠিয়েছি" ক্লেইম নয়)</label></td></tr>';
	echo '<tr><th>চ্যানেল তালিকা</th><td><textarea name="channels" rows="12" class="large-text code">' . esc_textarea( (string) get_option( 'hitscam_channels', HITSCAM_CH_DEFAULT ) ) . '</textarea><p class="description">প্রতি লাইনে একটি: <code>কী|লেবেল|আইকন|লিংক|নাম্বার|ব্যবহার</code>। নতুন চ্যানেল (যেমন Signal, Line) শুধু একটি লাইন যোগ করলেই চালু। ফাঁকা করে সেভ দিলে ডিফল্ট ফিরবে।</p></td></tr>';
	echo '<tr><th>কুরিয়ার (কাস্টম)</th><td><textarea name="couriers" rows="5" class="large-text code" placeholder="paperfly|Paperfly|https://paperfly.com.bd/track?id={id}">' . esc_textarea( (string) get_option( 'hitscam_couriers_x', '' ) ) . '</textarea><p class="description"><code>কী|নাম|ট্র্যাকিং লিংক ({id} কনসাইনমেন্ট, {phone})</code> — অর্ডারের কুরিয়ার মেটাবক্সে এসে যাবে। বিল্ট-ইন: Pathao, Steadfast, RedX।</p></td></tr></table>';
	echo '<p><button class="button button-primary" name="hc_e3" value="1">সেভ</button></p></form>';
	echo '<h2>ওয়েবহুক (API)</h2><p>সিক্রেট: <code>' . esc_html( hitscam_e3_secret() ) . '</code> (হেডার <code>X-HC-Secret</code> বা প্যারামিটার <code>secret</code>)</p><ul style="list-style:disc;margin-left:1.5em"><li><b>কুরিয়ার আপডেট</b> POST <code>' . esc_html( $hook ) . 'courier</code> — <code>consignment</code>, <code>status</code>, <code>note</code>(ঐচ্ছিক), <code>delivered</code>=1 (ঐচ্ছিক → অর্ডার সম্পন্ন)</li><li><b>ফোন ভেরিফাই</b> POST <code>' . esc_html( $hook ) . 'verify</code> — <code>phone</code>, <code>code</code> (WhatsApp/IMO বট গ্রাহকের পাঠানো কোড ফরোয়ার্ড করলে অটো যাচাই)</li></ul></div>';
}

/* কাস্টম কুরিয়ার রেজিস্ট্রিতে যোগ */
add_filter( 'hitscam_couriers', function ( $c ) {
	foreach ( hitscam_e0_lines( 'hitscam_couriers_x', '' ) as $r ) { if ( count( $r ) >= 3 ) { $c[ sanitize_key( $r[0] ) ] = array( $r[1], $r[2] ); } }
	return $c;
}, 5 );

/* ================= কুরিয়ার ট্র্যাকার ================= */
function hitscam_courier_info( WC_Order $o ) {
	$cid = (string) $o->get_meta( '_hc_consignment' ); $ck = (string) $o->get_meta( '_hc_courier' ); $co = hitscam_a2_couriers();
	if ( ! $cid ) { return null; }
	$name = $co[ $ck ][0] ?? ( $ck ?: 'কুরিয়ার' );
	$url  = isset( $co[ $ck ] ) ? str_replace( array( '{id}', '{phone}' ), array( rawurlencode( $cid ), rawurlencode( $o->get_billing_phone() ) ), $co[ $ck ][1] ) : '';
	$ev   = apply_filters( 'hitscam_courier_events', (array) $o->get_meta( '_hc_courier_events' ), $o );
	return array( 'name' => $name, 'cn' => $cid, 'url' => $url, 'events' => array_reverse( array_values( $ev ) ) );
}

add_action( 'rest_api_init', function () {
	$auth = function ( WP_REST_Request $r ) {
		$s = (string) ( $r->get_header( 'x-hc-secret' ) ?: $r->get_param( 'secret' ) );
		return hash_equals( hitscam_e3_secret(), $s );
	};
	register_rest_route( 'hitscam/v1', '/courier', array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => function ( WP_REST_Request $r ) {
		$cn = sanitize_text_field( (string) $r->get_param( 'consignment' ) );
		$q  = wc_get_orders( array( 'limit' => 1, 'meta_key' => '_hc_consignment', 'meta_value' => $cn ) );
		if ( ! $cn || ! $q ) { return new WP_REST_Response( array( 'ok' => false, 'msg' => 'not found' ), 404 ); }
		$o = $q[0]; $ev = (array) $o->get_meta( '_hc_courier_events' );
		$ev[] = array( 't' => time(), 's' => sanitize_text_field( (string) $r->get_param( 'status' ) ), 'n' => sanitize_text_field( (string) $r->get_param( 'note' ) ) );
		$o->update_meta_data( '_hc_courier_events', array_slice( $ev, -30 ) );
		if ( $r->get_param( 'delivered' ) && ! $o->has_status( array( 'completed', 'cancelled', 'refunded' ) ) ) { $o->update_status( 'completed', 'কুরিয়ার ওয়েবহুক: ডেলিভারি সম্পন্ন। ' ); }
		$o->save();
		return array( 'ok' => true );
	} ) );
	register_rest_route( 'hitscam/v1', '/verify', array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => function ( WP_REST_Request $r ) {
		$ph = hitscam_normalize_bd_phone( (string) $r->get_param( 'phone' ) ); $code = preg_replace( '/\D/', '', (string) $r->get_param( 'code' ) );
		$ok = $ph && $code && hash_equals( (string) get_transient( 'hc_vc_' . md5( $ph ) ), $code );
		if ( $ok ) { set_transient( 'hc_vf_' . md5( $ph ), 'webhook', HOUR_IN_SECONDS ); delete_transient( 'hc_vc_' . md5( $ph ) ); }
		return array( 'ok' => (bool) $ok );
	} ) );
} );

/* ================= ফোন ভেরিফাই ================= */
function hitscam_verified_via( $phone ) { $p = hitscam_normalize_bd_phone( $phone ); return $p ? (string) get_transient( 'hc_vf_' . md5( $p ) ) : ''; }
function hitscam_verify_mode() { return get_option( 'hitscam_verify', 'required' ); }

function hitscam_e3_ajax_vsend() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$ph = hitscam_normalize_bd_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	if ( ! $ph ) { wp_send_json_error( array( 'msg' => 'সঠিক মোবাইল নাম্বার দিন।' ) ); }
	if ( ! hitscam_a2_limit( 'vsend', 8, 900 ) ) { wp_send_json_error( array( 'msg' => 'অনেকবার চেষ্টা হয়েছে, কিছুক্ষণ পরে চেষ্টা করুন।' ) ); }
	$ch   = sanitize_key( $_POST['ch'] ?? '' );
	$code = (string) wp_rand( 100000, 999999 );
	set_transient( 'hc_vc_' . md5( $ph ), $code, 10 * MINUTE_IN_SECONDS );
	$msg = 'HitsCam ভেরিফিকেশন কোড: ' . $code . ' (১০ মিনিট বৈধ)';
	if ( 'sms' === $ch || '' === $ch ) {
		if ( hitscam_try_sms( $ph, $msg ) ) { wp_send_json_success( array( 'mode' => 'sent', 'via' => 'SMS' ) ); }
		if ( 'sms' === $ch ) { wp_send_json_error( array( 'msg' => 'SMS গেটওয়ে যুক্ত নেই — অন্য মাধ্যম বেছে নিন।' ) ); }
	}
	$chs = hitscam_channels( 'verify' ); $c = $chs[ $ch ] ?? null;
	if ( ! $c ) { wp_send_json_error( array( 'msg' => 'মাধ্যম পাওয়া যায়নি।' ) ); }
	// ইন্টিগ্রেশন হুক: WhatsApp Cloud API / IMO গেটওয়ে যুক্ত থাকলে true ফেরত দিলে সরাসরি পৌঁছে যায়
	if ( apply_filters( 'hitscam_send_channel', false, $ch, $ph, $msg, $c ) ) { wp_send_json_success( array( 'mode' => 'sent', 'via' => $c['label'] ) ); }
	// গেটওয়ে নেই: গ্রাহক শপের নাম্বারে অনুরোধ পাঠায়, টিম কোড জানায় (অথবা অ্যাডমিন সফট-ভেরিফাই চালু রাখলে "পাঠিয়েছি")
	$text = 'HitsCam verify request: ' . $ph;
	wp_send_json_success( array( 'mode' => 'manual', 'via' => $c['label'], 'link' => hitscam_channel_link( $c, $text, $ph ), 'soft' => ! get_option( 'hitscam_verify_hard' ) ) );
}
add_action( 'wp_ajax_hc_vsend', 'hitscam_e3_ajax_vsend' );
add_action( 'wp_ajax_nopriv_hc_vsend', 'hitscam_e3_ajax_vsend' );

function hitscam_e3_ajax_vcheck() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$ph = hitscam_normalize_bd_phone( wp_unslash( $_POST['phone'] ?? '' ) ); $mode = sanitize_key( $_POST['kind'] ?? 'code' );
	if ( ! $ph ) { wp_send_json_error( array( 'msg' => 'সঠিক নাম্বার দিন।' ) ); }
	if ( 'status' === $mode ) { wp_send_json_success( array( 'ok' => (bool) hitscam_verified_via( $ph ) ) ); }
	if ( 'claim' === $mode ) { // গ্রাহক চ্যানেলে পাঠিয়েছে বলে ক্লেইম — নরম ভেরিফিকেশন
		if ( get_option( 'hitscam_verify_hard' ) ) { wp_send_json_error( array( 'msg' => 'অ্যাডমিনের যাচাই/কোড ছাড়া গ্রহণযোগ্য নয় — কোড বসান বা অপেক্ষা করুন।' ) ); }
		set_transient( 'hc_vf_' . md5( $ph ), 'claimed', HOUR_IN_SECONDS ); wp_send_json_success( array( 'ok' => true ) );
	}
	if ( ! hitscam_a2_limit( 'vcheck', 10, 900 ) ) { wp_send_json_error( array( 'msg' => 'অনেকবার চেষ্টা, পরে করুন।' ) ); }
	$code = preg_replace( '/\D/', '', wp_unslash( $_POST['code'] ?? '' ) );
	if ( $code && hash_equals( (string) get_transient( 'hc_vc_' . md5( $ph ) ), $code ) ) {
		set_transient( 'hc_vf_' . md5( $ph ), 'sms', HOUR_IN_SECONDS ); delete_transient( 'hc_vc_' . md5( $ph ) );
		wp_send_json_success( array( 'ok' => true ) );
	}
	wp_send_json_error( array( 'msg' => 'কোড মেলেনি।' ) );
}
add_action( 'wp_ajax_hc_vcheck', 'hitscam_e3_ajax_vcheck' );
add_action( 'wp_ajax_nopriv_hc_vcheck', 'hitscam_e3_ajax_vcheck' );

/** চেকআউটে বাধ্যতামূলক হলে যাচাই */
add_action( 'woocommerce_checkout_process', function () {
	if ( 'required' !== hitscam_verify_mode() ) { return; }
	$v = hitscam_verified_via( wp_unslash( $_POST['billing_phone'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $v || ( 'claimed' === $v && get_option( 'hitscam_verify_hard' ) ) ) { wc_add_notice( 'ফোন নাম্বার ভেরিফাই করুন (ফোন ঘরের নিচের বাটন)।', 'error' ); }
} );
add_action( 'woocommerce_checkout_order_created', function ( $o ) {
	$v = hitscam_verified_via( $o->get_billing_phone() );
	if ( $v ) { $o->update_meta_data( '_hc_phone_verified', $v ); $o->save(); }
} );
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $o ) {
	$v = $o->get_meta( '_hc_phone_verified' ); $ch = $o->get_meta( '_hc_notify_ch' );
	echo '<p><b>ফোন ভেরিফাই:</b> ' . ( $v ? esc_html( array( 'sms' => '✓ SMS কোড', 'webhook' => '✓ ওয়েবহুক', 'claimed' => '◐ গ্রাহকের ক্লেইম (চ্যানেল)' )[ $v ] ?? $v ) : '— নেই' ) . '</p>';
	if ( $ch ) { echo '<p><b>আপডেট নিতে চান:</b> ' . esc_html( $ch ) . '</p>'; }
} );

/* ================= অর্ডার রিসিভড: নোটিফিকেশন অপ্ট-ইন + রিকনফার্ম চ্যানেল ================= */
function hitscam_e3_ajax_notify() {
	$o = hitscam_order_from_post();
	$ch = sanitize_key( $_POST['ch'] ?? '' );
	if ( $ch && isset( hitscam_channels( 'notify' )[ $ch ] ) ) {
		$o->update_meta_data( '_hc_notify_ch', $ch ); $o->add_order_note( 'গ্রাহক আপডেট পেতে চেয়েছেন: ' . hitscam_channels()[ $ch ]['label'] ); $o->save();
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_hc_notify_ch', 'hitscam_e3_ajax_notify' );
add_action( 'wp_ajax_nopriv_hc_notify_ch', 'hitscam_e3_ajax_notify' );

/** অর্ডারের স্ট্যাটাস বদলালে: SMS গেটওয়ে থাকলে সরাসরি; না থাকলে অ্যাডমিন নোটে চ্যানেল-লিংক (ডেলিভারি টিম এক ক্লিকে পাঠাবে) */
add_action( 'woocommerce_order_status_changed', function ( $id, $from, $to, $o ) {
	if ( ! in_array( $to, hitscam_notify_statuses(), true ) || ! $o->get_meta( '_hc_notify_ch' ) || ( $GLOBALS['hc_notified'] ?? 0 ) === $id ) { return; }
	$msg = 'HitsCam: আপনার অর্ডার #' . $o->get_order_number() . ' এখন "' . wc_get_order_status_name( $to ) . '"।';
	if ( hitscam_try_sms( $o->get_billing_phone(), $msg ) ) { return; }
	$cs = hitscam_channels(); $c = $cs[ $o->get_meta( '_hc_notify_ch' ) ] ?? null; $np = hitscam_normalize_bd_phone( $o->get_billing_phone() );
	if ( $c && $np ) { $c['num'] = '88' . $np; $o->add_order_note( 'গ্রাহককে ' . $c['label'] . '-এ জানান: ' . hitscam_channel_link( $c, $msg, '88' . $np ) ); }
}, 10, 4 );
