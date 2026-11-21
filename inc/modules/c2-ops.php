<?php
/**
 * c2 — অপারেশন: WhatsApp স্ট্যাটাস টেমপ্লেট (অর্ডার স্ক্রিনে বাটন), অর্ডার CSV এক্সপোর্ট, "HitsCam ড্যাশবোর্ড", Google Merchant ফিড (/feed/merchant.xml)।
 * টেমপ্লেট: ops.js + admin.html। মেনু: WooCommerce → HitsCam ড্যাশবোর্ড / অপারেশন।
 */
defined( 'ABSPATH' ) || exit;

/* ---------- WhatsApp টেমপ্লেট ---------- */
function hitscam_ops_tpls() {
	$d = array(
		'confirm'   => 'আসসালামু আলাইকুম {name}, আপনার অর্ডার #{order} (মোট {total}) কনফার্ম হয়েছে। ধন্যবাদ — HitsCam',
		'shipped'   => '{name}, আপনার অর্ডার #{order} কুরিয়ারে পাঠানো হয়েছে।{courier} শীঘ্রই পৌঁছাবে। — HitsCam',
		'delivered' => '{name}, আপনার অর্ডার #{order} ডেলিভারি সম্পন্ন হয়েছে। পণ্য ঠিকঠাক চললে একটি রিভিউ দিলে খুশি হব। — HitsCam',
		'cancelled' => '{name}, আপনার অর্ডার #{order} বাতিল করা হয়েছে। প্রয়োজনে আমাদের জানান। — HitsCam',
	);
	return wp_parse_args( (array) get_option( 'hitscam_wa_tpl', array() ), $d );
}
function hitscam_ops_tpl_labels() { return array( 'confirm' => 'কনফার্ম', 'shipped' => 'কুরিয়ারে পাঠানো', 'delivered' => 'ডেলিভারি', 'cancelled' => 'বাতিল' ); }
function hitscam_ops_wa_url( WC_Order $o, $k ) {
	$t = hitscam_ops_tpls(); $courier = '';
	if ( $o->get_meta( '_hc_consignment' ) ) { $co = hitscam_a2_couriers(); $ck = $o->get_meta( '_hc_courier' ); $courier = ' কুরিয়ার: ' . ( $co[ $ck ][0] ?? '' ) . ', কনসাইনমেন্ট ' . $o->get_meta( '_hc_consignment' ) . '.'; }
	$m  = strtr( $t[ $k ] ?? '', array( '{name}' => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), '{order}' => $o->get_order_number(), '{total}' => html_entity_decode( wp_strip_all_tags( wc_price( $o->get_total() ) ) ), '{courier}' => $courier ) );
	$ph = hitscam_normalize_bd_phone( $o->get_billing_phone() );
	return $ph ? 'https://wa.me/88' . $ph . '?text=' . rawurlencode( $m ) : '';
}
add_action( 'add_meta_boxes', function () {
	foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $s ) { add_meta_box( 'hc_wa', 'WhatsApp আপডেট (HitsCam)', function ( $x ) {
		$o = $x instanceof WC_Order ? $x : wc_get_order( $x->ID ); if ( ! $o ) { return; }
		foreach ( hitscam_ops_tpl_labels() as $k => $l ) { $u = hitscam_ops_wa_url( $o, $k ); echo $u ? '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $u ) . '">📲 ' . esc_html( $l ) . '</a></p>' : ''; } // phpcs:ignore
		if ( ! hitscam_normalize_bd_phone( $o->get_billing_phone() ) ) { echo '<p>সঠিক মোবাইল নম্বর নেই।</p>'; }
	}, $s, 'side' ); }
} );

/* ---------- অ্যাডমিন মেনু ---------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'HitsCam ড্যাশবোর্ড', 'HitsCam ড্যাশবোর্ড', 'manage_woocommerce', 'hitscam-dash', 'hitscam_ops_dash' );
	add_submenu_page( 'woocommerce', 'HitsCam অপারেশন', 'HitsCam অপারেশন', 'manage_woocommerce', 'hitscam-ops', 'hitscam_ops_page' );
} );

function hitscam_ops_sum( $after ) {
	$ids = wc_get_orders( array( 'limit' => -1, 'status' => array( 'processing', 'ready-to-ship', 'completed', 'on-hold' ), 'date_created' => '>=' . $after, 'return' => 'objects' ) ); $t = 0;
	foreach ( $ids as $o ) { $t += (float) $o->get_total(); }
	return array( count( $ids ), $t, $ids );
}
function hitscam_ops_dash() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	$today = hitscam_ops_sum( strtotime( 'today', current_time( 'timestamp' ) ) ); $w7 = hitscam_ops_sum( time() - 7 * DAY_IN_SECONDS ); $m30 = hitscam_ops_sum( time() - 30 * DAY_IN_SECONDS );
	$top = array(); foreach ( $m30[2] as $o ) { foreach ( $o->get_items() as $it ) { $top[ $it->get_name() ] = ( $top[ $it->get_name() ] ?? 0 ) + $it->get_quantity(); } } arsort( $top );
	$low = wc_get_products( array( 'limit' => 8, 'manage_stock' => true, 'stock_status' => 'instock', 'orderby' => 'meta_value_num', 'meta_key' => '_stock', 'order' => 'ASC' ) );
	$st  = array(); foreach ( wc_get_order_statuses() as $k => $l ) { $st[ $l ] = count( wc_get_orders( array( 'limit' => -1, 'status' => str_replace( 'wc-', '', $k ), 'return' => 'ids' ) ) ); }
	global $wpdb; $rma = $wpdb->get_results( "SELECT code,rtype,product,stage FROM {$wpdb->prefix}hc_rma ORDER BY id DESC LIMIT 5" ); // phpcs:ignore
	$leads = get_posts( array( 'post_type' => 'hc_lead', 'numberposts' => 5 ) );
	$m = function ( $n ) { return wp_kses_post( wc_price( $n ) ); };
	echo '<div class="wrap"><h1>HitsCam ড্যাশবোর্ড</h1><style>.hcd{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:14px 0}.hcd>div{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px}.hcd b{font-size:22px;display:block}</style>';
	echo '<div class="hcd"><div>আজ<b>' . $m( $today[1] ) . '</b>' . (int) $today[0] . 'টি অর্ডার</div><div>গত ৭ দিন<b>' . $m( $w7[1] ) . '</b>' . (int) $w7[0] . 'টি অর্ডার</div><div>গত ৩০ দিন<b>' . $m( $m30[1] ) . '</b>' . (int) $m30[0] . 'টি অর্ডার</div></div>';
	echo '<div class="hcd"><div><h3>অর্ডার স্ট্যাটাস</h3>'; foreach ( $st as $l => $n ) { echo esc_html( $l ) . ': <b style="display:inline;font-size:14px">' . (int) $n . '</b><br>'; } echo '</div>';
	echo '<div><h3>টপ পণ্য (৩০ দিন)</h3>'; foreach ( array_slice( $top, 0, 6, true ) as $n => $q ) { echo esc_html( $n ) . ' — <b style="display:inline;font-size:14px">' . (int) $q . '</b><br>'; } if ( ! $top ) { echo 'তথ্য নেই'; } echo '</div>';
	echo '<div><h3>কম স্টক</h3>'; foreach ( $low as $p ) { echo esc_html( $p->get_name() ) . ' — <b style="display:inline;font-size:14px">' . (int) $p->get_stock_quantity() . '</b><br>'; } if ( ! $low ) { echo 'স্টক ট্র্যাক করা পণ্য নেই'; } echo '</div>';
	echo '<div><h3>সাম্প্রতিক RMA</h3>'; foreach ( $rma as $r ) { echo esc_html( $r->code . ' · ' . $r->rtype . ' · ' . $r->product ) . '<br>'; } if ( ! $rma ) { echo 'নেই'; } echo '</div>';
	echo '<div><h3>অ্যালার্ট লিড</h3>'; foreach ( $leads as $l ) { echo esc_html( $l->post_title ) . '<br>'; } if ( ! $leads ) { echo 'নেই'; } echo '</div></div></div>';
}

function hitscam_ops_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	if ( isset( $_POST['hc_ops_n'] ) && wp_verify_nonce( sanitize_key( $_POST['hc_ops_n'] ), 'hc_ops' ) ) {
		$o = array(); foreach ( array_keys( hitscam_ops_tpl_labels() ) as $k ) { $o[ $k ] = sanitize_textarea_field( wp_unslash( $_POST['tpl'][ $k ] ?? '' ) ); }
		update_option( 'hitscam_wa_tpl', $o ); update_option( 'hitscam_cancel_min', absint( $_POST['cancel_min'] ?? 60 ) ); update_option( 'hitscam_guard_max', absint( $_POST['guard_max'] ?? 3 ) ); update_option( 'hitscam_guard_cod_above', absint( $_POST['guard_above'] ?? 10000 ) );
		echo '<div class="updated"><p>সংরক্ষিত।</p></div>';
	}
	$t = hitscam_ops_tpls();
	echo '<div class="wrap"><h1>HitsCam অপারেশন</h1><form method="post">'; wp_nonce_field( 'hc_ops', 'hc_ops_n' );
	echo '<h2>WhatsApp স্ট্যাটাস টেমপ্লেট</h2><p>ভেরিয়েবল: {name} {order} {total} {courier}</p><table class="form-table">';
	foreach ( hitscam_ops_tpl_labels() as $k => $l ) { echo '<tr><th>' . esc_html( $l ) . '</th><td><textarea name="tpl[' . esc_attr( $k ) . ']" rows="3" class="large-text">' . esc_textarea( $t[ $k ] ) . '</textarea></td></tr>'; }
	echo '</table><h2>সেলফ-সার্ভিস ও গার্ড</h2><table class="form-table"><tr><th>বাতিল/বদলের সময় (মিনিট)</th><td><input name="cancel_min" type="number" value="' . (int) hitscam_a2_window() . '"></td></tr><tr><th>এক ফোনে দিনে সর্বোচ্চ অর্ডার</th><td><input name="guard_max" type="number" value="' . (int) hitscam_a2_guard_cfg()['max'] . '"></td></tr><tr><th>এর বেশি অর্ডারে সাধারণ COD বন্ধ (৳)</th><td><input name="guard_above" type="number" value="' . (int) hitscam_a2_guard_cfg()['above'] . '"></td></tr></table><p><button class="button button-primary">সংরক্ষণ</button></p></form>';
	echo '<h2>অর্ডার CSV এক্সপোর্ট</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'hc_export' ); echo '<input type="hidden" name="action" value="hc_export_orders">শুরু <input type="date" name="from"> শেষ <input type="date" name="to"> <button class="button">CSV ডাউনলোড</button></form>';
	echo '<h2>Google Merchant ফিড</h2><p><a href="' . esc_url( home_url( '/feed/merchant.xml' ) ) . '" target="_blank">' . esc_html( home_url( '/feed/merchant.xml' ) ) . '</a> — Merchant Center এ এই URL দিন।</p></div>';
}

add_action( 'admin_post_hc_export_orders', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'অনুমতি নেই' ); }
	check_admin_referer( 'hc_export' );
	$a = array( 'limit' => 5000, 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC' );
	$f = sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) ); $t = sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) );
	if ( $f && $t ) { $a['date_created'] = $f . '...' . $t; } elseif ( $f ) { $a['date_created'] = '>=' . $f; } elseif ( $t ) { $a['date_created'] = '<=' . $t; }
	nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=hitscam-orders-' . gmdate( 'Ymd' ) . '.csv' );
	$out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'অর্ডার', 'তারিখ', 'স্ট্যাটাস', 'নাম', 'ফোন', 'ঠিকানা', 'পেমেন্ট', 'TrxID', 'আইটেম', 'সাবটোটাল', 'শিপিং', 'মোট', 'কুরিয়ার', 'কনসাইনমেন্ট' ) );
	$safe = function ( $v ) { $v = (string) $v; return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v; }; // CSV ইনজেকশন রোধ
	foreach ( wc_get_orders( $a ) as $o ) {
		$items = array(); foreach ( $o->get_items() as $it ) { $items[] = $it->get_name() . ' × ' . $it->get_quantity(); }
		fputcsv( $out, array_map( $safe, array( $o->get_order_number(), $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '', wc_get_order_status_name( $o->get_status() ), trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), $o->get_billing_phone(), implode( ', ', array_filter( array( $o->get_billing_address_1(), $o->get_billing_city(), $o->get_billing_state() ) ) ), $o->get_payment_method_title(), $o->get_meta( '_hc_trxid' ), implode( '; ', $items ), $o->get_subtotal(), $o->get_shipping_total(), $o->get_total(), $o->get_meta( '_hc_courier' ), $o->get_meta( '_hc_consignment' ) ) ) );
	}
	fclose( $out ); exit;
} );

/* ---------- Google Merchant ফিড ---------- */
add_action( 'init', function () {
	add_rewrite_rule( '^feed/merchant\.xml/?$', 'index.php?hc_merchant=1', 'top' );
	if ( get_option( 'hitscam_c2_rw' ) !== '2' ) { flush_rewrite_rules( false ); update_option( 'hitscam_c2_rw', '2' ); }
} );
add_filter( 'redirect_canonical', function ( $u ) { return get_query_var( 'hc_merchant' ) ? false : $u; } );
add_filter( 'query_vars', function ( $v ) { $v[] = 'hc_merchant'; return $v; } );
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'hc_merchant' ) || ! function_exists( 'wc_get_products' ) ) { return; }
	$x = function ( $s ) { return htmlspecialchars( wp_strip_all_tags( (string) $s ), ENT_XML1 | ENT_QUOTES, 'UTF-8' ); };
	$cur = get_woocommerce_currency();
	header( 'Content-Type: application/xml; charset=utf-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel><title>' . $x( get_bloginfo( 'name' ) ) . '</title><link>' . esc_url( home_url( '/' ) ) . '</link><description>' . $x( get_bloginfo( 'description' ) ) . '</description>';
	foreach ( wc_get_products( array( 'limit' => 500, 'status' => 'publish', 'visibility' => 'catalog' ) ) as $p ) {
		$list = $p->is_type( 'variable' ) ? array_map( 'wc_get_product', $p->get_children() ) : array( $p );
		foreach ( array_filter( $list ) as $v ) {
			if ( $p->is_type( 'grouped' ) || '' === $v->get_price() ) { continue; }
			$img = wp_get_attachment_image_url( $v->get_image_id() ?: $p->get_image_id(), 'full' ); $cats = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
			echo '<item><g:id>' . (int) $v->get_id() . '</g:id><g:title>' . $x( $v->get_name() ) . '</g:title><g:description>' . $x( wp_trim_words( $p->get_short_description() ?: $p->get_description() ?: $p->get_name(), 60 ) ) . '</g:description><g:link>' . esc_url( $v->is_type( 'variation' ) ? $v->get_permalink() : $p->get_permalink() ) . '</g:link>';
			if ( $img ) { echo '<g:image_link>' . esc_url( $img ) . '</g:image_link>'; }
			echo '<g:availability>' . ( $v->is_in_stock() ? 'in_stock' : 'out_of_stock' ) . '</g:availability><g:condition>new</g:condition>';
			if ( $v->is_on_sale() && $v->get_regular_price() ) { echo '<g:price>' . esc_html( number_format( (float) $v->get_regular_price(), 2, '.', '' ) . ' ' . $cur ) . '</g:price><g:sale_price>' . esc_html( number_format( (float) $v->get_sale_price(), 2, '.', '' ) . ' ' . $cur ) . '</g:sale_price>'; } else { echo '<g:price>' . esc_html( number_format( (float) $v->get_price(), 2, '.', '' ) . ' ' . $cur ) . '</g:price>'; }
			if ( $v->get_sku() ) { echo '<g:mpn>' . $x( $v->get_sku() ) . '</g:mpn>'; } else { echo '<g:identifier_exists>no</g:identifier_exists>'; }
			echo '<g:brand>' . $x( get_bloginfo( 'name' ) ) . '</g:brand>' . ( $cats ? '<g:product_type>' . $x( implode( ' > ', $cats ) ) . '</g:product_type>' : '' ) . '<g:item_group_id>' . (int) $p->get_id() . '</g:item_group_id></item>';
		}
	}
	echo '</channel></rss>'; exit;
} );
