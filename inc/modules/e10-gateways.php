<?php
/**
 * e10 — গেটওয়ে ম্যানেজার: কোড ছাড়াই অ্যাডমিন থেকে একাধিক SMS / WhatsApp / IMO / যেকোনো চ্যানেলের API গেটওয়ে যোগ।
 * প্রতিটি গেটওয়ে = একটি HTTP কল (URL, মেথড, হেডার, বডি টেমপ্লেট)। প্লেসহোল্ডার: {phone} (8801…), {phone_local} (01…), {message}, {message_json}, {message_url}।
 * চ্যানেল কী: sms = SMS গেটওয়ে (OTP, রিকনফার্ম, আপডেট); অন্য কী = "চ্যানেল ও ভেরিফিকেশন" পেজে যুক্ত চ্যানেলের কী (whatsapp, imo, telegram…)।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

function hitscam_gateways( $only_on = true ) {
	$g = get_option( 'hitscam_gateways', array() );
	$g = is_array( $g ) ? $g : array();
	return $only_on ? array_filter( $g, fn( $x ) => ! empty( $x['on'] ) && ! empty( $x['url'] ) ) : $g;
}

/** একটি গেটওয়ে দিয়ে পাঠান; সফল হলে true */
function hitscam_gateway_send( array $g, $phone, $msg ) {
	$p88   = preg_replace( '/\D/', '', (string) $phone ); if ( 0 === strpos( $p88, '01' ) ) { $p88 = '88' . $p88; }
	$local = preg_match( '/^880(1\d{9})$/', $p88, $m ) ? '0' . $m[1] : $p88;
	$json  = trim( wp_json_encode( (string) $msg ), '"' );
	$rep   = array( '{phone}' => $p88, '{phone_local}' => $local, '{message}' => $msg, '{message_json}' => $json, '{message_url}' => rawurlencode( $msg ) );
	$url   = strtr( $g['url'], array_merge( $rep, array( '{message}' => rawurlencode( $msg ) ) ) );
	$body  = strtr( (string) ( $g['body'] ?? '' ), $rep );
	$hdr   = array();
	foreach ( preg_split( '/\r?\n/', (string) ( $g['headers'] ?? '' ) ) as $l ) { if ( false !== strpos( $l, ':' ) ) { list( $k, $v ) = array_map( 'trim', explode( ':', $l, 2 ) ); $hdr[ $k ] = $v; } }
	$method = 'GET' === strtoupper( $g['method'] ?? 'POST' ) ? 'GET' : 'POST';
	$r = wp_remote_request( $url, array( 'method' => $method, 'timeout' => 15, 'headers' => $hdr, 'body' => 'GET' === $method ? null : $body ) );
	if ( is_wp_error( $r ) ) { return false; }
	$ok = wp_remote_retrieve_response_code( $r );
	if ( $ok < 200 || $ok >= 300 ) { return false; }
	$must = trim( (string) ( $g['ok'] ?? '' ) );
	return '' === $must || false !== stripos( wp_remote_retrieve_body( $r ), $must );
}

/* SMS: প্রথম সচল sms গেটওয়ে (ব্যর্থ হলে পরেরটা); কমপক্ষে একটি থাকলেই ফিল্টার বসে → hitscam_sms_ready() true */
if ( array_filter( hitscam_gateways(), fn( $x ) => 'sms' === ( $x['ch'] ?? 'sms' ) ) ) {
	add_filter( 'hitscam_send_sms', function ( $sent, $phone, $msg ) {
		if ( $sent ) { return $sent; }
		foreach ( hitscam_gateways() as $g ) { if ( 'sms' === ( $g['ch'] ?? 'sms' ) && hitscam_gateway_send( $g, $phone, $msg ) ) { return true; } }
		return false;
	}, 20, 3 );
}
/* অন্য চ্যানেল (WhatsApp/IMO/…) */
add_filter( 'hitscam_send_channel', function ( $sent, $ch, $phone, $msg ) {
	if ( $sent ) { return $sent; }
	foreach ( hitscam_gateways() as $g ) { if ( ( $g['ch'] ?? '' ) === $ch && hitscam_gateway_send( $g, $phone, $msg ) ) { return true; } }
	return false;
}, 20, 4 );

/* নোটিফিকেশন: চ্যানেল গেটওয়ে থাকলে সরাসরি পাঠান (আগের নোট-লিংকের বদলে) */
add_action( 'woocommerce_order_status_changed', function ( $id, $from, $to, $o ) {
	if ( ! in_array( $to, hitscam_notify_statuses(), true ) ) { return; }
	$ch = $o->get_meta( '_hc_notify_ch' );
	if ( ! $ch || 'sms' === $ch ) { return; }
	$msg = 'HitsCam: আপনার অর্ডার #' . $o->get_order_number() . ' এখন "' . wc_get_order_status_name( $to ) . '"।';
	if ( apply_filters( 'hitscam_send_channel', false, $ch, $o->get_billing_phone(), $msg, ( function_exists( 'hitscam_channels' ) ? hitscam_channels()[ $ch ] ?? array() : array() ) ) ) { $GLOBALS['hc_notified'] = $id; $o->add_order_note( 'নোটিফিকেশন পাঠানো হয়েছে (' . $ch . '): ' . $msg ); }
}, 9, 4 );

/* ===== অ্যাডমিন ===== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'গেটওয়ে', 'গেটওয়ে (SMS/WhatsApp/IMO)', 'manage_woocommerce', 'hitscam-gateways', 'hitscam_e10_gw_admin', 26 );
}, 20 );

function hitscam_e10_presets() {
	return array(
		'wa_cloud' => array( 'WhatsApp Cloud API (Meta)', 'whatsapp', 'https://graph.facebook.com/v20.0/PHONE_NUMBER_ID/messages', 'POST', "Authorization: Bearer ACCESS_TOKEN\nContent-Type: application/json", '{"messaging_product":"whatsapp","to":"{phone}","type":"text","text":{"body":"{message_json}"}}', '"messages"' ),
		'sms_get'  => array( 'SMS (GET API, সাধারণ বাংলাদেশি প্রোভাইডার)', 'sms', 'https://YOUR-SMS-API/send?api_key=KEY&senderid=SID&number={phone}&message={message}', 'GET', '', '', '' ),
		'sms_post' => array( 'SMS (POST JSON)', 'sms', 'https://YOUR-SMS-API/send', 'POST', "Content-Type: application/json\nAuthorization: Bearer TOKEN", '{"to":"{phone}","message":"{message_json}"}', '' ),
		'webhook'  => array( 'ওয়েবহুক (Zapier/Make/n8n/নিজস্ব সার্ভার)', 'imo', 'https://hooks.example.com/…', 'POST', 'Content-Type: application/json', '{"phone":"{phone}","message":"{message_json}"}', '' ),
	);
}

function hitscam_e10_gw_row( $i, $g ) {
	$f = fn( $k, $d = '' ) => esc_attr( $g[ $k ] ?? $d );
	echo '<div class="hc-gw" style="background:#fff;border:1px solid #c3c4c7;padding:12px;margin:0 0 12px;border-radius:6px"><p style="margin:0 0 8px"><label><input type="checkbox" name="gw[' . esc_attr( $i ) . '][on]" value="1"' . checked( ! empty( $g['on'] ), true, false ) . '> সচল</label> &nbsp; ';
	echo 'নাম <input name="gw[' . esc_attr( $i ) . '][name]" value="' . $f( 'name' ) . '" placeholder="যেমন: Meta WhatsApp"> &nbsp; চ্যানেল কী <input name="gw[' . esc_attr( $i ) . '][ch]" value="' . $f( 'ch', 'sms' ) . '" size="10" placeholder="sms / whatsapp / imo"> &nbsp; মেথড <select name="gw[' . esc_attr( $i ) . '][method]"><option>POST</option><option' . selected( $g['method'] ?? '', 'GET', false ) . '>GET</option></select> <a href="#" class="hc-gw-del" style="color:#b32d2e;float:right">মুছুন ✕</a></p>';
	echo '<p style="margin:0 0 8px">URL<br><input class="large-text code" name="gw[' . esc_attr( $i ) . '][url]" value="' . $f( 'url' ) . '"></p>';
	echo '<p style="margin:0 0 8px">হেডার (প্রতি লাইনে <code>নাম: মান</code>)<br><textarea class="large-text code" rows="2" name="gw[' . esc_attr( $i ) . '][headers]">' . esc_textarea( $g['headers'] ?? '' ) . '</textarea></p>';
	echo '<p style="margin:0 0 8px">বডি টেমপ্লেট (POST)<br><textarea class="large-text code" rows="3" name="gw[' . esc_attr( $i ) . '][body]">' . esc_textarea( $g['body'] ?? '' ) . '</textarea></p>';
	echo '<p style="margin:0">সফল ধরার শর্ত (রেসপন্সে এই লেখা থাকলে; ফাঁকা = শুধু HTTP 2xx) <input name="gw[' . esc_attr( $i ) . '][ok]" value="' . $f( 'ok' ) . '"></p></div>';
}

function hitscam_e10_gw_admin() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	if ( isset( $_POST['hc_gw_save'] ) && check_admin_referer( 'hc_gw' ) ) {
		$out = array();
		foreach ( (array) ( $_POST['gw'] ?? array() ) as $r ) {
			$url = esc_url_raw( wp_unslash( $r['url'] ?? '' ) );
			if ( ! $url || 0 !== strpos( $url, 'http' ) ) { continue; }
			$out[] = array(
				'on' => ! empty( $r['on'] ), 'name' => sanitize_text_field( wp_unslash( $r['name'] ?? '' ) ), 'ch' => sanitize_key( $r['ch'] ?? 'sms' ) ?: 'sms',
				'method' => 'GET' === ( $r['method'] ?? '' ) ? 'GET' : 'POST', 'url' => $url,
				'headers' => sanitize_textarea_field( wp_unslash( $r['headers'] ?? '' ) ), 'body' => trim( (string) wp_unslash( $r['body'] ?? '' ) ), 'ok' => sanitize_text_field( wp_unslash( $r['ok'] ?? '' ) ),
			);
		}
		update_option( 'hitscam_gateways', $out );
		echo '<div class="notice notice-success"><p>সংরক্ষিত। (SMS গেটওয়ে যোগ/বাদ হলে পেজ রিলোডের পর কার্যকর)</p></div>';
	}
	if ( isset( $_POST['hc_gw_test'] ) && check_admin_referer( 'hc_gw' ) ) {
		$i = (int) $_POST['hc_gw_test']; $all = array_values( hitscam_gateways( false ) ); $ph = sanitize_text_field( wp_unslash( $_POST['test_phone'] ?? '' ) );
		$ok = isset( $all[ $i ] ) && $ph && hitscam_gateway_send( $all[ $i ], $ph, 'HitsCam টেস্ট মেসেজ ✓' );
		echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . '"><p>' . ( $ok ? 'টেস্ট পাঠানো হয়েছে।' : 'টেস্ট ব্যর্থ — URL/হেডার/বডি ও "সফল শর্ত" দেখুন।' ) . '</p></div>';
	}
	$gws = array_values( hitscam_gateways( false ) );
	echo '<div class="wrap"><h1>গেটওয়ে (SMS / WhatsApp / IMO / ওয়েবহুক)</h1><p>যেকোনো সংখ্যক গেটওয়ে যোগ করুন। <b>চ্যানেল কী = sms</b> হলে সেটা SMS গেটওয়ে (OTP, রিকনফার্ম, আপডেট; একাধিক হলে ক্রমে চেষ্টা)। অন্য কী হলে "চ্যানেল ও ভেরিফিকেশন" পেজের ওই কী-র চ্যানেলে সরাসরি পাঠানো হয় (গেটওয়ে না থাকলে আগের ম্যানুয়াল লিংক)। প্লেসহোল্ডার: <code>{phone}</code> <code>{phone_local}</code> <code>{message}</code> <code>{message_json}</code>।</p>';
	echo '<form method="post">'; wp_nonce_field( 'hc_gw' ); echo '<div id="hcGwList">';
	foreach ( $gws as $i => $g ) { hitscam_e10_gw_row( $i, $g ); }
	echo '</div><p><select id="hcGwPreset"><option value="">— প্রিসেট বেছে যোগ করুন —</option>';
	foreach ( hitscam_e10_presets() as $k => $p ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $p[0] ) . '</option>'; }
	echo '</select> <button type="button" class="button" id="hcGwAdd">+ গেটওয়ে যোগ</button></p>';
	echo '<p><button class="button button-primary" name="hc_gw_save" value="1">সংরক্ষণ</button></p>';
	if ( $gws ) {
		echo '<hr><h2>টেস্ট</h2><p>ফোন <input name="test_phone" placeholder="01XXXXXXXXX"> ';
		foreach ( $gws as $i => $g ) { echo '<button class="button" name="hc_gw_test" value="' . esc_attr( $i ) . '">' . esc_html( ( $g['name'] ?: $g['ch'] ) ) . ' টেস্ট</button> '; }
		echo '</p>';
	}
	echo '</form></div>';
	ob_start(); hitscam_e10_gw_row( '__I__', array( 'on' => 1 ) ); $tpl = ob_get_clean();
	$pre = array(); foreach ( hitscam_e10_presets() as $k => $p ) { $pre[ $k ] = array( 'name' => $p[0], 'ch' => $p[1], 'url' => $p[2], 'method' => $p[3], 'headers' => $p[4], 'body' => $p[5], 'ok' => $p[6] ); }
	?>
	<script>
	(function(){var n=<?php echo (int) count( $gws ); ?>,tpl=<?php echo wp_json_encode( $tpl ); ?>,pre=<?php echo wp_json_encode( $pre ); ?>,L=document.getElementById('hcGwList');
	document.getElementById('hcGwAdd').onclick=function(){var d=document.createElement('div');d.innerHTML=tpl.split('__I__').join(n++);var el=d.firstChild,k=document.getElementById('hcGwPreset').value;
	if(k&&pre[k]){var p=pre[k];['name','ch','url','headers','body','ok'].forEach(function(f){var i=el.querySelector('[name$="['+f+']"]');if(i)i.value=p[f]});el.querySelector('select').value=p.method;}
	L.appendChild(el);};
	L.addEventListener('click',function(e){if(e.target.classList.contains('hc-gw-del')){e.preventDefault();e.target.closest('.hc-gw').remove();}});})();
	</script>
	<?php
}
