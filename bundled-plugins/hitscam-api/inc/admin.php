<?php
/**
 * অ্যাডমিন: WooCommerce → HitsCam API (Keys / Webhooks / Docs / Deliveries)
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'HitsCam API', 'HitsCam API', 'manage_woocommerce', 'hitscam-api', 'hcapi_admin_page' );
} );

function hcapi_admin_url( $tab = 'keys', $extra = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'hitscam-api', 'tab' => $tab ), $extra ), admin_url( 'admin.php' ) );
}

add_action( 'admin_post_hcapi_save_key', function () {
	check_admin_referer( 'hcapi' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'অনুমতি নেই' ); }
	$res = hcapi_create_key( wp_unslash( $_POST['name'] ?? 'Key' ), (array) ( $_POST['scopes'] ?? array() ), (int) ( $_POST['rate'] ?? 120 ) );
	set_transient( 'hcapi_new_key_' . get_current_user_id(), $res, 120 ); // একবার দেখানোর জন্য
	wp_safe_redirect( hcapi_admin_url( 'keys' ) );
	exit;
} );
add_action( 'admin_post_hcapi_revoke_key', function () {
	check_admin_referer( 'hcapi' );
	if ( current_user_can( 'manage_woocommerce' ) ) { hcapi_revoke_key( sanitize_key( $_GET['id'] ?? '' ) ); }
	wp_safe_redirect( hcapi_admin_url( 'keys' ) );
	exit;
} );
add_action( 'admin_post_hcapi_save_webhook', function () {
	check_admin_referer( 'hcapi' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'অনুমতি নেই' ); }
	$r = hcapi_save_webhook( wp_unslash( $_POST ) );
	set_transient( 'hcapi_wh_msg_' . get_current_user_id(), is_wp_error( $r ) ? array( 'err', $r->get_error_message() ) : array( 'ok', 'ওয়েবহুক সেভ হয়েছে। Secret: ' . $r['secret'] ), 60 );
	wp_safe_redirect( hcapi_admin_url( 'webhooks' ) );
	exit;
} );
add_action( 'admin_post_hcapi_delete_webhook', function () {
	check_admin_referer( 'hcapi' );
	if ( current_user_can( 'manage_woocommerce' ) ) { hcapi_delete_webhook( sanitize_key( $_GET['id'] ?? '' ) ); }
	wp_safe_redirect( hcapi_admin_url( 'webhooks' ) );
	exit;
} );
add_action( 'admin_post_hcapi_test_webhook', function () {
	check_admin_referer( 'hcapi' );
	if ( current_user_can( 'manage_woocommerce' ) ) {
		$id = sanitize_key( $_GET['id'] ?? '' );
		$did = wp_generate_uuid4();
		set_transient( 'hcapi_pl_' . $did, array( 'event' => 'ping', 'data' => array( 'message' => 'test' ), 'created' => gmdate( 'c' ) ), HOUR_IN_SECONDS );
		hcapi_deliver( $id, $did, 9 ); // সরাসরি, রিট্রাই নয়
	}
	wp_safe_redirect( hcapi_admin_url( 'deliveries' ) );
	exit;
} );

function hcapi_admin_page() {
	$tab  = sanitize_key( $_GET['tab'] ?? 'keys' );
	$tabs = array( 'keys' => 'API Keys', 'webhooks' => 'Webhooks', 'docs' => 'Docs / Examples', 'deliveries' => 'Delivery Log' );
	echo '<div class="wrap"><h1>HitsCam API <small style="font-size:12px;color:#888">v' . esc_html( HCAPI_VERSION ) . '</small></h1><nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $l ) {
		printf( '<a class="nav-tab %s" href="%s">%s</a>', $tab === $k ? 'nav-tab-active' : '', esc_url( hcapi_admin_url( $k ) ), esc_html( $l ) );
	}
	echo '</nav><div style="margin-top:16px;max-width:1000px">';
	call_user_func( 'hcapi_tab_' . ( isset( $tabs[ $tab ] ) ? $tab : 'keys' ) );
	echo '</div></div>';
}

function hcapi_tab_keys() {
	$new = get_transient( 'hcapi_new_key_' . get_current_user_id() );
	if ( $new ) {
		delete_transient( 'hcapi_new_key_' . get_current_user_id() );
		echo '<div class="notice notice-success inline"><p><strong>নতুন API key (এখনই কপি করুন — আর দেখানো হবে না):</strong><br><code style="font-size:14px;user-select:all">' . esc_html( $new['key'] ) . '</code></p></div>';
	}
	echo '<h2>নতুন key</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcapi_save_key">';
	wp_nonce_field( 'hcapi' );
	echo '<p><label>নাম <input name="name" required placeholder="যেমন: ERP, Courier" class="regular-text"></label> <label>রেট-লিমিট/মিনিট <input type="number" name="rate" value="120" min="10" style="width:80px"></label></p><p>';
	foreach ( hcapi_scopes() as $s => $l ) {
		printf( '<label style="display:inline-block;width:320px"><input type="checkbox" name="scopes[]" value="%s" %s> <code>%s</code> — %s</label>', esc_attr( $s ), checked( 0 === strpos( $s, 'products:read' ) || 0 === strpos( $s, 'orders:read' ), true, false ), esc_html( $s ), esc_html( $l ) );
	}
	echo '</p><p><button class="button button-primary">key তৈরি করুন</button></p></form><h2>বিদ্যমান key</h2><table class="widefat striped"><thead><tr><th>নাম</th><th>Prefix</th><th>স্কোপ</th><th>লিমিট</th><th>শেষ ব্যবহার</th><th></th></tr></thead><tbody>';
	$keys = hcapi_get_keys();
	if ( ! $keys ) { echo '<tr><td colspan="6">কোনো key নেই।</td></tr>'; }
	foreach ( $keys as $k ) {
		$rev = wp_nonce_url( admin_url( 'admin-post.php?action=hcapi_revoke_key&id=' . $k['id'] ), 'hcapi' );
		printf( '<tr><td>%s</td><td><code>%s…</code></td><td>%s</td><td>%d</td><td>%s</td><td><a href="%s" onclick="return confirm(\'বাতিল করবেন?\')">বাতিল</a></td></tr>', esc_html( $k['name'] ), esc_html( $k['prefix'] ), esc_html( implode( ', ', $k['scopes'] ) ), (int) $k['rate'], $k['used'] ? esc_html( human_time_diff( $k['used'] ) . ' আগে' ) : '—', esc_url( $rev ) );
	}
	echo '</tbody></table><p class="description">key হ্যাশ করে রাখা হয়; হারালে নতুন তৈরি করুন। নিরাপত্তার জন্য শুধু HTTPS সাইটে ব্যবহার করুন।</p>';
}

function hcapi_tab_webhooks() {
	if ( $m = get_transient( 'hcapi_wh_msg_' . get_current_user_id() ) ) {
		delete_transient( 'hcapi_wh_msg_' . get_current_user_id() );
		printf( '<div class="notice notice-%s inline"><p>%s</p></div>', 'ok' === $m[0] ? 'success' : 'error', esc_html( $m[1] ) );
	}
	echo '<h2>নতুন ওয়েবহুক</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcapi_save_webhook">';
	wp_nonce_field( 'hcapi' );
	echo '<p><input name="name" placeholder="নাম" class="regular-text"> <input name="url" type="url" required placeholder="https://example.com/hook" class="regular-text" style="width:340px"></p><p>';
	foreach ( hcapi_webhook_events() as $e => $l ) {
		printf( '<label style="display:inline-block;width:300px"><input type="checkbox" name="events[]" value="%s"> <code>%s</code> — %s</label>', esc_attr( $e ), esc_html( $e ), esc_html( $l ) );
	}
	echo '</p><p><button class="button button-primary">সেভ</button> <span class="description">Secret ফাঁকা রাখলে অটো-জেনারেট হবে।</span></p></form><h2>তালিকা</h2><table class="widefat striped"><thead><tr><th>নাম</th><th>URL</th><th>ইভেন্ট</th><th></th></tr></thead><tbody>';
	$all = hcapi_get_webhooks();
	if ( ! $all ) { echo '<tr><td colspan="4">কোনো ওয়েবহুক নেই।</td></tr>'; }
	foreach ( $all as $w ) {
		$del  = wp_nonce_url( admin_url( 'admin-post.php?action=hcapi_delete_webhook&id=' . $w['id'] ), 'hcapi' );
		$test = wp_nonce_url( admin_url( 'admin-post.php?action=hcapi_test_webhook&id=' . $w['id'] ), 'hcapi' );
		printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">টেস্ট পিং</a> | <a href="%s" onclick="return confirm(\'ডিলিট?\')">ডিলিট</a></td></tr>', esc_html( $w['name'] ), esc_html( $w['url'] ), esc_html( implode( ', ', $w['events'] ) ), esc_url( $test ), esc_url( $del ) );
	}
	echo '</tbody></table>';
}

function hcapi_tab_docs() {
	$base = untrailingslashit( rest_url( 'hitscam/v1' ) );
	echo '<p>Base URL: <code>' . esc_html( $base ) . '</code> — OpenAPI: <a href="' . esc_url( $base . '/openapi.json' ) . '" target="_blank">openapi.json</a> (Postman/Swagger-এ ইমপোর্ট করুন)</p>';
	echo '<h3>Authentication</h3><pre>Authorization: Bearer hc_xxxxxxxx
# অথবা
X-HitsCam-Key: hc_xxxxxxxx</pre>';
	echo '<h3>উদাহরণ</h3><pre>curl -H "Authorization: Bearer $KEY" "' . esc_html( $base ) . '/products?per_page=5&amp;search=camera&amp;orderby=price&amp;order=asc"
curl -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d \'{"name":"New Cam","regular_price":"4500","sku":"NC-1","stock_quantity":10,"manage_stock":true,"categories":["Camera"]}\' \
  ' . esc_html( $base ) . '/products
curl -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d \'{"quantity":25,"operation":"set"}\' ' . esc_html( $base ) . '/products/123/stock
curl -X POST -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d \'{"line_items":[{"product_id":123,"quantity":2}],"billing":{"first_name":"রহিম","phone":"01712345678","address_1":"মিরপুর","city":"ঢাকা","state":"BD-13","country":"BD"},"payment_method":"cod","status":"processing"}\' ' . esc_html( $base ) . '/orders
curl -X PUT -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" -d \'{"status":"completed"}\' ' . esc_html( $base ) . '/orders/456</pre>';
	echo '<h3>ওয়েবহুক যাচাই (PHP)</h3><pre>$ts  = $_SERVER["HTTP_X_HITSCAM_TIMESTAMP"];
$sig = $_SERVER["HTTP_X_HITSCAM_SIGNATURE"];
$body = file_get_contents("php://input");
$ok = hash_equals("sha256=".hash_hmac("sha256", $ts.".".$body, $SECRET), $sig) &amp;&amp; abs(time()-$ts) &lt; 300;</pre>';
	echo '<h3>Endpoints ও স্কোপ</h3><table class="widefat striped"><tbody>';
	foreach ( hcapi_openapi()['paths'] as $p => $ms ) {
		foreach ( $ms as $m => $o ) {
			printf( '<tr><td><code>%s</code></td><td><code>%s</code></td><td>%s</td></tr>', esc_html( strtoupper( $m ) ), esc_html( $p ), esc_html( $o['summary'] ) );
		}
	}
	echo '</tbody></table><p>ওয়েবহুক ইভেন্ট: ';
	echo esc_html( implode( ', ', array_keys( hcapi_webhook_events() ) ) ) . '। ব্যর্থ হলে ৩ বার রিট্রাই (১ মি, ৫ মি, ৩০ মি) — WP-Cron নির্ভর।</p>';
}

function hcapi_tab_deliveries() {
	echo '<table class="widefat striped"><thead><tr><th>সময়</th><th>Webhook</th><th>ইভেন্ট</th><th>চেষ্টা</th><th>কোড</th><th>ফলাফল</th></tr></thead><tbody>';
	$log = get_option( 'hcapi_deliveries', array() );
	if ( ! $log ) { echo '<tr><td colspan="6">কিছু নেই।</td></tr>'; }
	foreach ( $log as $l ) {
		printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%s</td></tr>', esc_html( wp_date( 'd M H:i:s', $l['time'] ) ), esc_html( $l['webhook'] ), esc_html( $l['event'] ), (int) $l['attempt'], (int) $l['code'], $l['ok'] ? '✓' : '✗ ' . esc_html( $l['error'] ) );
	}
	echo '</tbody></table>';
}
