<?php
/**
 * e10 — WhatsApp Business (Cloud API): সরাসরি পাঠানো + ইনবাউন্ড ওয়েবহুক (OTP স্বয়ংক্রিয় উত্তর, রিকনফার্ম)।
 * শপের নাম্বার: +8801953073445 (ডিফল্ট; Customizer-এ বদলানো যায়)।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

const HITSCAM_WA_NUMBER = '8801953073445';

/* একবার: পুরনো ডিফল্ট নাম্বার থাকলে নতুন WhatsApp Business নাম্বারে বদল */
add_action( 'init', function () {
	if ( get_option( 'hitscam_wa_migrated' ) ) { return; }
	$cur = preg_replace( '/\D/', '', (string) get_theme_mod( 'hitscam_whatsapp', '' ) );
	if ( '' === $cur || '8801614747054' === $cur ) { set_theme_mod( 'hitscam_whatsapp', HITSCAM_WA_NUMBER ); }
	update_option( 'hitscam_wa_migrated', 1 );
} );

function hitscam_wa_cfg() {
	$c = get_option( 'hitscam_wa', array() );
	$c = is_array( $c ) ? $c : array();
	$c += array( 'pnid' => '', 'waba' => '', 'token' => '', 'vtoken' => '', 'secret' => '', 'otp_tpl' => '', 'tpl_lang' => 'en_US', 'auto' => 1, 'ver' => 'v20.0' );
	if ( '' === $c['vtoken'] ) { $c['vtoken'] = wp_generate_password( 20, false ); update_option( 'hitscam_wa', $c ); }
	return $c;
}
function hitscam_wa_ready() { $c = hitscam_wa_cfg(); return $c['pnid'] && $c['token']; }

/** Graph API কল; সফল হলে true */
function hitscam_wa_api( array $payload ) {
	$c = hitscam_wa_cfg();
	if ( ! hitscam_wa_ready() ) { return false; }
	$r = wp_remote_post( 'https://graph.facebook.com/' . rawurlencode( $c['ver'] ) . '/' . rawurlencode( $c['pnid'] ) . '/messages', array(
		'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $c['token'], 'Content-Type' => 'application/json' ),
		'body' => wp_json_encode( array_merge( array( 'messaging_product' => 'whatsapp' ), $payload ) ),
	) );
	if ( is_wp_error( $r ) ) { update_option( 'hitscam_wa_last', 'ERR: ' . $r->get_error_message(), false ); return false; }
	$code = wp_remote_retrieve_response_code( $r );
	update_option( 'hitscam_wa_last', $code . ' ' . substr( wp_remote_retrieve_body( $r ), 0, 300 ), false );
	return $code >= 200 && $code < 300;
}
function hitscam_wa_to( $phone ) { $p = preg_replace( '/\D/', '', (string) $phone ); return 0 === strpos( $p, '01' ) ? '88' . $p : $p; }
function hitscam_wa_text( $phone, $msg ) { return hitscam_wa_api( array( 'to' => hitscam_wa_to( $phone ), 'type' => 'text', 'text' => array( 'body' => $msg ) ) ); }

/** পাঠানোর হুক (চ্যানেল কী = whatsapp): OTP হলে টেমপ্লেট (থাকলে), নইলে টেক্সট (২৪ ঘণ্টার উইন্ডোর ভেতরে) */
add_filter( 'hitscam_send_channel', function ( $sent, $ch, $phone, $msg ) {
	if ( $sent || 'whatsapp' !== $ch || ! hitscam_wa_ready() ) { return $sent; }
	$c = hitscam_wa_cfg();
	if ( $c['otp_tpl'] && preg_match( '/ভেরিফিকেশন কোড: (\d{6})/u', $msg, $m ) ) {
		$ok = hitscam_wa_api( array( 'to' => hitscam_wa_to( $phone ), 'type' => 'template', 'template' => array(
			'name' => $c['otp_tpl'], 'language' => array( 'code' => $c['tpl_lang'] ),
			'components' => array(
				array( 'type' => 'body', 'parameters' => array( array( 'type' => 'text', 'text' => $m[1] ) ) ),
				array( 'type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => array( array( 'type' => 'text', 'text' => $m[1] ) ) ),
			),
		) ) );
		if ( $ok ) { return true; }
	}
	return hitscam_wa_text( $phone, $msg );
}, 5, 4 );

/* ===== ওয়েবহুক ===== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'hitscam/v1', '/wa', array(
		array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $r ) {
			$c = hitscam_wa_cfg();
			if ( 'subscribe' === $r->get_param( 'hub_mode' ) && hash_equals( $c['vtoken'], (string) $r->get_param( 'hub_verify_token' ) ) ) {
				header( 'Content-Type: text/plain' ); echo esc_html( (string) $r->get_param( 'hub_challenge' ) ); exit;
			}
			return new WP_REST_Response( 'forbidden', 403 );
		} ),
		array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'hitscam_wa_inbound' ),
	) );
} );

function hitscam_wa_inbound( WP_REST_Request $r ) {
	$c = hitscam_wa_cfg(); $raw = $r->get_body();
	if ( $c['secret'] ) {
		$sig = (string) $r->get_header( 'x-hub-signature-256' );
		if ( ! hash_equals( 'sha256=' . hash_hmac( 'sha256', $raw, $c['secret'] ), $sig ) ) { return new WP_REST_Response( 'bad signature', 403 ); }
	}
	$d = json_decode( $raw, true );
	foreach ( (array) ( $d['entry'] ?? array() ) as $e ) {
		foreach ( (array) ( $e['changes'] ?? array() ) as $ch ) {
			foreach ( (array) ( $ch['value']['messages'] ?? array() ) as $m ) {
				$from = preg_replace( '/\D/', '', (string) ( $m['from'] ?? '' ) ); $txt = (string) ( $m['text']['body'] ?? '' );
				if ( $from && $txt ) { hitscam_wa_handle( $from, $txt ); }
			}
		}
	}
	return array( 'ok' => true );
}

/** গ্রাহকের মেসেজ: ভেরিফাই অনুরোধ → কোড পাঠান; "অর্ডার #N রিকনফার্ম" → রিকনফার্ম */
function hitscam_wa_handle( $from, $txt ) {
	$c = hitscam_wa_cfg(); if ( ! $c['auto'] ) { return; }
	$ph = hitscam_normalize_bd_phone( $from );
	if ( ! $ph ) { return; }
	if ( preg_match( '/রিকনফার্ম|reconfirm|confirm/iu', $txt ) && preg_match( '/#\s*(\d+)/', $txt, $m ) ) {
		$o = wc_get_order( absint( $m[1] ) );
		if ( $o && hitscam_normalize_bd_phone( $o->get_billing_phone() ) === $ph ) {
			if ( hitscam_do_reconfirm( $o, 'WhatsApp মেসেজ' ) ) { hitscam_wa_text( $from, 'ধন্যবাদ! আপনার অর্ডার #' . $o->get_order_number() . ' রিকনফার্ম হয়েছে ✓ — স্ট্যাটাস: ' . wc_get_order_status_name( $o->get_status() ) ); }
			else { hitscam_wa_text( $from, 'অর্ডার #' . $o->get_order_number() . ' আগেই রিকনফার্ম করা আছে।' ); }
		}
		return;
	}
	if ( preg_match( '/verify|ভেরিফাই/iu', $txt ) ) {
		$code = get_transient( 'hc_vc_' . md5( $ph ) );
		hitscam_wa_text( $from, $code ? 'HitsCam ভেরিফিকেশন কোড: ' . $code . ' (১০ মিনিট বৈধ)' : 'কোডের মেয়াদ শেষ — চেকআউট পেজে আবার "অর্ডার করুন" চেপে WhatsApp বাছুন।' );
	}
}

/* ===== অ্যাডমিন ===== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'WhatsApp Business', 'WhatsApp Business', 'manage_woocommerce', 'hitscam-wa', 'hitscam_wa_admin', 24 );
}, 20 );
function hitscam_wa_admin() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	$c = hitscam_wa_cfg();
	if ( isset( $_POST['hc_wa'] ) && check_admin_referer( 'hc_wa' ) ) {
		foreach ( array( 'pnid', 'waba', 'otp_tpl', 'tpl_lang', 'ver' ) as $k ) { $c[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ); }
		foreach ( array( 'token', 'secret' ) as $k ) { $v = trim( (string) wp_unslash( $_POST[ $k ] ?? '' ) ); if ( '' !== $v ) { $c[ $k ] = $v; } }
		if ( ! empty( $_POST['clear_token'] ) ) { $c['token'] = ''; }
		$c['auto'] = empty( $_POST['auto'] ) ? 0 : 1; $c['ver'] = $c['ver'] ?: 'v20.0';
		update_option( 'hitscam_wa', $c ); echo '<div class="notice notice-success"><p>সংরক্ষিত।</p></div>';
	}
	if ( isset( $_POST['hc_wa_test'] ) && check_admin_referer( 'hc_wa' ) ) {
		$ok = hitscam_wa_text( sanitize_text_field( wp_unslash( $_POST['test_phone'] ?? '' ) ), 'HitsCam WhatsApp টেস্ট ✓' );
		echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . '"><p>' . ( $ok ? 'পাঠানো হয়েছে।' : 'ব্যর্থ।' ) . '</p></div>';
	}
	$hook = rest_url( 'hitscam/v1/wa' ); $f = 'class="regular-text code"';
	echo '<div class="wrap"><h1>WhatsApp Business — +' . esc_html( HITSCAM_WA_NUMBER ) . '</h1>';
	echo '<p>স্ট্যাটাস: ' . ( hitscam_wa_ready() ? '<b style="color:#1a7f37">API যুক্ত — OTP/আপডেট সরাসরি যাবে</b>' : '<b style="color:#b32d2e">API যুক্ত নেই — এখন গ্রাহক wa.me লিংকে ম্যানুয়াল পদ্ধতিতে মেসেজ করবে</b>' ) . '</p>';
	echo '<ol><li><a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">Meta for Developers</a> → App তৈরি (Business) → WhatsApp প্রোডাক্ট যোগ।</li><li>WhatsApp → API Setup-এ আপনার নাম্বার +' . esc_html( HITSCAM_WA_NUMBER ) . ' যোগ ও OTP-তে ভেরিফাই করুন (এই নাম্বার সাধারণ WhatsApp অ্যাপে চালু থাকলে আগে ওটা থেকে ডিলিট/মাইগ্রেট করতে হয়)।</li><li>নিচে <b>Phone Number ID</b> ও স্থায়ী <b>Access Token</b> (System User token) বসান।</li><li>WhatsApp → Configuration → Webhook: Callback URL ও Verify Token নিচেরটা দিন, <code>messages</code> সাবস্ক্রাইব করুন।</li></ol>';
	echo '<form method="post">'; wp_nonce_field( 'hc_wa' ); echo '<table class="form-table">';
	echo '<tr><th>Phone Number ID</th><td><input ' . $f . ' name="pnid" value="' . esc_attr( $c['pnid'] ) . '"></td></tr>';
	echo '<tr><th>WhatsApp Business Account ID</th><td><input ' . $f . ' name="waba" value="' . esc_attr( $c['waba'] ) . '"></td></tr>';
	echo '<tr><th>Access Token</th><td><input ' . $f . ' type="password" name="token" placeholder="' . ( $c['token'] ? '•••••• (সংরক্ষিত — বদলাতে নতুন বসান)' : '' ) . '" autocomplete="off"> <label><input type="checkbox" name="clear_token" value="1"> মুছুন</label></td></tr>';
	echo '<tr><th>App Secret (ঐচ্ছিক)</th><td><input ' . $f . ' type="password" name="secret" placeholder="' . ( $c['secret'] ? '••••••' : 'ওয়েবহুক সিগনেচার যাচাই' ) . '" autocomplete="off"></td></tr>';
	echo '<tr><th>OTP টেমপ্লেট (ঐচ্ছিক)</th><td><input name="otp_tpl" value="' . esc_attr( $c['otp_tpl'] ) . '" placeholder="authentication টেমপ্লেটের নাম"> ভাষা <input name="tpl_lang" value="' . esc_attr( $c['tpl_lang'] ) . '" size="7"><p class="description">গ্রাহক আগে মেসেজ না করলে (২৪ ঘণ্টার বাইরে) শুধু অনুমোদিত টেমপ্লেটে পাঠানো যায়। Authentication টেমপ্লেট (বডিতে {{1}} কোড + কপি-কোড বাটন) বানালে এখানে নাম দিন।</p></td></tr>';
	echo '<tr><th>API ভার্সন</th><td><input name="ver" value="' . esc_attr( $c['ver'] ) . '" size="8"></td></tr>';
	echo '<tr><th>ইনবাউন্ড অটো-রিপ্লাই</th><td><label><input type="checkbox" name="auto" value="1"' . checked( $c['auto'], 1, false ) . '> গ্রাহক "verify" লিখে পাঠালে কোড, আর "অর্ডার #N রিকনফার্ম" লিখলে অর্ডার রিকনফার্ম (ফোন মিললে)</label></td></tr>';
	echo '<tr><th>Callback URL</th><td><input class="large-text code" readonly onclick="this.select()" value="' . esc_attr( $hook ) . '"></td></tr>';
	echo '<tr><th>Verify Token</th><td><input class="regular-text code" readonly onclick="this.select()" value="' . esc_attr( $c['vtoken'] ) . '"></td></tr></table>';
	echo '<p><button class="button button-primary" name="hc_wa" value="1">সংরক্ষণ</button></p>';
	echo '<hr><h2>টেস্ট মেসেজ</h2><p>ফোন <input name="test_phone" placeholder="01XXXXXXXXX"> <button class="button" name="hc_wa_test" value="1">পাঠান</button> <span class="description">(নাম্বার আগে এই বিজনেস নাম্বারে মেসেজ করলে ২৪ ঘণ্টা টেক্সট চলে)</span></p>';
	$last = get_option( 'hitscam_wa_last' ); if ( $last ) { echo '<p><b>সর্বশেষ API রেসপন্স:</b> <code>' . esc_html( $last ) . '</code></p>'; }
	echo '</form></div>';
}
