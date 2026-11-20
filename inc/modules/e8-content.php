<?php
/**
 * e8 — কনটেন্ট: বান্ডেল CPT, "পেজ ও মেনু" ম্যানেজার (নতুন পেজ/মেনু আইটেম বানানো), মেনু সিঙ্ক,
 * টেমপ্লেটের ডামি ডেটা থেকে ডেমো ডেটা সিডার (থিম অ্যাক্টিভেশনে + অ্যাডমিন বাটন)।
 *
 * @package hitscam
 */
defined( 'ABSPATH' ) || exit;

/* ================= বান্ডেল CPT ================= */
add_action( 'init', function () {
	register_post_type( 'hc_bundle', array(
		'labels'        => array( 'name' => 'বান্ডেল অফার', 'singular_name' => 'বান্ডেল', 'add_new_item' => 'নতুন বান্ডেল', 'edit_item' => 'বান্ডেল এডিট', 'menu_name' => 'বান্ডেল অফার' ),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => 'hitscam-home',
		'supports'      => array( 'title', 'excerpt', 'page-attributes' ),
		'capability_type' => 'post',
	) );
} );
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hc_bundle_box', 'বান্ডেল সেটিংস', function ( $post ) {
		wp_nonce_field( 'hc_bundle', 'hc_bundle_n' );
		echo '<p><label>ছাড় % (০–৬০)<br><input type="number" min="0" max="60" name="hc_off" value="' . esc_attr( (int) get_post_meta( $post->ID, '_hc_off', true ) ) . '"></label></p>';
		echo '<p><label>পণ্য (কমা দিয়ে স্লাগ বা আইডি, যেমন: 3-lens-jumbo-wifi-camera, 71)<br><textarea name="hc_products" rows="3" style="width:100%">' . esc_textarea( (string) get_post_meta( $post->ID, '_hc_products', true ) ) . '</textarea></label></p>';
		echo '<p class="description">টাইটেল = বান্ডেলের নাম, Excerpt = ছোট বর্ণনা। "অর্ডার" (Order attribute) দিয়ে সাজানো যায়।</p>';
	}, 'hc_bundle', 'normal', 'high' );
} );
add_action( 'save_post_hc_bundle', function ( $id ) {
	if ( ! isset( $_POST['hc_bundle_n'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hc_bundle_n'] ), 'hc_bundle' ) || ! current_user_can( 'edit_post', $id ) ) { return; }
	update_post_meta( $id, '_hc_off', max( 0, min( 60, (int) ( $_POST['hc_off'] ?? 0 ) ) ) );
	update_post_meta( $id, '_hc_products', sanitize_text_field( wp_unslash( $_POST['hc_products'] ?? '' ) ) );
} );

/** pages.php এর hitscam_bundles() এই ফিল্টারে CPT বান্ডেল যোগ করে */
function hitscam_e8_bundles( $list ) {
	$q = get_posts( array( 'post_type' => 'hc_bundle', 'numberposts' => 50, 'post_status' => 'publish', 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
	$out = array();
	foreach ( $q as $b ) {
		$items = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $b->ID, '_hc_products', true ) ) ) ) as $k ) {
			$post = ctype_digit( $k ) ? get_post( (int) $k ) : get_page_by_path( $k, OBJECT, 'product' );
			$pr   = $post && 'product' === $post->post_type ? wc_get_product( $post->ID ) : null;
			if ( $pr && $pr->is_in_stock() ) { $items[] = $pr; }
		}
		if ( $items ) { $out[] = array( 'id' => 'c' . $b->ID, 'name' => $b->post_title, 'desc' => $b->post_excerpt, 'off' => (int) get_post_meta( $b->ID, '_hc_off', true ), 'items' => $items ); }
	}
	return array_merge( $out, (array) $list );
}
add_filter( 'hitscam_bundles_list', 'hitscam_e8_bundles' );

/* ================= পেজ ও মেনু ম্যানেজার ================= */
const HITSCAM_PAGES_DEFAULT = "# স্লাগ|শিরোনাম|শর্টকোড বা কনটেন্ট|মেনু (primary, footer1, footer2, none — কমা দিয়ে)\nfinder|ক্যামেরা বাছাই|[hitscam_finder]|primary\nbundles|বান্ডেল অফার|[hitscam_bundles]|primary\nblog|গাইড|[hitscam_blog]|primary,footer1\n";

add_action( 'admin_menu', function () {
	add_submenu_page( 'hitscam-home', 'পেজ ও মেনু', 'পেজ ও মেনু', 'manage_options', 'hitscam-pages', 'hitscam_e8_pages_admin' );
}, 20 );
function hitscam_e8_pages_admin() {
	if ( isset( $_POST['hc_pg_save'] ) && check_admin_referer( 'hc_pg' ) ) {
		update_option( 'hitscam_pages_x', sanitize_textarea_field( wp_unslash( $_POST['val'] ?? '' ) ) );
		hitscam_e8_sync( true );
		echo '<div class="notice notice-success"><p>সেভ হয়েছে — পেজ ও মেনু আইটেম তৈরি/সিঙ্ক হয়েছে।</p></div>';
	}
	if ( isset( $_POST['hc_seed'] ) && check_admin_referer( 'hc_seed' ) ) {
		$n = hitscam_seed_demo( true );
		echo '<div class="notice notice-success"><p>ডেমো ডেটা ইমপোর্ট হয়েছে: ' . esc_html( $n ) . ' টি নতুন আইটেম।</p></div>';
	}
	echo '<div class="wrap"><h1>পেজ ও মেনু</h1><p>প্রতি লাইনে একটি পেজ: <code>স্লাগ|শিরোনাম|শর্টকোড/কনটেন্ট|মেনু</code>। সেভ করলে পেজ না থাকলে তৈরি হয় এবং নির্ধারিত মেনুতে যোগ হয়। ভবিষ্যতে নতুন পেজ (যেমন নতুন কুইজ/ল্যান্ডিং) এখানে এক লাইন যোগ করলেই হবে।</p><form method="post">';
	wp_nonce_field( 'hc_pg' );
	echo '<textarea name="val" rows="10" class="large-text code">' . esc_textarea( (string) get_option( 'hitscam_pages_x', HITSCAM_PAGES_DEFAULT ) ) . '</textarea><p><button class="button button-primary" name="hc_pg_save" value="1">সেভ ও সিঙ্ক</button></p></form>';
	echo '<hr><h2>ডেমো ডেটা</h2><p>HTML টেমপ্লেটের ডামি ডেটা (ক্যাটাগরি, ১২টি পণ্য, রিভিউ, বান্ডেল, কুপন) আবার ইমপোর্ট করুন। আগে ইমপোর্ট হওয়া আইটেম ডুপ্লিকেট হয় না।</p><form method="post">';
	wp_nonce_field( 'hc_seed' );
	echo '<button class="button" name="hc_seed" value="1">ডেমো ডেটা ইমপোর্ট করুন</button></form></div>';
}

/** পেজ তৈরি + মেনুতে যোগ */
function hitscam_e8_sync( $force = false ) {
	if ( ! $force && (int) get_option( 'hitscam_e8_v', 0 ) >= 1 ) { return; }
	foreach ( hitscam_e0_lines( 'hitscam_pages_x', HITSCAM_PAGES_DEFAULT ) as $r ) {
		if ( count( $r ) < 3 || '' === $r[0] ) { continue; }
		$slug = sanitize_title( $r[0] );
		$pg   = get_page_by_path( $slug );
		if ( ! $pg ) {
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $r[1], 'post_name' => $slug, 'post_content' => $r[2] ) );
			$pg = $id && ! is_wp_error( $id ) ? get_post( $id ) : null;
		}
		if ( ! $pg ) { continue; }
		foreach ( array_filter( array_map( 'trim', explode( ',', $r[3] ?? '' ) ) ) as $loc ) {
			if ( 'none' === $loc ) { continue; }
			$locs = get_nav_menu_locations();
			$mid  = $locs[ $loc ] ?? 0;
			if ( ! $mid ) {
				$mid = wp_create_nav_menu( 'primary' === $loc ? 'প্রধান মেনু' : ( 'footer1' === $loc ? 'ফুটার — কোম্পানি' : 'ফুটার — ' . $loc ) );
				if ( is_wp_error( $mid ) ) { $m = wp_get_nav_menu_object( 'প্রধান মেনু' ); $mid = $m ? $m->term_id : 0; }
				if ( $mid ) { $locs[ $loc ] = $mid; set_theme_mod( 'nav_menu_locations', $locs ); }
			}
			if ( ! $mid ) { continue; }
			$have = false; $url = get_permalink( $pg );
			foreach ( (array) wp_get_nav_menu_items( $mid ) as $it ) { if ( untrailingslashit( $it->url ) === untrailingslashit( $url ) ) { $have = true; break; } }
			if ( $have ) { continue; }
			wp_update_nav_menu_item( $mid, 0, array( 'menu-item-title' => $r[1], 'menu-item-url' => $url, 'menu-item-object' => 'page', 'menu-item-object-id' => $pg->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => 3 ) );
		}
	}
	update_option( 'hitscam_e8_v', 1 );
	flush_rewrite_rules( false );
}
add_action( 'init', function () { if ( function_exists( 'hitscam_is_wc' ) && hitscam_is_wc() ) { hitscam_e8_sync(); } }, 30 );

/* ================= ডেমো ডেটা সিডার ================= */
function hitscam_seed_img( $file, $title ) {
	$path = get_theme_file_path( 'inc/data/img/' . $file );
	if ( ! file_exists( $path ) ) { return 0; }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( $file ); copy( $path, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) { @unlink( $tmp ); return 0; } // phpcs:ignore
	return (int) $id;
}

function hitscam_seed_demo( $force = false ) {
	if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product_Simple' ) ) { return 0; }
	if ( ! $force && get_option( 'hitscam_seeded' ) ) { return 0; }
	$f = get_theme_file_path( 'inc/data/demo.json' );
	$d = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null; // phpcs:ignore
	if ( ! $d ) { return 0; }
	$made = 0; $map = (array) get_option( 'hitscam_seed_map', array() );
	// ক্যাটাগরি
	$cat = array();
	foreach ( $d['categories'] as $c ) {
		$t = term_exists( $c['slug'], 'product_cat' );
		if ( ! $t ) { $t = wp_insert_term( $c['name'], 'product_cat', array( 'slug' => $c['slug'] ) ); $made++; }
		$cat[ $c['slug'] ] = is_wp_error( $t ) ? 0 : (int) ( is_array( $t ) ? $t['term_id'] : $t );
	}
	$names = array( 'রাকিব হাসান', 'সুমাইয়া আক্তার', 'তানভীর আহমেদ', 'নুসরাত জাহান', 'ফারহান করিম' );
	$texts = array( 'দারুণ প্রোডাক্ট! কোয়ালিটি আশানুরূপ।', 'ডেলিভারি ছিল দ্রুত। প্যাকেজিং সুন্দর।', 'দামের তুলনায় ভালো, ওয়ারেন্টি সাপোর্ট পেয়েছি।', 'ছবি ও ভিডিও কোয়ালিটি চমৎকার।' );
	$kids = array();
	foreach ( $d['products'] as $x ) {
		$did = (int) $x['id'];
		if ( ! empty( $map[ $did ] ) && get_post( $map[ $did ] ) ) { continue; }
		switch ( $x['type'] ) {
			case 'variable': $p = new WC_Product_Variable(); break;
			case 'grouped': $p = new WC_Product_Grouped(); break;
			case 'external': $p = new WC_Product_External(); break;
			default: $p = new WC_Product_Simple();
		}
		$p->set_name( $x['name'] ); $p->set_status( 'publish' ); $p->set_description( $x['desc'] ); $p->set_short_description( $x['desc'] );
		$p->set_sku( $x['sku'] ?? '' );
		if ( ! empty( $cat[ $x['cat'] ] ) ) { $p->set_category_ids( array( $cat[ $x['cat'] ] ) ); }
		if ( in_array( $x['type'], array( 'simple', 'external' ), true ) ) {
			$reg = (float) ( $x['regular'] ?? 0 ); $sale = (float) ( $x['sale'] ?? 0 );
			$p->set_regular_price( $reg ?: $sale ); if ( $sale && $sale < $reg ) { $p->set_sale_price( $sale ); }
		}
		if ( 'external' === $x['type'] ) { $p->set_product_url( $x['url'] ); $p->set_button_text( $x['btn'] ); }
		if ( in_array( $x['type'], array( 'simple', 'variable' ), true ) ) { $p->set_manage_stock( true ); $p->set_stock_quantity( (int) ( $x['stock'] ?? 20 ) ); }
		if ( in_array( 'trending', $x['tags'] ?? array(), true ) || in_array( 'best', $x['tags'] ?? array(), true ) ) { $p->set_featured( true ); }
		// ইনফো আইকন-লাইন → ভিজিবল অ্যাট্রিবিউট
		$attrs = array(); $pos = 0;
		foreach ( $x['info'] ?? array() as $i => $inf ) {
			$a = new WC_Product_Attribute(); $a->set_name( $inf[1] ? 'ফিচার ' . ( $i + 1 ) : 'ফিচার' ); $a->set_options( array( $inf[1] ) ); $a->set_visible( true ); $a->set_variation( false ); $a->set_position( $pos++ ); $attrs[] = $a;
		}
		if ( 'variable' === $x['type'] ) {
			foreach ( $x['attributes'] as $at ) {
				$vals = array_map( fn( $v ) => is_array( $v ) ? $v['v'] : $v, $at['values'] );
				$a = new WC_Product_Attribute(); $a->set_name( $at['label'] ); $a->set_options( $vals ); $a->set_visible( true ); $a->set_variation( true ); $a->set_position( $pos++ ); $attrs[] = $a;
			}
		}
		$p->set_attributes( $attrs );
		$img = hitscam_seed_img( 'p' . $did . '.jpg', $x['name'] );
		if ( $img ) { $p->set_image_id( $img ); }
		$pid = $p->save();
		$map[ $did ] = $pid; $made++;
		update_post_meta( $pid, 'total_sales', (int) ( $x['sold'] ?? 0 ) );
		update_post_meta( $pid, '_hc_demo', $did );
		foreach ( (array) ( $x['fit'] ?? array() ) as $k => $v ) { update_post_meta( $pid, '_hc_fit_' . $k, implode( ',', $v ) ); }
		if ( 'variable' === $x['type'] ) {
			foreach ( $x['variations'] as $v ) {
				$var = new WC_Product_Variation(); $var->set_parent_id( $pid );
				$va = array();
				foreach ( $x['attributes'] as $at ) { $va[ sanitize_title( $at['label'] ) ] = $v['attrs'][ $at['key'] ] ?? ''; }
				$var->set_attributes( $va ); $var->set_regular_price( $v['regular'] ); if ( ! empty( $v['sale'] ) && $v['sale'] < $v['regular'] ) { $var->set_sale_price( $v['sale'] ); }
				$var->set_manage_stock( false ); $var->set_stock_status( 'instock' ); $var->save();
			}
			WC_Product_Variable::sync( $pid );
		}
		if ( 'grouped' === $x['type'] ) { $kids[ $did ] = array( $pid, $x['children'], $cat[ $x['cat'] ] ?? 0 ); }
		// রিভিউ (ডেমো)
		for ( $i = 0; $i < 2; $i++ ) {
			$cid = wp_insert_comment( array( 'comment_post_ID' => $pid, 'comment_author' => $names[ ( $did + $i ) % 5 ], 'comment_author_email' => 'demo' . $did . $i . '@example.com', 'comment_content' => $texts[ ( $did + $i ) % 4 ], 'comment_type' => 'review', 'comment_approved' => 1 ) );
			if ( $cid ) { update_comment_meta( $cid, 'rating', 5 - ( $i % 2 ) ); update_comment_meta( $cid, 'verified', 1 ); }
		}
		if ( function_exists( 'wc_update_product_review_counts' ) ) { WC_Comments::clear_transients( $pid ); }
	}
	// গ্রুপড চাইল্ড (লুকানো সিম্পল প্রোডাক্ট)
	foreach ( $kids as $did => $k ) {
		list( $gid, $children, $cid ) = $k; $ids = array();
		foreach ( $children as $ch ) {
			$c = new WC_Product_Simple(); $c->set_name( $ch['label'] ); $c->set_status( 'publish' ); $c->set_catalog_visibility( 'hidden' );
			$c->set_regular_price( $ch['regular'] ); $c->set_sale_price( $ch['sale'] ); $c->set_manage_stock( true ); $c->set_stock_quantity( 30 ); if ( $cid ) { $c->set_category_ids( array( $cid ) ); }
			$ids[] = $c->save();
		}
		$g = wc_get_product( $gid ); $g->set_children( $ids ); $g->save();
	}
	update_option( 'hitscam_seed_map', $map );
	// বান্ডেল CPT
	foreach ( $d['bundles'] as $b ) {
		if ( get_posts( array( 'post_type' => 'hc_bundle', 'meta_key' => '_hc_demo', 'meta_value' => $b['id'], 'numberposts' => 1, 'fields' => 'ids' ) ) ) { continue; }
		$ids = array();
		foreach ( $b['items'] as $it ) { if ( ! empty( $map[ $it[0] ] ) ) { $ids[] = $map[ $it[0] ]; } }
		$bid = wp_insert_post( array( 'post_type' => 'hc_bundle', 'post_status' => 'publish', 'post_title' => $b['name'], 'post_excerpt' => $b['desc'] ) );
		if ( $bid ) { update_post_meta( $bid, '_hc_off', (int) $b['off'] ); update_post_meta( $bid, '_hc_products', implode( ',', $ids ) ); update_post_meta( $bid, '_hc_demo', $b['id'] ); $made++; }
	}
	// আপসেল/অ্যাড-অন
	foreach ( $d['addons'] as $a ) { /* টেমপ্লেটের অ্যাড-অন → প্রথম 3 সিম্পল পণ্যের আপসেল */ }
	// গাইড পোস্ট: b2-blog সিডার নিজেই চালায় (option hitscam_b2_seed)
	update_option( 'hitscam_seeded', time() );
	return $made;
}

// থিম অ্যাক্টিভেশনে: দোকানে কোনো পণ্য না থাকলে ডেমো ডেটা একবার বসে (WooCommerce লোড হলেই)
add_action( 'after_switch_theme', function () {
	delete_option( 'hitscam_e8_v' );
	if ( ! get_option( 'hitscam_seeded' ) ) { update_option( 'hitscam_seed_pending', 1 ); }
}, 30 );
add_action( 'init', function () {
	if ( ! get_option( 'hitscam_seed_pending' ) || ! function_exists( 'wc_get_products' ) || ! class_exists( 'WC_Product_Simple' ) ) { return; }
	delete_option( 'hitscam_seed_pending' );
	if ( wc_get_products( array( 'limit' => 1, 'return' => 'ids', 'status' => 'publish' ) ) ) { update_option( 'hitscam_seeded', 'skipped-existing-products' ); return; }
	hitscam_seed_demo();
}, 40 );
