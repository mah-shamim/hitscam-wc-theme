<?php
/**
 * অতিরিক্ত পেজ ও শর্টকোড: নীতিমালা পেজ, স্টোর লোকেটার, বান্ডেল, ডিলার, ওয়ারেন্টি চেক।
 * (ক্যামেরা-কুইজ → finder.php, রিটার্ন/রিপেয়ার → rma.php, সেলফ-সার্ভিস/কুরিয়ার → selfservice.php)
 */
defined( 'ABSPATH' ) || exit;

/* ---------- Customizer ---------- */
add_action( 'customize_register', function ( WP_Customize_Manager $c ) {
	$f = array(
		'stores'      => array( "স্টোর (প্রতি লাইনে: id|নাম|ঠিকানা|ফোন|lat|lng|খোলা|বন্ধ — ঘণ্টা ২৪-ঘণ্টা ফরম্যাটে)", "mirpur|HitsCam শো-রুম — মিরপুর|মিরপুর-১০, ঢাকা (আসল ঠিকানা বসান)|+880 1614-747054|23.8069|90.3687|10|20\ngazipur|HitsCam পিকআপ পয়েন্ট — গাজীপুর|গাজীপুর চৌরাস্তা (আসল ঠিকানা বসান)|+880 1614-747054|23.9999|90.4203|10|19" ),
		'bundles'     => array( "বান্ডেল (প্রতি লাইনে: নাম|বর্ণনা|ছাড়%|পণ্য-স্লাগ১,স্লাগ২,…)", '' ),
		'dealer_codes' => array( 'ডিলার কোড (কমা দিয়ে; ফাঁকা = ডিলার মোড বন্ধ)', '' ),
		'dealer_pct'  => array( 'ডিলার অতিরিক্ত ছাড় (%)', '4' ),
		'warranty_m'  => array( 'ডিফল্ট ওয়ারেন্টি (মাস)', '12' ),
	);
	foreach ( $f as $k => $x ) {
		$c->add_setting( 'hitscam_' . $k, array( 'default' => $x[1], 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$c->add_control( 'hitscam_' . $k, array( 'label' => $x[0], 'section' => 'hitscam', 'type' => in_array( $k, array( 'stores', 'bundles' ), true ) ? 'textarea' : 'text' ) );
	}
} );

/* ---------- পেজ তৈরি (থিম আপডেটেও একবার চলে) ---------- */
function hitscam_extra_pages() {
	$read = function ( $n ) { $p = HITSCAM_DIR . "/inc/pages/$n.html"; return file_exists( $p ) ? file_get_contents( $p ) : ''; }; // phpcs:ignore
	return apply_filters( 'hitscam_extra_pages', array(
		'shipping' => array( 'শিপিং তথ্য', $read( 'shipping' ) ),
		'returns'  => array( 'রিটার্ন, রিফান্ড ও ওয়ারেন্টি পলিসি', $read( 'returns' ) ),
		'terms'    => array( 'শর্তাবলী', $read( 'terms' ) ),
		'stores'   => array( 'আমাদের স্টোর', '[hitscam_stores]' ),
		'bundles'  => array( 'বান্ডেল অফার', '[hitscam_bundles]' ),
		'finder'   => array( 'ক্যামেরা বাছাই', '[hitscam_finder]' ),
		'dealer'   => array( 'ডিলার ও বাল্ক প্রাইস', '[hitscam_dealer]' ),
		'warranty' => array( 'ওয়ারেন্টি চেক', '[hitscam_warranty]' ),
		'rma'      => array( 'রিটার্ন ও রিপেয়ার অনুরোধ', '[hitscam_rma]' ),
	) );
}

function hitscam_ensure_extra_pages() {
	$ids = (array) get_option( 'hitscam_page_ids', array() );
	foreach ( hitscam_extra_pages() as $slug => $p ) {
		$ex = get_page_by_path( $slug );
		if ( $ex ) { $ids[ $slug ] = $ex->ID; continue; }
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $p[0], 'post_name' => $slug, 'post_content' => $p[1] ) );
		if ( $id && ! is_wp_error( $id ) ) { $ids[ $slug ] = $id; }
	}
	// {{url:slug}} লিংক বসাই
	foreach ( array( 'shipping', 'returns', 'terms' ) as $s ) {
		if ( empty( $ids[ $s ] ) ) { continue; }
		$post = get_post( $ids[ $s ] );
		if ( $post && false !== strpos( $post->post_content, '{{url:' ) ) {
			$new = preg_replace_callback( '/\{\{url:([a-z\-]*)\}\}/', function ( $m ) use ( $ids ) { return isset( $ids[ $m[1] ] ) ? esc_url( get_permalink( $ids[ $m[1] ] ) ) : esc_url( home_url( '/' ) ); }, $post->post_content );
			wp_update_post( array( 'ID' => $post->ID, 'post_content' => $new ) );
		}
	}
	update_option( 'hitscam_page_ids', $ids );
	// ফুটার মেনুতে নীতিমালা লিংক
	update_option( 'hitscam_pages_v', 3 );
}
add_action( 'admin_init', function () {
	if ( (int) get_option( 'hitscam_pages_v', 0 ) < 3 && get_option( 'hitscam_store_setup_done' ) && current_user_can( 'manage_options' ) ) { hitscam_ensure_extra_pages(); }
} );

/* ---------- স্টোর ---------- */
function hitscam_stores() {
	$out = array();
	foreach ( preg_split( '/\R/', (string) hitscam_opt( 'stores', '' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 8 ) {
			$out[] = array( 'id' => sanitize_key( $p[0] ), 'name' => $p[1], 'addr' => $p[2], 'phone' => $p[3], 'lat' => (float) $p[4], 'lng' => (float) $p[5], 'open' => (int) $p[6], 'close' => (int) $p[7] );
		}
	}
	return $out;
}
function hitscam_bn_hour( $h ) {
	return ( $h < 12 ? 'সকাল ' : ( $h < 16 ? 'দুপুর ' : ( $h < 18 ? 'বিকাল ' : ( $h < 20 ? 'সন্ধ্যা ' : 'রাত ' ) ) ) ) . hitscam_bn_digits( $h > 12 ? $h - 12 : $h ) . 'টা';
}
function hitscam_store_is_open( $s ) {
	$now = new DateTime( 'now', new DateTimeZone( 'Asia/Dhaka' ) );
	$h   = (int) $now->format( 'G' ) + (int) $now->format( 'i' ) / 60;
	return 5 !== (int) $now->format( 'w' ) && $h >= $s['open'] && $h < $s['close'];
}
add_shortcode( 'hitscam_stores', function () {
	$st = hitscam_stores();
	if ( ! $st ) { return '<p class="text-muted">স্টোর যোগ করা হয়নি (Customizer → HitsCam সেটিংস)।</p>'; }
	ob_start(); ?>
	<p class="text-muted">সরাসরি এসে পণ্য দেখুন বা অনলাইনে অর্ডার করে স্টোর থেকে নিন (শিপিং ফ্রি)।</p>
	<div data-stores-page><div class="d-flex flex-wrap gap-2 align-items-center mb-3"><button class="btn btn-soft" id="nearBtn" type="button"><i class="bi bi-geo-alt"></i> আমার কাছের স্টোর</button><span class="small text-muted" id="nearMsg" role="status"></span></div>
	<div class="row g-3" id="storeList"><?php foreach ( $st as $s ) : $open = hitscam_store_is_open( $s ); ?>
		<div class="col-md-6 col-lg-4" data-lat="<?php echo esc_attr( $s['lat'] ); ?>" data-lng="<?php echo esc_attr( $s['lng'] ); ?>"><div class="panel h-100 store-card">
			<div class="d-flex justify-content-between gap-2"><h2 class="h6 fw-bold"><?php echo esc_html( $s['name'] ); ?></h2><span class="badge <?php echo $open ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $open ? 'এখন খোলা' : 'বন্ধ'; ?></span></div>
			<div class="small text-muted mb-2"><?php echo esc_html( $s['addr'] ); ?></div>
			<div class="small"><i class="bi bi-clock"></i> শনি–বৃহস্পতি <?php echo esc_html( hitscam_bn_hour( $s['open'] ) . ' – ' . hitscam_bn_hour( $s['close'] ) ); ?> · শুক্র বন্ধ</div>
			<div class="small"><i class="bi bi-telephone"></i> <a href="tel:<?php echo esc_attr( preg_replace( '/[\s\-]/', '', $s['phone'] ) ); ?>"><?php echo esc_html( $s['phone'] ); ?></a></div>
			<div class="small text-brand mt-1 km" hidden></div>
			<div class="d-flex gap-2 mt-3"><a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?php echo esc_attr( $s['lat'] . ',' . $s['lng'] ); ?>"><i class="bi bi-map"></i> ম্যাপে দেখুন</a><a class="btn btn-brand btn-sm" href="<?php echo esc_url( add_query_arg( 'pickup', $s['id'], wc_get_checkout_url() ) ); ?>">এখান থেকে সংগ্রহ</a></div>
		</div></div><?php endforeach; ?></div></div>
	<?php return ob_get_clean();
} );

/* ---------- বান্ডেল ---------- */
function hitscam_bundles() {
	$out = array();
	foreach ( preg_split( '/\R/', (string) hitscam_opt( 'bundles', '' ) ) as $i => $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) < 4 ) { continue; }
		$items = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $p[3] ) ) ) as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'product' );
			$pr   = $post ? wc_get_product( $post->ID ) : null;
			if ( $pr && $pr->is_in_stock() ) { $items[] = $pr; }
		}
		if ( $items ) { $out[] = array( 'id' => 'b' . ( $i + 1 ), 'name' => $p[0], 'desc' => $p[1], 'off' => max( 0, min( 60, (int) $p[2] ) ), 'items' => $items ); }
	}
	return apply_filters( 'hitscam_bundles_list', $out );
}
function hitscam_bundle_price( WC_Product $p ) {
	return $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min' ) : (float) wc_get_price_to_display( $p );
}
function hitscam_bundle_variation_id( WC_Product $p ) {
	if ( ! $p->is_type( 'variable' ) ) { return 0; }
	$best = 0; $bp = INF;
	foreach ( $p->get_children() as $vid ) { $v = wc_get_product( $vid ); if ( $v && $v->is_in_stock() && (float) $v->get_price() < $bp ) { $bp = (float) $v->get_price(); $best = $vid; } }
	return $best;
}
add_shortcode( 'hitscam_bundles', function () {
	$b = hitscam_bundles();
	if ( ! $b ) { return '<p class="text-muted">কোনো বান্ডেল সেট করা নেই (Customizer → HitsCam সেটিংস → বান্ডেল)।</p>'; }
	ob_start();
	echo '<p class="text-muted">একসাথে কিনলে আলাদা ছাড় — প্রতিটি আইটেমে বান্ডেল ডিসকাউন্ট বসে যায়।</p><div class="row g-3">';
	foreach ( $b as $x ) {
		$sum = array_sum( array_map( 'hitscam_bundle_price', $x['items'] ) ); $net = round( $sum * ( 100 - $x['off'] ) / 100 );
		echo '<div class="col-md-6 col-lg-4"><div class="panel h-100 d-flex flex-column"><div class="d-flex justify-content-between"><h2 class="h6 fw-bold">' . esc_html( $x['name'] ) . '</h2><span class="badge bg-danger">−' . esc_html( hitscam_bn_digits( $x['off'] ) ) . '%</span></div><p class="small text-muted">' . esc_html( $x['desc'] ) . '</p><ul class="list-unstyled small mb-3">';
		foreach ( $x['items'] as $it ) { echo '<li class="d-flex align-items-center gap-2 mb-1">' . wp_kses_post( $it->get_image( array( 36, 36 ) ) ) . '<a href="' . esc_url( $it->get_permalink() ) . '">' . esc_html( $it->get_name() ) . '</a></li>'; }
		echo '</ul><div class="mt-auto"><del class="text-muted">' . wp_kses_post( wc_price( $sum ) ) . '</del> <b class="text-brand fs-5">' . wp_kses_post( wc_price( $net ) ) . '</b><button class="btn btn-brand w-100 mt-2 hc-bundle" type="button" data-id="' . esc_attr( $x['id'] ) . '"><i class="bi bi-bag-plus"></i> বান্ডেল কার্টে যোগ করুন</button></div></div></div>';
	}
	return ob_get_clean() . '</div>';
} );
add_action( 'wp_ajax_hc_add_bundle', 'hitscam_ajax_add_bundle' );
add_action( 'wp_ajax_nopriv_hc_add_bundle', 'hitscam_ajax_add_bundle' );
function hitscam_ajax_add_bundle() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$id = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
	foreach ( hitscam_bundles() as $x ) {
		if ( $x['id'] !== $id ) { continue; }
		foreach ( $x['items'] as $it ) {
			$vid = hitscam_bundle_variation_id( $it );
			if ( $it->is_type( 'variable' ) && ! $vid ) { continue; }
			WC()->cart->add_to_cart( $it->get_id(), 1, $vid, $vid ? wc_get_product( $vid )->get_variation_attributes() : array(), array( 'hc_bundle' => $id . ':' . $x['off'] ) );
		}
		WC()->cart->calculate_totals();
		ob_start(); woocommerce_mini_cart(); $mini = ob_get_clean();
		$frag = apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content flex-grow-1">' . $mini . '</div>' ) );
		wp_send_json_success( array( 'fragments' => $frag, 'cart_hash' => WC()->cart->get_cart_hash(), 'count' => WC()->cart->get_cart_contents_count() ) );
	}
	wp_send_json_error( array( 'msg' => 'বান্ডেল পাওয়া যায়নি।' ) );
}
add_filter( 'woocommerce_add_cart_item_data', function ( $d ) { return $d; } );
add_action( 'woocommerce_cart_calculate_fees', function ( WC_Cart $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) { return; }
	$tot = array();
	foreach ( $cart->get_cart() as $it ) {
		if ( empty( $it['hc_bundle'] ) ) { continue; }
		list( $bid, $off ) = explode( ':', $it['hc_bundle'] );
		$tot[ $bid ] = ( $tot[ $bid ] ?? 0 ) + (float) $it['line_total'] * (int) $off / 100;
	}
	$sum = array_sum( $tot );
	if ( $sum > 0 ) { $cart->add_fee( 'বান্ডেল ছাড়', -round( $sum ), false ); }
} );

/* ---------- ডিলার মোড ---------- */
function hitscam_dealer_active() {
	if ( is_user_logged_in() && get_user_meta( get_current_user_id(), 'hc_dealer', true ) ) { return true; }
	return WC()->session && WC()->session->get( 'hc_dealer' );
}
add_shortcode( 'hitscam_dealer', function () {
	$pct = (int) hitscam_opt( 'dealer_pct', 4 );
	$on  = hitscam_is_wc() && WC()->session && hitscam_dealer_active();
	ob_start(); ?>
	<div data-dealer-page style="max-width:760px">
	<div class="panel mb-3"><h2 class="h6 fw-bold mb-2"><i class="bi bi-boxes text-brand"></i> বাল্ক ছাড় (সবার জন্য)</h2><ul class="mb-0"><?php foreach ( hitscam_bulk_tiers() as $t ) { echo '<li>একই পণ্য ' . esc_html( hitscam_bn_digits( $t[0] ) ) . '+ পিস নিলে ' . esc_html( hitscam_bn_digits( $t[1] ) ) . '% ছাড়</li>'; } ?></ul></div>
	<div class="panel"><h2 class="h6 fw-bold mb-2"><i class="bi bi-patch-check text-brand"></i> ডিলার মোড</h2>
	<p class="small text-muted">ডিলার কোড থাকলে বাল্ক ছাড়ের উপর অতিরিক্ত <?php echo esc_html( hitscam_bn_digits( $pct ) ); ?>% ছাড় পাবেন।</p>
	<div id="dealerState" role="status"><?php echo $on ? '<span class="badge bg-success">ডিলার মোড চালু</span>' : ''; ?></div>
	<form id="dealerForm" class="d-flex gap-2 my-2" novalidate <?php echo $on ? 'hidden' : ''; ?>><input class="form-control" name="code" placeholder="ডিলার কোড" aria-label="ডিলার কোড" autocomplete="off" autocapitalize="characters"><button class="btn btn-brand" type="submit">চালু করুন</button></form>
	<div class="small text-danger" id="dealerMsg" role="alert"></div>
	<button class="btn btn-soft btn-sm" id="dealerOff" type="button" <?php echo $on ? '' : 'hidden'; ?>>ডিলার মোড বন্ধ করুন</button></div></div>
	<?php return ob_get_clean();
} );
add_action( 'wp_ajax_hc_dealer', 'hitscam_ajax_dealer' );
add_action( 'wp_ajax_nopriv_hc_dealer', 'hitscam_ajax_dealer' );
function hitscam_ajax_dealer() {
	check_ajax_referer( 'hitscam', 'nonce' );
	if ( ! WC()->session ) { wp_send_json_error( array( 'msg' => 'সেশন নেই' ) ); }
	if ( ! empty( $_POST['off'] ) ) { WC()->session->set( 'hc_dealer', 0 ); if ( is_user_logged_in() ) { delete_user_meta( get_current_user_id(), 'hc_dealer' ); } wp_send_json_success(); }
	$codes = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) hitscam_opt( 'dealer_codes', '' ) ) ) ) );
	$code  = strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ) ) );
	$key   = 'hc_dealer_try_' . md5( WC_Geolocation::get_ip_address() );
	$n     = (int) get_transient( $key ); set_transient( $key, $n + 1, 600 );
	if ( $n >= 8 ) { wp_send_json_error( array( 'msg' => 'অনেকবার ভুল — ১০ মিনিট পরে চেষ্টা করুন।' ) ); }
	if ( ! $codes || ! in_array( $code, $codes, true ) ) { wp_send_json_error( array( 'msg' => 'কোডটি সঠিক নয়।' ) ); }
	WC()->session->set( 'hc_dealer', 1 );
	if ( is_user_logged_in() ) { update_user_meta( get_current_user_id(), 'hc_dealer', 1 ); }
	wp_send_json_success();
}
add_action( 'woocommerce_cart_calculate_fees', function ( WC_Cart $cart ) {
	if ( ( is_admin() && ! wp_doing_ajax() ) || ! hitscam_dealer_active() ) { return; }
	$pct = (int) hitscam_opt( 'dealer_pct', 4 );
	if ( $pct > 0 && $cart->get_subtotal() > 0 ) { $cart->add_fee( 'ডিলার ছাড় (' . $pct . '%)', -round( $cart->get_subtotal() * $pct / 100 ), false ); }
}, 30 );

/* ---------- ওয়ারেন্টি চেক ---------- */
add_shortcode( 'hitscam_warranty', function () {
	ob_start(); ?>
	<div class="row g-3"><div class="col-lg-5"><div class="panel"><form id="wtyForm" novalidate>
		<label class="form-label fw-semibold" for="wtyOrder">অর্ডার নাম্বার</label><input class="form-control mb-2" id="wtyOrder" inputmode="numeric" placeholder="যেমন 1234" autocomplete="off" required>
		<label class="form-label fw-semibold" for="wtyPhone">অর্ডারের ফোন নাম্বার</label><input class="form-control" id="wtyPhone" inputmode="tel" placeholder="01XXXXXXXXX" autocomplete="tel" required>
		<button class="btn btn-brand w-100 mt-3" type="submit"><i class="bi bi-shield-check"></i> ওয়ারেন্টি দেখুন</button></form></div></div>
	<div class="col-lg-7"><div id="wtyRes" aria-live="polite"></div></div></div>
	<?php return ob_get_clean();
} );
add_action( 'wp_ajax_hc_warranty', 'hitscam_ajax_warranty' );
add_action( 'wp_ajax_nopriv_hc_warranty', 'hitscam_ajax_warranty' );
function hitscam_find_order( $num, $phone ) {
	$num = absint( $num ); $ph = hitscam_normalize_bd_phone( $phone );
	$o   = $num ? wc_get_order( $num ) : null;
	if ( ! $o || ! $ph || hitscam_normalize_bd_phone( $o->get_billing_phone() ) !== $ph ) { return null; }
	return $o;
}
function hitscam_ajax_warranty() {
	check_ajax_referer( 'hitscam', 'nonce' );
	$key = 'hc_wty_' . md5( WC_Geolocation::get_ip_address() ); $n = (int) get_transient( $key ); set_transient( $key, $n + 1, 600 );
	if ( $n >= 10 ) { wp_send_json_error( array( 'msg' => 'অনেকবার চেষ্টা — কিছুক্ষণ পরে আসুন।' ) ); }
	$o = hitscam_find_order( wp_unslash( $_POST['order'] ?? '' ), wp_unslash( $_POST['phone'] ?? '' ) );
	if ( ! $o ) { wp_send_json_error( array( 'msg' => 'অর্ডার নাম্বার ও ফোন মিলছে না।' ) ); }
	if ( ! in_array( $o->get_status(), array( 'completed', 'processing' ), true ) ) { wp_send_json_error( array( 'msg' => 'অর্ডারটি এখনো ডেলিভারি হয়নি।' ) ); }
	$start = $o->get_date_completed() ?: $o->get_date_created(); $rows = array();
	foreach ( $o->get_items() as $it ) {
		$p = $it->get_product(); $m = (int) ( $p ? ( $p->get_meta( '_hc_warranty_months' ) ?: hitscam_opt( 'warranty_m', 12 ) ) : hitscam_opt( 'warranty_m', 12 ) );
		$end = ( clone $start )->modify( "+$m months" ); $left = (int) floor( ( $end->getTimestamp() - time() ) / DAY_IN_SECONDS );
		$rows[] = array( 'name' => $it->get_name(), 'months' => $m, 'end' => wp_date( 'd M Y', $end->getTimestamp() ), 'active' => $left >= 0, 'left' => max( 0, $left ) );
	}
	wp_send_json_success( array( 'rows' => $rows, 'wa' => preg_replace( '/\D/', '', hitscam_opt( 'whatsapp', '8801953073445' ) ) ) );
}
// পণ্যে ওয়ারেন্টি মাস (দ্রুত এডিট: জেনারেল ট্যাব)
add_action( 'woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_text_input( array( 'id' => '_hc_warranty_months', 'label' => 'ওয়ারেন্টি (মাস)', 'type' => 'number', 'desc_tip' => true, 'description' => 'খালি থাকলে ডিফল্ট (Customizer)' ) );
} );
add_action( 'woocommerce_process_product_meta', function ( $id ) {
	if ( isset( $_POST['_hc_warranty_months'] ) ) { update_post_meta( $id, '_hc_warranty_months', absint( $_POST['_hc_warranty_months'] ) ?: '' ); } // phpcs:ignore
} );
