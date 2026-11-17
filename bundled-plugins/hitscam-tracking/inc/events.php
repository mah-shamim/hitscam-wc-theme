<?php
/**
 * ফ্রন্টএন্ড ইভেন্ট কালেকশন + স্ক্রিপ্ট আউটপুট।
 * প্রতিটি ইভেন্টে একটি event_id থাকে — ব্রাউজার পিক্সেল ও সার্ভার (CAPI) একই id পাঠায়, Meta ডিডুপ্লিকেট করে।
 */
defined( 'ABSPATH' ) || exit;

function hctrk_new_id() {
	return 'hc_' . bin2hex( random_bytes( 8 ) );
}

function hctrk_item( WC_Product $p, $qty = 1, $fmt = 'id' ) {
	$pid  = $p->get_parent_id() ? $p->get_parent_id() : $p->get_id();
	$cats = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) );
	$id   = ( 'sku' === $fmt && $p->get_sku() ) ? $p->get_sku() : (string) $p->get_id();
	return apply_filters( 'hitscam_tracking_item', array(
		'id' => $id, 'name' => $p->get_name(), 'price' => (float) wc_get_price_to_display( $p ), 'quantity' => (int) $qty,
		'category' => ! is_wp_error( $cats ) && $cats ? $cats[0] : '', 'sku' => $p->get_sku(),
	), $p );
}

/** একটি ইভেন্ট বানায়: প্ল্যাটফর্মভেদে payload */
function hctrk_make( $key, $meta_name, $meta_params, $ga_name, $ga_params, $capi = true, $extra = array() ) {
	$m = hctrk_opts();
	$ev = array( 'id' => hctrk_new_id(), 'key' => $key );
	if ( $m['meta']['enabled'] && $m['meta']['pixel_id'] && $meta_name && ! empty( $m['meta']['events'][ $key ] ) ) {
		$ev['meta'] = array( 'name' => $meta_name, 'custom' => ! in_array( $meta_name, array( 'PageView', 'ViewContent', 'Search', 'AddToCart', 'InitiateCheckout', 'AddPaymentInfo', 'Purchase' ), true ), 'params' => $meta_params );
		$ev['capi'] = $capi && $m['meta']['capi'] && $m['meta']['token'];
	}
	if ( $ga_name ) {
		$gkey = array( 'viewcontent' => 'view_item', 'viewcategory' => 'view_item_list', 'search' => 'search', 'addtocart' => 'add_to_cart', 'initiatecheckout' => 'begin_checkout', 'addpaymentinfo' => 'add_payment_info', 'purchase' => 'purchase' )[ $key ] ?? '';
		$to_ga  = $m['google']['enabled'] && ( $m['google']['ga4_id'] || $m['google']['ads_id'] ) && $gkey && ! empty( $m['google']['events'][ $gkey ] );
		$to_gtm = $m['gtm']['enabled'] && $m['gtm']['container_id'];
		if ( $to_ga || $to_gtm ) {
			$ev['ga'] = array( 'name' => $ga_name, 'params' => $ga_params, 'gtag' => (bool) $to_ga, 'gtm' => (bool) $to_gtm );
		}
	}
	$ev = array_merge( $ev, $extra );
	$ev = apply_filters( 'hitscam_tracking_event', $ev, $key );
	return ( isset( $ev['meta'] ) || isset( $ev['ga'] ) ) ? $ev : null;
}

function hctrk_items_payload( array $items, $id_fmt = 'id' ) {
	$ga = array();
	foreach ( $items as $i => $it ) {
		$ga[] = array( 'item_id' => $it['id'], 'item_name' => $it['name'], 'price' => $it['price'], 'quantity' => $it['quantity'], 'item_category' => $it['category'], 'index' => $i );
	}
	return $ga;
}

function hctrk_meta_contents( array $items ) {
	return array(
		'content_type' => 'product',
		'content_ids'  => array_map( fn( $i ) => (string) $i['id'], $items ),
		'contents'     => array_map( fn( $i ) => array( 'id' => (string) $i['id'], 'quantity' => $i['quantity'], 'item_price' => $i['price'] ), $items ),
	);
}

function hctrk_cart_items() {
	$items = array();
	if ( WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $ci ) {
			$items[] = hctrk_item( $ci['data'], $ci['quantity'], hctrk_opt( 'meta', 'content_id' ) );
		}
	}
	return $items;
}

/* ---------- AddToCart: সেশন কিউ ---------- */
add_action( 'woocommerce_add_to_cart', function ( $key, $pid, $qty, $vid ) {
	if ( hctrk_skip() || ! hctrk_has_consent() || ! WC()->session ) { return; }
	$p = wc_get_product( $vid ? $vid : $pid );
	if ( ! $p ) { return; }
	$it = hctrk_item( $p, $qty, hctrk_opt( 'meta', 'content_id' ) );
	$val = $it['price'] * $it['quantity'];
	$ev = hctrk_make( 'addtocart', 'AddToCart',
		array_merge( hctrk_meta_contents( array( $it ) ), array( 'content_name' => $it['name'], 'content_category' => $it['category'], 'value' => $val, 'currency' => get_woocommerce_currency() ) ),
		'add_to_cart', array( 'currency' => get_woocommerce_currency(), 'value' => $val, 'items' => hctrk_items_payload( array( $it ) ) ) );
	if ( ! $ev ) { return; }
	$q   = (array) WC()->session->get( 'hctrk_q', array() );
	$q[] = $ev;
	WC()->session->set( 'hctrk_q', $q );
	if ( ! empty( $ev['capi'] ) ) { hctrk_capi_queue( 'AddToCart', $ev ); }
}, 20, 4 );

// AJAX add-to-cart: কিউ ফ্র্যাগমেন্টে পাঠাই (এবং সেশন থেকে মুছি)
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$q = WC()->session ? (array) WC()->session->get( 'hctrk_q', array() ) : array();
	if ( $q ) { WC()->session->set( 'hctrk_q', array() ); }
	$f['div.hctrk-q'] = '<div class="hctrk-q" hidden data-q="' . esc_attr( wp_json_encode( $q ) ) . '"></div>';
	return $f;
} );

/* ---------- পেজ ইভেন্ট ---------- */
function hctrk_collect_events() {
	$cur = get_woocommerce_currency();
	$fmt = hctrk_opt( 'meta', 'content_id' );
	$evs = array();
	$add = function ( $e ) use ( &$evs ) { if ( $e ) { $evs[] = $e; } };

	$add( hctrk_make( 'pageview', 'PageView', array(), '', array(), true ) );

	if ( function_exists( 'is_product' ) && is_product() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$it = hctrk_item( $p, 1, $fmt );
			$add( hctrk_make( 'viewcontent', 'ViewContent',
				array_merge( hctrk_meta_contents( array( $it ) ), array( 'content_name' => $it['name'], 'content_category' => $it['category'], 'value' => $it['price'], 'currency' => $cur ) ),
				'view_item', array( 'currency' => $cur, 'value' => $it['price'], 'items' => hctrk_items_payload( array( $it ) ) ) ) );
		}
	} elseif ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
		global $wp_query;
		$items = array();
		foreach ( array_slice( (array) $wp_query->posts, 0, 20 ) as $post ) {
			if ( $pr = wc_get_product( $post->ID ) ) { $items[] = hctrk_item( $pr, 1, $fmt ); }
		}
		$name = is_product_category() || is_product_tag() ? single_term_title( '', false ) : get_the_title( wc_get_page_id( 'shop' ) );
		$add( hctrk_make( 'viewcategory', 'ViewCategory', array( 'content_name' => $name, 'content_ids' => array_map( fn( $i ) => (string) $i['id'], $items ), 'content_type' => 'product' ),
			'view_item_list', array( 'item_list_name' => $name, 'items' => hctrk_items_payload( $items ) ) ) );
	}
	if ( is_search() ) {
		$add( hctrk_make( 'search', 'Search', array( 'search_string' => get_search_query( false ) ), 'search', array( 'search_term' => get_search_query( false ) ) ) );
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && WC()->cart && ! WC()->cart->is_empty() ) {
		$items = hctrk_cart_items();
		$val   = (float) WC()->cart->get_cart_contents_total();
		$base  = array_merge( hctrk_meta_contents( $items ), array( 'num_items' => WC()->cart->get_cart_contents_count(), 'value' => $val, 'currency' => $cur ) );
		$add( hctrk_make( 'initiatecheckout', 'InitiateCheckout', $base, 'begin_checkout', array( 'currency' => $cur, 'value' => $val, 'items' => hctrk_items_payload( $items ) ) ) );
		$add( hctrk_make( 'addpaymentinfo', 'AddPaymentInfo', $base, 'add_payment_info', array( 'currency' => $cur, 'value' => $val, 'items' => hctrk_items_payload( $items ) ), false, array( 'trigger' => 'place_order' ) ) );
	}
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		$oid   = absint( get_query_var( 'order-received' ) );
		$order = $oid ? wc_get_order( $oid ) : null;
		$ok    = $order && hash_equals( (string) $order->get_order_key(), (string) wp_unslash( $_GET['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $ok && ( ! hctrk_opt( 'general', 'purchase_once' ) || ! $order->get_meta( '_hctrk_purchased' ) ) && ! in_array( $order->get_status(), array( 'failed', 'cancelled' ), true ) ) {
			$items = array();
			foreach ( $order->get_items() as $oi ) {
				if ( $pr = $oi->get_product() ) { $items[] = hctrk_item( $pr, $oi->get_quantity(), $fmt ); }
			}
			$total = (float) $order->get_total();
			$ev = hctrk_make( 'purchase', 'Purchase',
				array_merge( hctrk_meta_contents( $items ), array( 'num_items' => $order->get_item_count(), 'value' => $total, 'currency' => $order->get_currency() ) ),
				'purchase', array( 'transaction_id' => (string) $order->get_order_number(), 'value' => $total, 'tax' => (float) $order->get_total_tax(), 'shipping' => (float) $order->get_shipping_total(), 'currency' => $order->get_currency(), 'coupon' => implode( ',', $order->get_coupon_codes() ), 'items' => hctrk_items_payload( $items ) ),
				true, array( 'order_id' => $order->get_id() ) );
			if ( $ev ) {
				$ev['id'] = 'purchase_' . $order->get_id(); // স্থির id: রিলোডেও ডিডুপ হয়
				$ev['ads_conv'] = hctrk_opt( 'google', 'enabled' ) && hctrk_opt( 'google', 'ads_id' ) && hctrk_opt( 'google', 'ads_label' ) ? hctrk_opt( 'google', 'ads_id' ) . '/' . hctrk_opt( 'google', 'ads_label' ) : '';
				$evs[] = $ev;
				$order->update_meta_data( '_hctrk_purchased', time() );
				$order->save_meta_data();
			}
		}
	}
	// সেশনে জমা থাকা AddToCart (নন-AJAX রিডাইরেক্টের পর)
	if ( WC()->session ) {
		$q = (array) WC()->session->get( 'hctrk_q', array() );
		if ( $q ) { WC()->session->set( 'hctrk_q', array() ); $evs = array_merge( $evs, $q ); }
	}
	return $evs;
}

/** পিক্সেল Advanced Matching ডেটা (কনসেন্ট থাকলে, লগইন/অর্ডার থেকে) */
function hctrk_adv_match() {
	if ( ! hctrk_opt( 'meta', 'adv_match' ) ) { return new stdClass(); }
	$d = hctrk_user_data_raw();
	return $d ? (object) $d : new stdClass();
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! class_exists( 'WooCommerce' ) || hctrk_skip() ) { return; }
	$o = hctrk_opts();
	if ( ! ( ( $o['meta']['enabled'] && $o['meta']['pixel_id'] ) || ( $o['google']['enabled'] && ( $o['google']['ga4_id'] || $o['google']['ads_id'] ) ) || ( $o['gtm']['enabled'] && $o['gtm']['container_id'] ) ) ) { return; }
	$consent = hctrk_has_consent();
	$cfg = array(
		'meta'    => array( 'ids' => $o['meta']['enabled'] ? array_values( array_filter( explode( ',', $o['meta']['pixel_id'] ) ) ) : array(), 'am' => $consent ? hctrk_adv_match() : new stdClass() ),
		'google'  => array( 'ga4' => $o['google']['enabled'] ? $o['google']['ga4_id'] : '', 'ads' => $o['google']['enabled'] ? $o['google']['ads_id'] : '', 'anon' => (bool) $o['google']['anonymize'] ),
		'gtm'     => array( 'id' => $o['gtm']['enabled'] ? $o['gtm']['container_id'] : '', 'dl' => $o['gtm']['dl_name'], 'noGtag' => (bool) $o['gtm']['suppress_gtag'] ),
		'consent' => array( 'required' => (bool) $o['general']['require_consent'], 'cookie' => $o['general']['consent_cookie'], 'mode' => (bool) $o['general']['consent_mode'] ),
		'q'       => hctrk_collect_events(),
		'debug'   => (bool) ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ),
	);
	// সার্ভার-সাইড CAPI: পেজ ইভেন্টগুলো (কনসেন্ট থাকলে)
	if ( $consent ) {
		foreach ( $cfg['q'] as $e ) {
			if ( ! empty( $e['capi'] ) && empty( $e['trigger'] ) ) { hctrk_capi_queue( $e['meta']['name'], $e ); }
		}
	}
	wp_register_script( 'hctrk', HCTRK_URL . 'assets/tracker.js', array(), HCTRK_VERSION, array( 'in_footer' => false, 'strategy' => 'defer' ) );
	wp_add_inline_script( 'hctrk', 'window.HCTRK=' . wp_json_encode( apply_filters( 'hitscam_tracking_config', $cfg ) ) . ';', 'before' );
	wp_enqueue_script( 'hctrk' );
}, 20 );

// GTM noscript
add_action( 'wp_body_open', function () {
	$g = hctrk_opt( 'gtm' );
	if ( $g['enabled'] && $g['container_id'] && ! hctrk_skip() && hctrk_has_consent() ) {
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $g['container_id'] ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
	}
} );
