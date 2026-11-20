<?php
/**
 * e2 — সেকশন প্লেসমেন্ট ম্যানেজার: কোন পেজে কোন সেকশন (নতুন/বেস্ট সেলার/ফ্ল্যাশ সেল/রিলেটেড/সম্প্রতি দেখা/…), কন্ডিশন সহ।
 * নতুন সেকশন:   add_filter('hitscam_sections', fn($s)=>$s+['my'=>['label'=>'…','title'=>'…','render'=>callable($row,$ctx)]]);
 * নতুন কন্ডিশন: add_filter('hitscam_conditions', fn($c)=>$c+['vip'=>['label'=>'…','check'=>callable($ctx)]]);
 * নতুন পেজ:     add_filter('hitscam_place_pages', …) এবং টেমপ্লেটে do_action('hitscam_place','page-key').
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

function hitscam_cards_grid( array $products, $cols = 'col-12 col-sm-6 col-md-4 col-lg-3' ) {
	global $post, $product;
	$keep = array( $post, $product );
	echo '<div class="row g-3">';
	foreach ( $products as $p ) {
		if ( ! $p || ! $p->is_visible() ) { continue; }
		$post    = get_post( $p->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$product = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		echo '<div class="' . esc_attr( $cols ) . '">';
		wc_get_template_part( 'content', 'product' );
		echo '</div>';
	}
	echo '</div>';
	list( $post, $product ) = $keep; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	wp_reset_postdata();
}

function hitscam_sections_registry() {
	$q = function ( $args ) {
		return function ( $row, $ctx ) use ( $args ) {
			$a = array_merge( array( 'limit' => max( 1, (int) $row['count'] ), 'status' => 'publish', 'stock_status' => 'instock', 'visibility' => 'catalog', 'exclude' => $ctx['shown'] ), $args );
			if ( ! empty( $row['cat'] ) ) { $a['category'] = array_map( 'trim', explode( ',', $row['cat'] ) ); }
			return wc_get_products( $a );
		};
	};
	$reg = array(
		'new'      => array( 'label' => 'New arrivals (নতুন)', 'title' => 'New arrivals', 'query' => $q( array( 'orderby' => 'date', 'order' => 'DESC' ) ) ),
		'best'     => array( 'label' => 'Best sellers', 'title' => 'Best sellers', 'query' => $q( array( 'orderby' => 'popularity' ) ) ),
		'trending' => array( 'label' => 'ট্রেন্ডিং', 'title' => 'ট্রেন্ডিং', 'query' => $q( array( 'orderby' => 'popularity' ) ) ),
		'flash'    => array( 'label' => 'Flash sale (অফার)', 'title' => 'Flash sale', 'query' => function ( $row, $ctx ) { $ids = array_slice( wc_get_product_ids_on_sale(), 0, 40 ); return $ids ? wc_get_products( array( 'include' => $ids, 'exclude' => $ctx['shown'], 'limit' => max( 1, (int) $row['count'] ), 'status' => 'publish', 'stock_status' => 'instock' ) ) : array(); } ),
		'rated'    => array( 'label' => 'টপ রেটেড', 'title' => 'টপ রেটেড', 'query' => $q( array( 'orderby' => 'rating' ) ) ),
		'featured' => array( 'label' => 'ফিচার্ড পণ্য', 'title' => 'ফিচার্ড', 'query' => $q( array( 'featured' => true ) ) ),
		'category' => array( 'label' => 'নির্দিষ্ট ক্যাটাগরি (স্লাগ "ক্যাটাগরি" ঘরে দিন)', 'title' => 'ক্যাটাগরি পণ্য', 'query' => $q( array( 'orderby' => 'date' ) ) ),
		'cross'    => array( 'label' => 'আপনার পছন্দ হতে পারে (আপসেল/একই ক্যাটাগরি) — শুধু product', 'title' => 'আপনার পছন্দ হতে পারে', 'query' => function ( $row, $ctx ) {
			$p = $ctx['product_obj'] ?? null; if ( ! $p ) { return array(); }
			$list = array_filter( array_map( 'wc_get_product', $p->get_upsell_ids() ) );
			if ( ! $list && $p->get_category_ids() ) { $list = wc_get_products( array( 'limit' => (int) $row['count'], 'category' => array( get_term( $p->get_category_ids()[0] )->slug ), 'exclude' => array_merge( array( $p->get_id() ), $ctx['shown'] ), 'status' => 'publish', 'orderby' => 'popularity', 'stock_status' => 'instock' ) ); }
			return array_slice( array_values( $list ), 0, (int) $row['count'] );
		} ),
		'related'  => array( 'label' => 'Related Products — শুধু product', 'title' => 'Related Products', 'query' => function ( $row, $ctx ) {
			if ( empty( $ctx['product'] ) ) { return array(); }
			return array_slice( array_values( array_filter( array_map( 'wc_get_product', wc_get_related_products( $ctx['product'], 12, array_merge( array( $ctx['product'] ), $ctx['shown'] ) ) ) ) ), 0, (int) $row['count'] );
		} ),
		'recent'   => array( 'label' => 'সম্প্রতি দেখা পণ্য (ব্রাউজার-ভিত্তিক)', 'title' => 'সম্প্রতি দেখা পণ্য', 'html' => function ( $row ) { return '<div class="recent-wrap mb-4" id="recentWrap" style="display:none"><div class="section-head"><h2>' . esc_html( $row['title'] ) . '</h2></div><div class="row g-3" id="secRecent"></div></div>'; } ),
		'also'     => array( 'label' => 'অন্য ক্রেতারা এগুলোও দেখেছেন — শুধু product', 'title' => 'অন্য ক্রেতারা এগুলোও দেখেছেন', 'html' => function ( $row ) { return '<div class="hc-coview-slot mb-4" data-title="' . esc_attr( $row['title'] ) . '"></div>'; } ),
	);
	return apply_filters( 'hitscam_sections', $reg );
}

function hitscam_place_pages() {
	return apply_filters( 'hitscam_place_pages', array( 'home' => 'হোম', 'shop' => 'শপ', 'category' => 'ক্যাটাগরি আর্কাইভ', 'product' => 'প্রোডাক্ট পেজ', 'cart' => 'কার্ট', 'checkout' => 'চেকআউট', 'thankyou' => 'অর্ডার সম্পন্ন', 'blog' => 'গাইড/ব্লগ', 'account' => 'অ্যাকাউন্ট' ) );
}

function hitscam_conditions_registry() {
	return apply_filters( 'hitscam_conditions', array(
		'always'     => array( 'label' => 'সবসময়', 'check' => '__return_true' ),
		'logged_in'  => array( 'label' => 'লগইন করা ইউজার', 'check' => function () { return is_user_logged_in(); } ),
		'guest'      => array( 'label' => 'গেস্ট', 'check' => function () { return ! is_user_logged_in(); } ),
		'cart_has'   => array( 'label' => 'কার্টে পণ্য আছে', 'check' => function () { return WC()->cart && ! WC()->cart->is_empty(); } ),
		'cart_empty' => array( 'label' => 'কার্ট ফাঁকা', 'check' => function () { return ! WC()->cart || WC()->cart->is_empty(); } ),
		'has_sale'   => array( 'label' => 'অফারে পণ্য আছে', 'check' => function () { return (bool) wc_get_product_ids_on_sale(); } ),
		'mobile'     => array( 'label' => 'মোবাইল ডিভাইস', 'check' => 'wp_is_mobile' ),
		'hours_open' => array( 'label' => 'অফিস টাইমে', 'check' => function () { $h = (int) current_time( 'G' ); return $h >= (int) get_option( 'hitscam_hours_open', 10 ) && $h < (int) get_option( 'hitscam_hours_close', 22 ); } ),
	) );
}

function hitscam_placements_default() {
	return array(
		array( 'section' => 'trending', 'page' => 'home', 'title' => 'ট্রেন্ডিং', 'count' => 4, 'order' => 10, 'on' => 1 ),
		array( 'section' => 'new', 'page' => 'home', 'title' => 'নতুন এসেছে', 'count' => 4, 'order' => 20, 'on' => 1 ),
		array( 'section' => 'flash', 'page' => 'home', 'title' => 'অফার', 'count' => 4, 'order' => 30, 'on' => 1 ),
		array( 'section' => 'cross', 'page' => 'product', 'title' => 'আপনার পছন্দ হতে পারে', 'count' => 4, 'order' => 10, 'on' => 1 ),
		array( 'section' => 'recent', 'page' => 'product', 'title' => 'সম্প্রতি দেখা পণ্য', 'count' => 4, 'order' => 20, 'on' => 1 ),
		array( 'section' => 'also', 'page' => 'product', 'title' => 'অন্য ক্রেতারা এগুলোও দেখেছেন', 'count' => 4, 'order' => 30, 'on' => 1 ),
		array( 'section' => 'related', 'page' => 'product', 'title' => 'Related Products', 'count' => 4, 'order' => 40, 'on' => 1 ),
		array( 'section' => 'best', 'page' => 'cart', 'title' => 'Best sellers', 'count' => 4, 'order' => 10, 'on' => 1 ),
		array( 'section' => 'recent', 'page' => 'shop', 'title' => 'সম্প্রতি দেখা পণ্য', 'count' => 4, 'order' => 10, 'on' => 1 ),
	);
}

function hitscam_placements() {
	$p = get_option( 'hitscam_placements' );
	return is_array( $p ) ? $p : hitscam_placements_default();
}

function hitscam_place_context( $page ) {
	static $shown = array();
	$ctx = array( 'page' => $page, 'shown' => &$shown, 'cats' => array(), 'product' => 0, 'product_obj' => null );
	if ( 'product' === $page && is_singular( 'product' ) ) {
		$ctx['product']     = get_the_ID();
		$ctx['product_obj'] = wc_get_product( $ctx['product'] );
		$shown[]            = $ctx['product'];
		$ctx['cats']        = wp_get_post_terms( $ctx['product'], 'product_cat', array( 'fields' => 'slugs' ) );
	} elseif ( 'category' === $page && is_product_category() ) {
		$ctx['cats'] = array( get_queried_object()->slug );
	} elseif ( in_array( $page, array( 'cart', 'checkout' ), true ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $it ) {
			$ctx['cats'] = array_merge( $ctx['cats'], wp_get_post_terms( $it['product_id'], 'product_cat', array( 'fields' => 'slugs' ) ) );
		}
	}
	return $ctx;
}

function hitscam_placement_ok( $row, $ctx ) {
	$reg  = hitscam_conditions_registry();
	$cond = $row['cond'] ?? 'always';
	if ( isset( $reg[ $cond ] ) && ! call_user_func( $reg[ $cond ]['check'], $ctx ) ) { return false; }
	if ( ! empty( $row['only_cats'] ) ) { // শুধু এই ক্যাটাগরিগুলোর প্রসঙ্গে (পণ্য/আর্কাইভ/কার্ট)
		if ( ! array_intersect( array_map( 'trim', explode( ',', $row['only_cats'] ) ), $ctx['cats'] ) ) { return false; }
	}
	$tot = WC()->cart ? (float) WC()->cart->get_subtotal() : 0;
	if ( ! empty( $row['min_cart'] ) && $tot < (float) $row['min_cart'] ) { return false; }
	if ( ! empty( $row['max_cart'] ) && $tot > (float) $row['max_cart'] ) { return false; }
	return (bool) apply_filters( 'hitscam_placement_ok', true, $row, $ctx );
}

function hitscam_place( $page ) {
	if ( ! function_exists( 'wc_get_products' ) ) { return; }
	$rows = array_filter( hitscam_placements(), fn( $r ) => ( $r['page'] ?? '' ) === $page && ! empty( $r['on'] ) );
	usort( $rows, fn( $a, $b ) => (int) $a['order'] <=> (int) $b['order'] );
	$reg = hitscam_sections_registry();
	$ctx = hitscam_place_context( $page );
	foreach ( $rows as $row ) {
		$def = $reg[ $row['section'] ] ?? null;
		if ( ! $def || ! hitscam_placement_ok( $row, $ctx ) ) { continue; }
		$row['title'] = '' !== trim( (string) ( $row['title'] ?? '' ) ) ? $row['title'] : $def['title'];
		$row['count'] = max( 1, (int) ( $row['count'] ?? 4 ) );
		if ( isset( $def['html'] ) ) { echo call_user_func( $def['html'], $row, $ctx ); continue; } // phpcs:ignore
		$list = isset( $def['query'] ) ? call_user_func( $def['query'], $row, $ctx ) : ( isset( $def['render'] ) ? null : array() );
		if ( isset( $def['render'] ) ) { call_user_func( $def['render'], $row, $ctx ); continue; }
		$list = array_values( array_filter( (array) $list ) );
		if ( ! $list ) { continue; }
		foreach ( $list as $p ) { $ctx['shown'][] = $p->get_id(); }
		$more = in_array( $page, array( 'home' ), true ) ? '<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">সব দেখুন →</a>' : '';
		$wrap = in_array( $page, array( 'home' ), true ) ? 'container section' : 'hc-place mb-4';
		echo '<section class="' . esc_attr( $wrap ) . ' hc-sec-' . esc_attr( $row['section'] ) . '"><div class="section-head"><h2>' . esc_html( $row['title'] ) . '</h2>' . $more . '</div>'; // phpcs:ignore
		hitscam_cards_grid( $list );
		echo '</section>';
	}
}
add_action( 'hitscam_place', 'hitscam_place' );

// কার্ট / চেকআউট
add_action( 'woocommerce_after_cart', function () { echo '<div class="container">'; hitscam_place( 'cart' ); echo '</div>'; } );
add_action( 'woocommerce_after_checkout_form', function () { echo '<div class="container">'; hitscam_place( 'checkout' ); echo '</div>'; }, 20 );

/* ---------- অ্যাডমিন UI ---------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'সেকশন প্লেসমেন্ট', 'সেকশন প্লেসমেন্ট', 'manage_woocommerce', 'hitscam-sections', 'hitscam_e2_admin', 10 );
}, 20 );

function hitscam_e2_admin() {
	$reg   = hitscam_sections_registry();
	$pages = hitscam_place_pages();
	$conds = hitscam_conditions_registry();
	if ( isset( $_POST['hc_e2_save'] ) && check_admin_referer( 'hc_e2' ) ) {
		$out = array();
		foreach ( (array) ( $_POST['r'] ?? array() ) as $r ) {
			$r = wp_unslash( $r );
			if ( empty( $r['section'] ) || isset( $r['del'] ) ) { continue; }
			$out[] = array(
				'section'   => sanitize_key( $r['section'] ), 'page' => sanitize_key( $r['page'] ?? 'home' ), 'title' => sanitize_text_field( $r['title'] ?? '' ),
				'count'     => max( 1, min( 12, (int) ( $r['count'] ?? 4 ) ) ), 'order' => (int) ( $r['order'] ?? 10 ), 'on' => empty( $r['on'] ) ? 0 : 1,
				'cat'       => sanitize_text_field( $r['cat'] ?? '' ), 'cond' => sanitize_key( $r['cond'] ?? 'always' ), 'only_cats' => sanitize_text_field( $r['only_cats'] ?? '' ),
				'min_cart'  => (float) ( $r['min_cart'] ?? 0 ), 'max_cart' => (float) ( $r['max_cart'] ?? 0 ),
			);
		}
		update_option( 'hitscam_placements', $out );
		echo '<div class="notice notice-success"><p>সেভ হয়েছে।</p></div>';
	}
	if ( isset( $_POST['hc_e2_reset'] ) && check_admin_referer( 'hc_e2' ) ) { delete_option( 'hitscam_placements' ); echo '<div class="notice notice-success"><p>ডিফল্টে ফেরানো হয়েছে।</p></div>'; }
	$rows   = hitscam_placements();
	$rows[] = array( 'section' => '', 'page' => 'home', 'title' => '', 'count' => 4, 'order' => 50, 'on' => 1 ); // নতুন রো
	$sel = function ( $name, $opts, $cur ) { $h = '<select name="' . esc_attr( $name ) . '">'; foreach ( $opts as $k => $v ) { $h .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( is_array( $v ) ? $v['label'] : $v ) . '</option>'; } return $h . '</select>'; };
	echo '<div class="wrap"><h1>সেকশন প্লেসমেন্ট</h1><p>কোন পেজে কোন সেকশন, কতগুলো পণ্য, কী ক্রমে ও কোন কন্ডিশনে দেখাবে তা এখানে ঠিক করুন। শেষের ফাঁকা রো-তে সেকশন বেছে সেভ করলে নতুন রো যোগ হয়; রো মুছতে "মুছুন" টিক দিন। কাস্টম সেকশন/কন্ডিশন কোড থেকে ফিল্টারে যোগ করা যায় (<code>hitscam_sections</code>, <code>hitscam_conditions</code>)।</p><form method="post">';
	wp_nonce_field( 'hc_e2' );
	echo '<table class="widefat striped" style="max-width:1300px"><thead><tr><th>চালু</th><th>সেকশন</th><th>পেজ</th><th>শিরোনাম</th><th>সংখ্যা</th><th>ক্রম</th><th>ক্যাটাগরি স্লাগ</th><th>কন্ডিশন</th><th>শুধু এই ক্যাটাগরি প্রসঙ্গে</th><th>কার্ট ≥</th><th>কার্ট ≤</th><th>মুছুন</th></tr></thead><tbody>';
	foreach ( $rows as $i => $r ) {
		$n = 'r[' . $i . ']';
		echo '<tr><td><input type="checkbox" name="' . $n . '[on]" value="1" ' . checked( ! empty( $r['on'] ), true, false ) . '></td><td>' . $sel( $n . '[section]', array( '' => '— নতুন —' ) + $reg, $r['section'] ) . '</td><td>' . $sel( $n . '[page]', $pages, $r['page'] ) . '</td>'; // phpcs:ignore
		echo '<td><input name="' . $n . '[title]" value="' . esc_attr( $r['title'] ?? '' ) . '" style="width:150px"></td><td><input type="number" name="' . $n . '[count]" value="' . (int) ( $r['count'] ?? 4 ) . '" min="1" max="12" style="width:55px"></td><td><input type="number" name="' . $n . '[order]" value="' . (int) ( $r['order'] ?? 10 ) . '" style="width:60px"></td>'; // phpcs:ignore
		echo '<td><input name="' . $n . '[cat]" value="' . esc_attr( $r['cat'] ?? '' ) . '" style="width:110px"></td><td>' . $sel( $n . '[cond]', $conds, $r['cond'] ?? 'always' ) . '</td><td><input name="' . $n . '[only_cats]" value="' . esc_attr( $r['only_cats'] ?? '' ) . '" style="width:120px"></td><td><input type="number" name="' . $n . '[min_cart]" value="' . esc_attr( $r['min_cart'] ?? '' ) . '" style="width:70px"></td><td><input type="number" name="' . $n . '[max_cart]" value="' . esc_attr( $r['max_cart'] ?? '' ) . '" style="width:70px"></td><td><input type="checkbox" name="' . $n . '[del]" value="1"></td></tr>'; // phpcs:ignore
	}
	echo '</tbody></table><p><button class="button button-primary" name="hc_e2_save" value="1">সেভ</button> <button class="button" name="hc_e2_reset" value="1" onclick="return confirm(\'ডিফল্টে ফিরবেন?\')">ডিফল্টে ফিরুন</button></p></form></div>';
}
