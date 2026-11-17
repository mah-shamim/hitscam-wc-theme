<?php
defined( 'ABSPATH' ) || exit;

function hcseo_tabs() {
	return array( 'overview' => 'ওভারভিউ', 'titles' => 'টাইটেল ও মেটা', 'social' => 'সোশ্যাল', 'schema' => 'স্কিমা ও ব্রেডক্রাম্ব', 'sitemaps' => 'সাইটম্যাপ', 'indexing' => 'ইনস্ট্যান্ট ইনডেক্সিং', 'redirects' => '404 ও রিডাইরেক্ট', 'files' => 'robots / .htaccess', 'import' => 'ইমপোর্ট', 'speed' => 'PageSpeed', 'stats' => 'সার্চ স্ট্যাটিস্টিক্স', 'advanced' => 'অ্যাডভান্সড / AI', 'help' => 'হেল্প' );
}
function hcseo_url( $tab = 'overview', $x = array() ) { return add_query_arg( array_merge( array( 'page' => 'hitscam-seo', 'tab' => $tab ), $x ), admin_url( 'admin.php' ) ); }

add_action( 'admin_menu', function () {
	add_menu_page( 'HitsCam SEO', 'HitsCam SEO', 'manage_options', 'hitscam-seo', 'hcseo_page', 'dashicons-search', 59 );
	add_submenu_page( 'hitscam-seo', 'SEO Wizard', 'SEO Wizard', 'manage_options', 'hitscam-seo-wizard', 'hcseo_wizard' );
} );
add_action( 'admin_head', function () {
	remove_submenu_page( 'hitscam-seo', 'hitscam-seo-wizard' );
} );
add_action( 'admin_init', function () {
	if ( get_transient( 'hcseo_redirect_wizard' ) && ! wp_doing_ajax() && ! isset( $_GET['activate-multi'] ) ) { delete_transient( 'hcseo_redirect_wizard' ); wp_safe_redirect( admin_url( 'admin.php?page=hitscam-seo-wizard' ) ); exit; }
} );

/* ---------- POST হ্যান্ডলার ---------- */
add_action( 'admin_post_hcseo_save', function () {
	check_admin_referer( 'hcseo' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	$g = sanitize_key( $_POST['group'] ?? '' );
	hcseo_save_group( $g, wp_unslash( $_POST['f'] ?? array() ) );
	wp_safe_redirect( hcseo_url( $g, array( 'saved' => 1 ) ) ); exit;
} );
add_action( 'admin_post_hcseo_wizard', function () {
	check_admin_referer( 'hcseo' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	$f = wp_unslash( $_POST['f'] ?? array() );
	hcseo_save_group( 'schema', array( 'schema_enable' => 1, 'kg_type' => $f['kg_type'] ?? 'Organization', 'kg_name' => $f['kg_name'] ?? '', 'kg_logo' => $f['kg_logo'] ?? '', 'kg_same_as' => $f['kg_same_as'] ?? '', 'kg_phone' => $f['kg_phone'] ?? '', 'sitelinks' => 1, 'breadcrumbs' => 1, 'wc_meta' => 1, 'bc_sep' => '›', 'lb_country' => 'BD', 'lb_type' => 'Store' ) );
	hcseo_save_group( 'social', array( 'og_enable' => 1, 'tw_enable' => 1, 'tw_card' => 'summary_large_image', 'tw_site' => $f['tw_site'] ?? '', 'default_image' => $f['kg_logo'] ?? '' ) );
	hcseo_save_group( 'sitemaps', array( 'sm_enable' => 1, 'sm_types' => 'post,page,product', 'sm_tax' => 'category,product_cat', 'sm_images' => 1 ) );
	hcseo_save_group( 'titles', array_map( fn( $d ) => $d, array_merge( array_map( fn( $x ) => $x[2], hcseo_schema()['titles'] ), array( 'noindex_search' => 1, 'noindex_author' => 1, 'noindex_date' => 1, 'noindex_filtered' => 1 ) ) ) );
	update_option( 'hcseo_wizard_done', 1 ); flush_rewrite_rules();
	wp_safe_redirect( hcseo_url( 'overview', array( 'wizard' => 1 ) ) ); exit;
} );
add_action( 'admin_post_hcseo_import', function () {
	check_admin_referer( 'hcseo' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	$n = hcseo_import_run( sanitize_key( $_POST['src'] ?? '' ), ! empty( $_POST['overwrite'] ) );
	wp_safe_redirect( hcseo_url( 'import', array( 'imported' => $n ) ) ); exit;
} );
add_action( 'admin_post_hcseo_files', function () {
	check_admin_referer( 'hcseo' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	$msg = 'ok';
	if ( isset( $_POST['robots'] ) ) { update_option( 'hcseo_robots', sanitize_textarea_field( wp_unslash( $_POST['robots'] ) ) ); }
	if ( isset( $_POST['htaccess'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/misc.php';
		$p = hcseo_htaccess_path(); $new = str_replace( "\r\n", "\n", wp_unslash( $_POST['htaccess'] ) );
		if ( file_exists( $p ) && is_writable( $p ) && ( false !== strpos( $new, '# BEGIN WordPress' ) || ! file_exists( $p ) || ! empty( $_POST['force'] ) ) ) {
			copy( $p, $p . '.hcseo-bak' ); file_put_contents( $p, $new ); // phpcs:ignore
		} else { $msg = 'htaccess_fail'; }
	}
	wp_safe_redirect( hcseo_url( 'files', array( 'saved' => 1, 'm' => $msg ) ) ); exit;
} );
add_action( 'admin_post_hcseo_redirect', function () {
	check_admin_referer( 'hcseo' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	global $wpdb;
	if ( ! empty( $_POST['del'] ) ) { $wpdb->delete( $wpdb->prefix . 'hcseo_redirects', array( 'id' => absint( $_POST['del'] ) ) ); }
	elseif ( ! empty( $_POST['clear404'] ) ) { $wpdb->query( "DELETE FROM {$wpdb->prefix}hcseo_404" ); } // phpcs:ignore
	elseif ( ! empty( $_POST['src'] ) ) { hcseo_add_redirect( wp_unslash( $_POST['src'] ), wp_unslash( $_POST['tgt'] ?? '' ), (int) ( $_POST['code'] ?? 301 ) ); }
	wp_safe_redirect( hcseo_url( 'redirects', array( 'saved' => 1 ) ) ); exit;
} );
add_action( 'admin_post_hcseo_submit', function () {
	check_admin_referer( 'hcseo' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'অনুমতি নেই' ); }
	$u = esc_url_raw( wp_unslash( $_POST['u'] ?? '' ) );
	if ( $u ) { if ( ! empty( $_POST['now'] ) ) { hcseo_indexnow_submit( array( $u ) ); } if ( ! empty( $_POST['goog'] ) ) { hcseo_google_notify( $u ); } }
	wp_safe_redirect( hcseo_url( 'indexing', array( 'sent' => 1 ) ) ); exit;
} );

/* ---------- রেন্ডার হেল্পার ---------- */
function hcseo_form( $group ) {
	$sc = hcseo_schema()[ $group ]; $o = hcseo_opts();
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_save"><input type="hidden" name="group" value="' . esc_attr( $group ) . '">';
	wp_nonce_field( 'hcseo' ); echo '<table class="form-table">';
	foreach ( $sc as $k => $f ) {
		list( $type, $label, , $desc ) = array_pad( $f, 4, '' ); $n = "f[$k]"; $v = $o[ $k ];
		echo '<tr><th><label>' . esc_html( $label ) . '</label></th><td>';
		if ( 'checkbox' === $type ) { printf( '<input type="checkbox" name="%s" value="1" %s>', esc_attr( $n ), checked( $v, 1, false ) ); }
		elseif ( 'textarea' === $type ) { printf( '<textarea name="%s" rows="3" class="large-text">%s</textarea>', esc_attr( $n ), esc_textarea( $v ) ); }
		elseif ( 'select' === $type ) { echo '<select name="' . esc_attr( $n ) . '">'; foreach ( $f[3] as $ov => $ol ) { printf( '<option value="%s" %s>%s</option>', esc_attr( $ov ), selected( $v, $ov, false ), esc_html( $ol ) ); } echo '</select>'; $desc = ''; }
		else { printf( '<input type="%s" name="%s" value="%s" class="regular-text" autocomplete="off">', in_array( $k, array( 'ai_key' ), true ) ? 'password' : 'text', esc_attr( $n ), esc_attr( $v ) ); }
		if ( $desc && 'select' !== $type ) { echo '<p class="description">' . esc_html( $desc ) . '</p>'; }
		echo '</td></tr>';
	}
	echo '</table>'; submit_button( 'সেভ করুন' ); echo '</form>';
}

function hcseo_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$tab = sanitize_key( $_GET['tab'] ?? 'overview' ); $tabs = hcseo_tabs(); $tab = isset( $tabs[ $tab ] ) ? $tab : 'overview';
	echo '<div class="wrap"><h1>HitsCam SEO <small style="font-size:12px;color:#666">v' . esc_html( HCSEO_VERSION ) . '</small></h1>';
	if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>সেভ হয়েছে।</p></div>'; }
	echo '<nav class="nav-tab-wrapper" style="display:flex;flex-wrap:wrap">';
	foreach ( $tabs as $k => $l ) { printf( '<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url( hcseo_url( $k ) ), esc_html( $l ) ); }
	echo '</nav><div style="max-width:960px;margin-top:16px">';
	$fn = 'hcseo_tab_' . $tab; if ( function_exists( $fn ) ) { $fn(); } elseif ( isset( hcseo_schema()[ $tab ] ) ) { hcseo_form( $tab ); }
	echo '</div></div>';
}

function hcseo_tab_overview() {
	global $wpdb;
	$ok = fn( $c ) => $c ? '<b style="color:#1a7f37">চালু</b>' : '<b style="color:#b32d2e">বন্ধ</b>';
	if ( isset( $_GET['wizard'] ) ) { echo '<div class="notice notice-success"><p>ওয়াইজার্ড সম্পন্ন — মূল সেটিংস প্রয়োগ হয়েছে।</p></div>'; }
	$n404 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hcseo_404" ); // phpcs:ignore
	echo '<table class="widefat striped" style="max-width:640px"><tbody>';
	foreach ( array( 'সাইটম্যাপ' => hcseo_opt( 'sm_enable' ), 'Open Graph / X' => hcseo_opt( 'og_enable' ), 'স্ট্রাকচার্ড ডেটা' => hcseo_opt( 'schema_enable' ), 'IndexNow' => hcseo_opt( 'in_now' ), 'Google Indexing API' => hcseo_opt( 'in_google' ), '404 মনিটর' => hcseo_opt( 'redirect_404' ) ) as $l => $c ) { echo '<tr><td>' . esc_html( $l ) . '</td><td>' . $ok( $c ) . '</td></tr>'; } // phpcs:ignore
	echo '<tr><td>সাইটম্যাপ লিংক</td><td><a href="' . esc_url( home_url( '/sitemap_index.xml' ) ) . '" target="_blank">/sitemap_index.xml</a></td></tr><tr><td>অমীমাংসিত 404 URL</td><td>' . (int) $n404 . '</td></tr></tbody></table>';
	if ( ! get_option( 'hcseo_wizard_done' ) ) { echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=hitscam-seo-wizard' ) ) . '">ইনস্টলেশন ওয়াইজার্ড চালান</a></p>'; }
	$cmp = array( 'Yoast SEO' => 'wordpress-seo/wp-seo.php', 'Rank Math' => 'seo-by-rank-math/rank-math.php', 'SEOPress' => 'wp-seopress/seopress.php', 'AIOSEO' => 'all-in-one-seo-pack/all_in_one_seo_pack.php' );
	$act = array_filter( $cmp, fn( $f ) => is_plugin_active( $f ) );
	if ( $act ) { echo '<div class="notice notice-warning inline"><p>অন্য SEO প্লাগিন সক্রিয়: <b>' . esc_html( implode( ', ', array_keys( $act ) ) ) . '</b> — ডুপ্লিকেট মেটা এড়াতে ডিঅ্যাকটিভেট করুন (আগে ইমপোর্ট ট্যাব থেকে ডেটা আনুন)।</p></div>'; }
}

function hcseo_wizard() {
	$o = hcseo_opts();
	echo '<div class="wrap"><h1>HitsCam SEO — সেটআপ ওয়াইজার্ড</h1><p>৩০ সেকেন্ডে মূল সেটিংস ঠিক করুন; পরে সব বদলানো যায়।</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:640px"><input type="hidden" name="action" value="hcseo_wizard">'; wp_nonce_field( 'hcseo' );
	echo '<table class="form-table">';
	echo '<tr><th>সাইট কার?</th><td><select name="f[kg_type]"><option>Organization</option><option>Person</option></select></td></tr>';
	foreach ( array( 'kg_name' => array( 'নাম', get_bloginfo( 'name' ) ), 'kg_logo' => array( 'লোগো URL (শেয়ার ইমেজও)', $o['kg_logo'] ), 'kg_phone' => array( 'ফোন', $o['kg_phone'] ), 'tw_site' => array( 'X ইউজারনেম', $o['tw_site'] ) ) as $k => $x ) { printf( '<tr><th>%s</th><td><input class="regular-text" name="f[%s]" value="%s"></td></tr>', esc_html( $x[0] ), esc_attr( $k ), esc_attr( $x[1] ) ); }
	echo '<tr><th>সোশ্যাল প্রোফাইল</th><td><textarea name="f[kg_same_as]" rows="3" class="large-text" placeholder="https://facebook.com/…&#10;https://instagram.com/…">' . esc_textarea( $o['kg_same_as'] ) . '</textarea></td></tr></table>';
	submit_button( 'সম্পন্ন — প্রয়োগ করুন' ); echo '</form><p><a href="' . esc_url( hcseo_url() ) . '">স্কিপ করুন</a></p></div>';
	update_option( 'hcseo_wizard_done', 1 );
}

function hcseo_tab_indexing() {
	hcseo_form( 'indexing' );
	$key = hcseo_opt( 'in_key' );
	if ( $key ) { echo '<p>IndexNow কী ফাইল: <a href="' . esc_url( home_url( "/$key.txt" ) ) . '" target="_blank">/' . esc_html( $key ) . '.txt</a></p>'; }
	if ( isset( $_GET['sent'] ) ) { echo '<div class="notice notice-info"><p>পাঠানো হয়েছে — নিচের লগ দেখুন।</p></div>'; }
	echo '<h3>ম্যানুয়াল সাবমিট</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_submit">'; wp_nonce_field( 'hcseo' );
	echo '<input class="regular-text" type="url" name="u" placeholder="https://…" required> <label><input type="checkbox" name="now" value="1" checked> IndexNow</label> <label><input type="checkbox" name="goog" value="1"> Google</label> '; submit_button( 'পাঠান', 'secondary', '', false ); echo '</form>';
	echo '<h3>লগ</h3><table class="widefat striped"><thead><tr><th>সময়</th><th>সার্ভিস</th><th>URL</th><th>অবস্থা</th><th>বার্তা</th></tr></thead><tbody>';
	foreach ( (array) get_option( 'hcseo_index_log', array() ) as $l ) { printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( wp_date( 'd M H:i', $l[0] ) ), esc_html( $l[1] ), esc_html( $l[2] ), esc_html( $l[3] ), esc_html( $l[4] ) ); }
	echo '</tbody></table><p class="description">তৃতীয়-পক্ষ API: IndexNow (api.indexnow.org — URL ও key), Google OAuth (oauth2.googleapis.com — আপনার সার্ভিস-অ্যাকাউন্টের JWT) ও Google Indexing API (indexing.googleapis.com — URL ও অবস্থা)। এগুলো শুধু আপনি চালু করলে সক্রিয় হয়।</p>';
}

function hcseo_tab_redirects() {
	global $wpdb;
	echo '<h2>রিডাইরেক্ট যোগ</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_redirect">'; wp_nonce_field( 'hcseo' );
	echo '<input name="src" placeholder="/old-url" class="regular-text" required> → <input name="tgt" placeholder="/new-url বা https://…" class="regular-text" required> <select name="code"><option>301</option><option>302</option><option>307</option><option>410</option></select> '; submit_button( 'যোগ', 'primary', '', false ); echo '</form>';
	echo '<h3>বিদ্যমান</h3><table class="widefat striped"><thead><tr><th>থেকে</th><th>যেখানে</th><th>কোড</th><th>হিট</th><th></th></tr></thead><tbody>';
	foreach ( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hcseo_redirects ORDER BY id DESC LIMIT 200" ) as $r ) { // phpcs:ignore
		echo '<tr><td>' . esc_html( $r->source ) . '</td><td>' . esc_html( $r->target ) . '</td><td>' . (int) $r->code . '</td><td>' . (int) $r->hits . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_redirect">'; wp_nonce_field( 'hcseo' ); echo '<button name="del" value="' . (int) $r->id . '" class="button-link-delete">মুছুন</button></form></td></tr>';
	}
	echo '</tbody></table><h2>404 মনিটর</h2>';
	hcseo_form_404_toggle();
	echo '<table class="widefat striped"><thead><tr><th>URL</th><th>হিট</th><th>রেফারার</th><th>শেষ</th><th>রিডাইরেক্ট</th></tr></thead><tbody>';
	foreach ( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hcseo_404 ORDER BY hits DESC LIMIT 100" ) as $r ) { // phpcs:ignore
		echo '<tr><td>' . esc_html( $r->url ) . '</td><td>' . (int) $r->hits . '</td><td>' . esc_html( $r->referrer ) . '</td><td>' . esc_html( $r->last_seen ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:4px"><input type="hidden" name="action" value="hcseo_redirect"><input type="hidden" name="src" value="' . esc_attr( $r->url ) . '">'; wp_nonce_field( 'hcseo' ); echo '<input name="tgt" placeholder="/নতুন-url" required><button class="button">→ 301</button></form></td></tr>';
	}
	echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_redirect">'; wp_nonce_field( 'hcseo' ); submit_button( '404 লগ মুছুন', 'secondary', 'clear404', false ); echo '</form>';
}
function hcseo_form_404_toggle() {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_save"><input type="hidden" name="group" value="advanced">'; wp_nonce_field( 'hcseo' );
	foreach ( hcseo_schema()['advanced'] as $k => $f ) { if ( 'redirect_404' === $k || 'redirect_auto' === $k ) { printf( '<label style="margin-right:16px"><input type="checkbox" name="f[%s]" value="1" %s> %s</label>', esc_attr( $k ), checked( hcseo_opt( $k ), 1, false ), esc_html( $f[1] ) ); } else { printf( '<input type="hidden" name="f[%s]" value="%s">', esc_attr( $k ), esc_attr( hcseo_opt( $k ) ) ); } }
	submit_button( 'সেভ', 'secondary', '', false ); echo '</form>';
}

function hcseo_tab_files() {
	if ( isset( $_GET['m'] ) && 'htaccess_fail' === $_GET['m'] ) { echo '<div class="notice notice-error"><p>.htaccess সেভ হয়নি (লেখার অনুমতি নেই বা “# BEGIN WordPress” ব্লক বাদ পড়েছে)।</p></div>'; }
	$rob = get_option( 'hcseo_robots', '' ) ?: ( function_exists( 'do_robots' ) ? hcseo_robots_default() : '' );
	echo '<h2>robots.txt (ভার্চুয়াল)</h2><p>ডিস্কে আসল <code>robots.txt</code> ফাইল থাকলে এটি কাজ করবে না।</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_files">'; wp_nonce_field( 'hcseo' );
	echo '<textarea name="robots" id="hcseo-robots" rows="10" class="large-text code">' . esc_textarea( $rob ) . '</textarea>';
	echo '<p><button type="button" class="button" onclick="var t=document.getElementById(\'hcseo-robots\');t.value+=\'\\nUser-agent: GPTBot\\nDisallow: /\\n\'">+ GPTBot ব্লক</button> <button type="button" class="button" onclick="var t=document.getElementById(\'hcseo-robots\');t.value+=\'\\nDisallow: /?s=\\n\'">+ সার্চ ব্লক</button> <a href="' . esc_url( home_url( '/robots.txt' ) ) . '" target="_blank">দেখুন</a></p>'; submit_button( 'robots.txt সেভ' ); echo '</form>';
	$p = hcseo_htaccess_path();
	echo '<h2>.htaccess</h2>';
	if ( ! file_exists( $p ) ) { echo '<p>.htaccess ফাইল নেই (Nginx/পারমালিংক প্লেইন হলে এমন হয়)।</p>'; return; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_files">'; wp_nonce_field( 'hcseo' );
	echo '<textarea name="htaccess" id="hcseo-ht" rows="14" class="large-text code" ' . ( is_writable( $p ) ? '' : 'readonly' ) . '>' . esc_textarea( file_get_contents( $p ) ) . '</textarea><p>'; // phpcs:ignore
	foreach ( hcseo_htaccess_snippets() as $k => $s ) { printf( '<button type="button" class="button" onclick="var t=document.getElementById(\'hcseo-ht\');t.value+=%s">+ %s</button> ', esc_attr( wp_json_encode( "\n# HitsCam $k\n$s\n" ) ), esc_html( $k ) ); }
	echo '</p><p class="description">সেভের সময় ব্যাকআপ <code>.htaccess.hcseo-bak</code> তৈরি হয়। ভুল নিয়মে সাইট ভেঙে যেতে পারে।</p>'; submit_button( '.htaccess সেভ', 'primary', '', true, is_writable( $p ) ? array() : array( 'disabled' => 'disabled' ) ); echo '</form>';
}

function hcseo_tab_import() {
	if ( isset( $_GET['imported'] ) ) { echo '<div class="notice notice-success"><p>' . (int) $_GET['imported'] . 'টি আইটেম ইমপোর্ট হয়েছে।</p></div>'; }
	echo '<p>অন্য SEO প্লাগিনের পোস্ট/টার্ম মেটা এক ক্লিকে আনুন (টাইটেল, ডেসক্রিপশন, canonical, noindex/nofollow, সোশ্যাল, ফোকাস কিওয়ার্ড; ভেরিয়েবল রূপান্তরিত)। মূল ডেটা অক্ষত থাকে। <b>গ্লোবাল টেমপ্লেট সেটিংস</b> ইমপোর্ট হয় না।</p><table class="widefat striped"><thead><tr><th>সোর্স</th><th>পাওয়া গেছে</th><th></th></tr></thead><tbody>';
	foreach ( hcseo_import_sources() as $k => $s ) {
		echo '<tr><td>' . esc_html( $s[0] ) . '</td><td>' . (int) hcseo_import_count( $k ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hcseo_import"><input type="hidden" name="src" value="' . esc_attr( $k ) . '">'; wp_nonce_field( 'hcseo' );
		echo '<label><input type="checkbox" name="overwrite" value="1"> বিদ্যমান ওভাররাইট</label> <button class="button">ইমপোর্ট</button></form></td></tr>';
	}
	echo '</tbody></table><p class="description">Yoast-এর টার্ম মেটা (সিরিয়ালাইজড অপশনে থাকে) এই সংস্করণে ইমপোর্ট হয় না।</p>';
}

function hcseo_tab_speed() {
	$u = esc_url_raw( wp_unslash( $_GET['u'] ?? home_url( '/' ) ) );
	echo '<form method="get"><input type="hidden" name="page" value="hitscam-seo"><input type="hidden" name="tab" value="speed"><input class="regular-text" name="u" value="' . esc_attr( $u ) . '"> '; submit_button( 'বিশ্লেষণ (মোবাইল + ডেস্কটপ)', 'primary', '', false ); echo '</form>';
	if ( isset( $_GET['u'] ) ) {
		echo '<p class="description">Google PageSpeed Insights API-কে URL পাঠানো হচ্ছে; ৩০–৬০ সেকেন্ড লাগতে পারে। (লোকালহোস্ট URL কাজ করবে না।)</p><table class="widefat striped" style="max-width:720px"><thead><tr><th>ডিভাইস</th><th>স্কোর</th><th>FCP</th><th>LCP</th><th>TBT</th><th>CLS</th><th>SI</th></tr></thead><tbody>';
		foreach ( array( 'mobile' => 'মোবাইল', 'desktop' => 'ডেস্কটপ' ) as $s => $l ) {
			$r = hcseo_psi( $u, $s );
			echo is_wp_error( $r ) ? '<tr><td>' . esc_html( $l ) . '</td><td colspan="6">' . esc_html( $r->get_error_message() ) . '</td></tr>' : '<tr><td>' . esc_html( $l ) . '</td><td><b>' . (int) $r['score'] . '</b></td><td>' . esc_html( $r['fcp'] ) . '</td><td>' . esc_html( $r['lcp'] ) . '</td><td>' . esc_html( $r['tbt'] ) . '</td><td>' . esc_html( $r['cls'] ) . '</td><td>' . esc_html( $r['si'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

function hcseo_tab_stats() {
	echo '<p>Google Search Console থেকে শেষ ২৮ দিনের শীর্ষ কুয়েরি ও পেজ। সার্ভিস-অ্যাকাউন্ট JSON (ইনডেক্সিং ট্যাব) ও সাইট URL (অ্যাডভান্সড ট্যাব) লাগবে; ঐ ইমেইলকে Search Console-এ ইউজার হিসেবে যোগ করুন।</p>';
	if ( ! hcseo_opt( 'gsc_site' ) || ! hcseo_opt( 'in_google_sa' ) ) { echo '<p><i>এখনো কনফিগার করা হয়নি।</i></p>'; return; }
	$r = hcseo_gsc_query();
	if ( is_wp_error( $r ) ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $r->get_error_message() ) . '</p></div>'; return; }
	foreach ( array( 'query' => 'শীর্ষ কুয়েরি', 'page' => 'শীর্ষ পেজ' ) as $d => $l ) {
		echo '<h3>' . esc_html( $l ) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html( $l ) . '</th><th>ক্লিক</th><th>ইমপ্রেশন</th><th>CTR</th><th>পজিশন</th></tr></thead><tbody>';
		foreach ( $r[ $d ] as $row ) { printf( '<tr><td>%s</td><td>%d</td><td>%d</td><td>%.1f%%</td><td>%.1f</td></tr>', esc_html( $row['keys'][0] ), $row['clicks'], $row['impressions'], $row['ctr'] * 100, $row['position'] ); }
		echo '</tbody></table>';
	}
}

function hcseo_tab_help() {
	echo '<h2>হেল্প</h2><ul style="list-style:disc;margin-left:20px"><li><b>ভেরিয়েবল:</b> <code>' . esc_html( implode( ' ', array_map( fn( $v ) => "%%$v%%", hcseo_vars_list() ) ) ) . '</code></li><li>কাস্টম ভেরিয়েবল: <code>hitscam_seo_variables</code> ফিল্টার।</li><li>ব্রেডক্রাম্ব: <code>[hitscam_breadcrumbs]</code> বা <code>hitscam_seo_breadcrumbs()</code>।</li><li>স্কিমা পরিবর্তন: <code>hitscam_seo_schema</code>; কনটেক্সট: <code>hitscam_seo_context</code>।</li><li>সাইটম্যাপ: <code>/sitemap_index.xml</code> — Search Console/Bing Webmaster-এ সাবমিট করুন।</li><li>Elementor/পেজ-বিল্ডার: মেটাবক্স সব পোস্ট এডিট স্ক্রিনে থাকে; অ্যানালাইসিস সেভ করা কনটেন্ট থেকে পড়ে।</li></ul>';
}
