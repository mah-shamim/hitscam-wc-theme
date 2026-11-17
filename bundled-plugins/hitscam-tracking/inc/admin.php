<?php
/**
 * অ্যাডমিন: HitsCam Tracking — ট্যাব: Meta / Google Tags / GTM / Hooks / Logs / Help
 */
defined( 'ABSPATH' ) || exit;

function hctrk_tabs() {
	return array(
		'meta'   => 'Meta Settings',
		'google' => 'Google Tags Settings',
		'gtm'    => 'GTM Tag Settings',
		'hooks'  => 'Filter & Hook List',
		'logs'   => 'Logs',
		'help'   => 'HELP',
	);
}

add_action( 'admin_menu', function () {
	add_menu_page( 'HitsCam Tracking', 'HitsCam Tracking', 'manage_options', 'hitscam-tracking', 'hctrk_admin_page', 'dashicons-chart-area', 58 );
} );

add_action( 'admin_post_hctrk_save', function () {
	check_admin_referer( 'hctrk_save' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	$group = sanitize_key( $_POST['group'] ?? '' );
	if ( in_array( $group, array( 'meta', 'google', 'gtm', 'general' ), true ) ) {
		$cur           = array_replace_recursive( hctrk_defaults(), (array) get_option( 'hctrk', array() ) );
		$cur[ $group ] = wp_unslash( $_POST[ $group ] ?? array() );
		update_option( 'hctrk', hctrk_sanitize( $cur ) );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'hitscam-tracking', 'tab' => sanitize_key( $_POST['tab'] ?? 'meta' ), 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );
add_action( 'admin_post_hctrk_clear_logs', function () {
	check_admin_referer( 'hctrk_logs' );
	if ( current_user_can( 'manage_options' ) ) { delete_option( 'hctrk_logs' ); }
	wp_safe_redirect( add_query_arg( array( 'page' => 'hitscam-tracking', 'tab' => 'logs' ), admin_url( 'admin.php' ) ) );
	exit;
} );
add_action( 'admin_post_hctrk_test_capi', function () {
	check_admin_referer( 'hctrk_test' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	global $hctrk_capi_buf;
	$m = hctrk_opt( 'meta' );
	if ( $m['pixel_id'] && $m['token'] ) {
		$hctrk_capi_buf = array( array( 'event_name' => 'PageView', 'event_time' => time(), 'event_id' => 'test_' . time(), 'event_source_url' => home_url( '/' ), 'action_source' => 'website', 'user_data' => array( 'client_ip_address' => hctrk_client_ip(), 'client_user_agent' => 'HitsCam-Test' ), 'custom_data' => new stdClass() ) );
		add_filter( 'hitscam_tracking_force_log_ok', '__return_true' );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'hitscam-tracking', 'tab' => 'logs', 'tested' => 1 ), admin_url( 'admin.php' ) ) );
	exit; // shutdown হুক CAPI পাঠাবে
} );

function hctrk_chk( $name, $val, $label ) {
	printf( '<label style="display:block;margin:3px 0"><input type="checkbox" name="%s" value="1" %s> %s</label>', esc_attr( $name ), checked( ! empty( $val ), true, false ), esc_html( $label ) );
}
function hctrk_txt( $name, $val, $label, $ph = '', $type = 'text', $desc = '' ) {
	printf( '<tr><th scope="row"><label>%s</label></th><td><input type="%s" class="regular-text" name="%s" value="%s" placeholder="%s" autocomplete="off">%s</td></tr>', esc_html( $label ), esc_attr( $type ), esc_attr( $name ), esc_attr( $val ), esc_attr( $ph ), $desc ? '<p class="description">' . wp_kses_post( $desc ) . '</p>' : '' );
}
function hctrk_form_open( $group, $tab ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hctrk_save"><input type="hidden" name="group" value="' . esc_attr( $group ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'hctrk_save' );
}

function hctrk_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$tab  = sanitize_key( $_GET['tab'] ?? 'meta' );
	$tabs = hctrk_tabs();
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'meta';
	$o    = hctrk_opts();
	echo '<div class="wrap"><h1>HitsCam Tracking <small style="font-size:12px;color:#666">v' . esc_html( HCTRK_VERSION ) . '</small></h1>';
	if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="notice notice-error"><p>WooCommerce সক্রিয় নেই — ই-কমার্স ইভেন্ট কাজ করবে না।</p></div>'; }
	if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>সেভ হয়েছে।</p></div>'; }
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $l ) {
		printf( '<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url( add_query_arg( array( 'page' => 'hitscam-tracking', 'tab' => $k ), admin_url( 'admin.php' ) ) ), esc_html( $l ) );
	}
	echo '</nav><div style="max-width:900px;margin-top:16px">';
	$fn = 'hctrk_tab_' . $tab;
	$fn( $o );
	echo '</div></div>';
}

function hctrk_tab_meta( $o ) {
	$m = $o['meta'];
	hctrk_form_open( 'meta', 'meta' );
	echo '<h2>Meta Pixel + Conversions API</h2><table class="form-table">';
	echo '<tr><th>চালু</th><td>'; hctrk_chk( 'meta[enabled]', $m['enabled'], 'Meta Pixel সক্রিয় করুন' ); echo '</td></tr>';
	hctrk_txt( 'meta[pixel_id]', $m['pixel_id'], 'Pixel ID', '123456789012345', 'text', 'একাধিক হলে কমা দিয়ে লিখুন।' );
	echo '<tr><th>Conversions API</th><td>'; hctrk_chk( 'meta[capi]', $m['capi'], 'সার্ভার-সাইড ইভেন্ট পাঠান (event_id দিয়ে ডিডুপ্লিকেট)' ); echo '</td></tr>';
	hctrk_txt( 'meta[token]', $m['token'], 'Access Token', 'EAAB…', 'password', 'Events Manager → Settings → Conversions API → Generate access token।' );
	hctrk_txt( 'meta[test_code]', $m['test_code'], 'Test Event Code', 'TEST12345', 'text', 'টেস্টিংয়ের সময় দিন; লাইভে খালি রাখুন।' );
	echo '<tr><th>Advanced Matching</th><td>'; hctrk_chk( 'meta[adv_match]', $m['adv_match'], 'লগইন/অর্ডার ডেটা (ইমেইল, ফোন, নাম) হ্যাশ করে ম্যাচিংয়ে ব্যবহার' ); echo '</td></tr>';
	echo '<tr><th>Content ID</th><td><select name="meta[content_id]"><option value="id" ' . selected( $m['content_id'], 'id', false ) . '>Product ID</option><option value="sku" ' . selected( $m['content_id'], 'sku', false ) . '>SKU (খালি হলে ID)</option></select><p class="description">ক্যাটালগ ফিডের id-র সাথে মিলতে হবে।</p></td></tr>';
	echo '<tr><th>ইভেন্ট</th><td>';
	$lbl = array( 'pageview' => 'PageView', 'viewcontent' => 'ViewContent', 'viewcategory' => 'ViewCategory (কাস্টম)', 'search' => 'Search', 'addtocart' => 'AddToCart', 'initiatecheckout' => 'InitiateCheckout', 'addpaymentinfo' => 'AddPaymentInfo', 'purchase' => 'Purchase' );
	foreach ( $lbl as $k => $l ) { hctrk_chk( "meta[events][$k]", $m['events'][ $k ] ?? 0, $l ); }
	echo '</td></tr></table>';
	submit_button( 'সেভ করুন' );
	echo '</form>';
	if ( $m['capi'] && $m['token'] && $m['pixel_id'] ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hctrk_test_capi">';
		wp_nonce_field( 'hctrk_test' );
		submit_button( 'CAPI টেস্ট ইভেন্ট পাঠান', 'secondary' );
		echo '<p class="description">ফলাফল Logs ট্যাবে দেখুন (লগিং চালু থাকতে হবে)।</p></form>';
	}
}

function hctrk_tab_google( $o ) {
	$g = $o['google'];
	hctrk_form_open( 'google', 'google' );
	echo '<h2>Google Tag (GA4 / Google Ads)</h2><table class="form-table">';
	echo '<tr><th>চালু</th><td>'; hctrk_chk( 'google[enabled]', $g['enabled'], 'Google Tag সক্রিয় করুন' ); echo '</td></tr>';
	hctrk_txt( 'google[ga4_id]', $g['ga4_id'], 'GA4 Measurement ID', 'G-XXXXXXXXXX' );
	hctrk_txt( 'google[ads_id]', $g['ads_id'], 'Google Ads ID', 'AW-123456789' );
	hctrk_txt( 'google[ads_label]', $g['ads_label'], 'Ads Purchase Conversion Label', 'AbC-D_efG-h12', 'text', 'দিলে Purchase-এ conversion ইভেন্ট যাবে।' );
	echo '<tr><th>আইপি</th><td>'; hctrk_chk( 'google[anonymize]', $g['anonymize'], 'IP anonymize' ); echo '</td></tr>';
	echo '<tr><th>ইভেন্ট</th><td>';
	foreach ( $g['events'] as $k => $v ) { hctrk_chk( "google[events][$k]", $v, $k ); }
	echo '</td></tr></table>';
	submit_button( 'সেভ করুন' );
	echo '</form>';
}

function hctrk_tab_gtm( $o ) {
	$x = $o['gtm'];
	hctrk_form_open( 'gtm', 'gtm' );
	echo '<h2>Google Tag Manager</h2><table class="form-table">';
	echo '<tr><th>চালু</th><td>'; hctrk_chk( 'gtm[enabled]', $x['enabled'], 'GTM সক্রিয় করুন (GA4 ইকমার্স ডেটা dataLayer-এ যাবে)' ); echo '</td></tr>';
	hctrk_txt( 'gtm[container_id]', $x['container_id'], 'Container ID', 'GTM-XXXXXXX' );
	hctrk_txt( 'gtm[dl_name]', $x['dl_name'], 'dataLayer নাম', 'dataLayer' );
	echo '<tr><th>ডাবল-কাউন্ট</th><td>'; hctrk_chk( 'gtm[suppress_gtag]', $x['suppress_gtag'], 'GTM চালু থাকলে সরাসরি gtag.js বন্ধ রাখুন (GA4 ট্যাগ GTM-এ কনফিগার করুন)' ); echo '</td></tr>';
	echo '</table><p>GTM-এ Custom Event ট্রিগার বানান: <code>view_item, view_item_list, search, add_to_cart, begin_checkout, add_payment_info, purchase</code>। প্রতিটি পুশে <code>event_id</code> থাকে (Meta CAPI ডিডুপের জন্য)।</p>';
	submit_button( 'সেভ করুন' );
	echo '</form><hr>';
	hctrk_form_open( 'general', 'gtm' );
	$n = $o['general'];
	echo '<h2>সাধারণ সেটিংস (সব প্ল্যাটফর্ম)</h2><table class="form-table"><tr><th>বাদ দেওয়া রোল</th><td>';
	foreach ( wp_roles()->get_names() as $slug => $name ) { hctrk_chk( 'general[exclude_roles][]" value="' . esc_attr( $slug ) . '" data-x="', in_array( $slug, $n['exclude_roles'], true ), translate_user_role( $name ) ); }
	echo '</td></tr><tr><th>কনসেন্ট</th><td>';
	hctrk_chk( 'general[require_consent]', $n['require_consent'], 'কনসেন্ট কুকি না থাকলে কোনো স্ক্রিপ্ট/CAPI চালাবেন না' );
	echo '</td></tr>';
	hctrk_txt( 'general[consent_cookie]', $n['consent_cookie'], 'কনসেন্ট কুকির নাম', 'hc_consent', 'text', 'মান 1/true/all/granted/marketing/accepted হলে সম্মতি ধরা হয়। অন্য কুকি-ব্যানার প্লাগিনের নাম দিন অথবা <code>hitscam_tracking_has_consent</code> ফিল্টার ব্যবহার করুন।' );
	echo '<tr><th>Google Consent Mode</th><td>'; hctrk_chk( 'general[consent_mode]', $n['consent_mode'], 'gtag consent granted সিগন্যাল পাঠান' ); echo '</td></tr>';
	echo '<tr><th>Purchase</th><td>'; hctrk_chk( 'general[purchase_once]', $n['purchase_once'], 'প্রতিটি অর্ডারে Purchase একবারই (রিলোডে ডুপ্লিকেট নয়)' ); echo '</td></tr>';
	echo '<tr><th>লগিং</th><td>'; hctrk_chk( 'general[logging]', $n['logging'], 'এরর লগ রাখুন' ); hctrk_chk( 'general[log_ok]', $n['log_ok'], 'সফল ইভেন্টও লগ করুন (ডিবাগ)' ); echo '</td></tr></table>';
	submit_button( 'সেভ করুন' );
	echo '</form>';
}

function hctrk_tab_hooks( $o ) {
	$rows = array(
		array( 'filter', 'hitscam_tracking_skip', '$skip (bool)', 'true দিলে এই রিকোয়েস্টে ট্র্যাকিং বন্ধ।' ),
		array( 'filter', 'hitscam_tracking_has_consent', '$ok (bool)', 'আপনার কুকি-ব্যানারের সাথে কনসেন্ট যুক্ত করুন।' ),
		array( 'filter', 'hitscam_tracking_config', '$cfg (array)', 'ব্রাউজারে যাওয়া সম্পূর্ণ কনফিগ/ইভেন্ট কিউ পরিবর্তন।' ),
		array( 'filter', 'hitscam_tracking_event', '$event, $key', 'প্রতিটি ইভেন্ট পাঠানোর আগে বদলান; null দিলে বাদ।' ),
		array( 'filter', 'hitscam_tracking_item', '$item, WC_Product', 'আইটেমের id/name/price/category পরিবর্তন।' ),
		array( 'filter', 'hitscam_tracking_capi_payload', '$events (array)', 'Meta CAPI-তে যাওয়ার আগে payload পরিবর্তন।' ),
		array( 'JS event', 'hitscam:consent', 'document.dispatchEvent(new Event("hitscam:consent"))', 'কনসেন্ট দেওয়ার পর স্ক্রিপ্ট চালু করতে।' ),
		array( 'JS fn', 'hitscamTrackingConsent()', '—', 'একই কাজ ফাংশন কলে।' ),
		array( 'cookie', 'hc_consent', '1|granted|all…', 'ডিফল্ট কনসেন্ট কুকি (নাম সেটিংসে বদলানো যায়)।' ),
		array( 'dataLayer', 'view_item, view_item_list, search, add_to_cart, begin_checkout, add_payment_info, purchase', 'GA4 ecommerce স্কিমা + event_id', 'GTM ট্রিগার।' ),
		array( 'option', 'hctrk', 'array', 'সব সেটিংস (get_option)।' ),
	);
	echo '<h2>Filter &amp; Hook তালিকা</h2><table class="widefat striped"><thead><tr><th>ধরন</th><th>নাম</th><th>আর্গুমেন্ট</th><th>কাজ</th></tr></thead><tbody>';
	foreach ( $rows as $r ) { echo '<tr><td>' . esc_html( $r[0] ) . '</td><td><code>' . esc_html( $r[1] ) . '</code></td><td><code>' . esc_html( $r[2] ) . '</code></td><td>' . esc_html( $r[3] ) . '</td></tr>'; }
	echo '</tbody></table><h3>উদাহরণ</h3><pre style="background:#f6f7f7;padding:10px">add_filter( \'hitscam_tracking_skip\', fn( $s ) =&gt; $s || is_page( \'thank-you-test\' ) );' . "\n" . 'add_filter( \'hitscam_tracking_has_consent\', fn( $ok ) =&gt; function_exists( \'cky_consent\' ) ? cky_consent( \'advertisement\' ) : $ok );</pre>';
}

function hctrk_tab_logs( $o ) {
	$logs = (array) get_option( 'hctrk_logs', array() );
	if ( isset( $_GET['tested'] ) ) { echo '<div class="notice notice-info"><p>টেস্ট পাঠানো হয়েছে; ফলাফল নিচে (রিফ্রেশ করুন)।</p></div>'; }
	echo '<h2>Logs <small>(শেষ ' . count( $logs ) . 'টি)</small></h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hctrk_clear_logs">';
	wp_nonce_field( 'hctrk_logs' );
	submit_button( 'লগ মুছুন', 'secondary', '', false );
	echo '</form><table class="widefat striped" style="margin-top:10px"><thead><tr><th>সময়</th><th>ধরন</th><th>প্ল্যাটফর্ম</th><th>ইভেন্ট</th><th>অবস্থা</th><th>বার্তা</th></tr></thead><tbody>';
	if ( ! $logs ) { echo '<tr><td colspan="6">কোনো লগ নেই।</td></tr>'; }
	foreach ( $logs as $l ) {
		printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><b style="color:%s">%s</b></td><td>%s</td></tr>', esc_html( wp_date( 'd M H:i:s', $l['t'] ) ), esc_html( $l['type'] ), esc_html( $l['p'] ), esc_html( $l['e'] ), 'ok' === $l['s'] ? '#1a7f37' : '#b32d2e', esc_html( $l['s'] ), esc_html( $l['m'] ) );
	}
	echo '</tbody></table>';
}

function hctrk_tab_help( $o ) {
	?>
	<h2>HELP</h2>
	<ol>
		<li><b>Meta:</b> Events Manager → Pixel ID কপি; CAPI চাইলে Access Token বানান। টেস্ট কোড দিয়ে Test Events ট্যাবে দেখুন, তারপর টেস্ট কোড মুছে দিন।</li>
		<li><b>Google:</b> GA4 → Admin → Data Streams → Measurement ID (<code>G-…</code>)। Ads হলে Conversion ID/Label।</li>
		<li><b>GTM:</b> Container ID দিন; GA4 Event ট্যাগ বানিয়ে ট্রিগার দিন Custom Event: <code>purchase</code> ইত্যাদি; ecommerce ডেটা dataLayer থেকে নিন (Send Ecommerce data → Data Layer)।</li>
		<li><b>ডুপ্লিকেট এড়ানো:</b> GTM চালু থাকলে gtag.js ডিফল্টে বন্ধ। Meta Pixel ও CAPI একই <code>event_id</code> পাঠায় — Events Manager-এ “Deduplicated” দেখাবে।</li>
		<li><b>কনসেন্ট:</b> কনসেন্ট বাধ্যতামূলক করলে কুকি না আসা পর্যন্ত কিছুই লোড হয় না; কুকি-ব্যানার থেকে <code>document.dispatchEvent(new Event('hitscam:consent'))</code> দিন।</li>
		<li><b>ডিবাগ:</b> <code>WP_DEBUG</code> চালু রেখে অ্যাডমিন হিসেবে (রোল বাদ না দিলে) ব্রাউজার কনসোলে <code>[HCTRK]</code> দেখুন।</li>
		<li>অফিসিয়াল ডকুমেন্টেশন: <a href="https://developers.facebook.com/docs/marketing-api/conversions-api" target="_blank" rel="noopener">Meta CAPI</a> · <a href="https://developers.google.com/analytics/devguides/collection/ga4/ecommerce" target="_blank" rel="noopener">GA4 Ecommerce</a> · <a href="https://support.google.com/tagmanager" target="_blank" rel="noopener">GTM</a></li>
	</ol>
	<?php
}
