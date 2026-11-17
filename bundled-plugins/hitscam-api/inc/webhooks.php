<?php
/**
 * ওয়েবহুক: ইভেন্ট ঘটলে সাবস্ক্রাইবড URL-এ HMAC-SHA256 সাইনড JSON পাঠায়; ব্যর্থ হলে WP-Cron দিয়ে রিট্রাই।
 *
 * সাইন: X-HitsCam-Signature: sha256=HMAC_SHA256(secret, timestamp . "." . body)
 */
defined( 'ABSPATH' ) || exit;

function hcapi_webhook_events() {
	return array(
		'order.created'        => 'নতুন অর্ডার',
		'order.status_changed' => 'অর্ডার স্ট্যাটাস বদল',
		'product.created'      => 'নতুন পণ্য',
		'product.updated'      => 'পণ্য আপডেট',
		'product.deleted'      => 'পণ্য ডিলিট/ট্র্যাশ',
		'stock.changed'        => 'স্টক বদল',
		'customer.created'     => 'নতুন কাস্টমার',
	);
}

function hcapi_get_webhooks() {
	$w = get_option( 'hcapi_webhooks', array() );
	return is_array( $w ) ? $w : array();
}

function hcapi_save_webhook( $data, $id = '' ) {
	$all = hcapi_get_webhooks();
	$url = esc_url_raw( $data['url'] ?? '' );
	$okurl = apply_filters( 'hcapi_allow_private_webhook_urls', false ) ? ( 0 === strpos( $url, 'http' ) ) : (bool) wp_http_validate_url( $url ); // SSRF গার্ড: লোকাল/প্রাইভেট IP ডিফল্টে বন্ধ
	if ( ! $okurl ) {
		return new WP_Error( 'hcapi_url', 'সঠিক URL দিন (http/https)।', array( 'status' => 400 ) );
	}
	$events = array_values( array_intersect( (array) ( $data['events'] ?? array() ), array_keys( hcapi_webhook_events() ) ) );
	if ( ! $events ) {
		return new WP_Error( 'hcapi_events', 'অন্তত একটি ইভেন্ট দিন।', array( 'status' => 400 ) );
	}
	if ( ! $id ) {
		$id = 'w' . wp_generate_password( 8, false, false );
	}
	$old = $all[ $id ] ?? array();
	$all[ $id ] = array(
		'id'     => $id,
		'name'   => sanitize_text_field( $data['name'] ?? 'Webhook' ),
		'url'    => $url,
		'events' => $events,
		'secret' => ! empty( $data['secret'] ) ? (string) $data['secret'] : ( $old['secret'] ?? bin2hex( random_bytes( 16 ) ) ),
		'active' => isset( $data['active'] ) ? (bool) $data['active'] : true,
	);
	update_option( 'hcapi_webhooks', $all, false );
	return $all[ $id ];
}

function hcapi_delete_webhook( $id ) {
	$all = hcapi_get_webhooks();
	unset( $all[ $id ] );
	update_option( 'hcapi_webhooks', $all, false );
}

function hcapi_log_delivery( $row ) {
	$log = get_option( 'hcapi_deliveries', array() );
	array_unshift( $log, $row );
	update_option( 'hcapi_deliveries', array_slice( $log, 0, 100 ), false );
}

/** ইভেন্ট ছড়িয়ে দেয় */
function hcapi_dispatch( $event, $payload ) {
	foreach ( hcapi_get_webhooks() as $w ) {
		if ( empty( $w['active'] ) || ! in_array( $event, $w['events'], true ) ) {
			continue;
		}
		$did = wp_generate_uuid4();
		set_transient( 'hcapi_pl_' . $did, array( 'event' => $event, 'data' => $payload, 'created' => gmdate( 'c' ) ), DAY_IN_SECONDS );
		wp_schedule_single_event( time(), 'hcapi_deliver', array( $w['id'], $did, 1 ) );
	}
}

add_action( 'shutdown', function () {
	// একই রিকোয়েস্টে ঠিকঠাক চালু রাখতে cron স্পন করি
	if ( ! wp_doing_cron() && function_exists( 'spawn_cron' ) && ! defined( 'DOING_AJAX' ) ) {
		static $done = false;
		if ( ! $done && get_option( 'hcapi_webhooks' ) ) {
			$done = true;
			spawn_cron();
		}
	}
} );

add_action( 'hcapi_deliver', 'hcapi_deliver', 10, 3 );
function hcapi_deliver( $wid, $did, $attempt ) {
	$all = hcapi_get_webhooks();
	$pl  = get_transient( 'hcapi_pl_' . $did );
	if ( ! isset( $all[ $wid ] ) || ! $pl ) {
		return;
	}
	$w    = $all[ $wid ];
	$body = wp_json_encode( array( 'id' => $did, 'event' => $pl['event'], 'created' => $pl['created'], 'data' => $pl['data'] ) );
	$ts   = (string) time();
	$res  = wp_remote_post( $w['url'], array(
		'timeout'     => 8,
		'redirection' => 0,
		'body'        => $body,
		'headers'     => array(
			'Content-Type'          => 'application/json',
			'User-Agent'            => 'HitsCam-Webhook/' . HCAPI_VERSION,
			'X-HitsCam-Event'       => $pl['event'],
			'X-HitsCam-Delivery'    => $did,
			'X-HitsCam-Timestamp'   => $ts,
			'X-HitsCam-Signature'   => 'sha256=' . hash_hmac( 'sha256', $ts . '.' . $body, $w['secret'] ),
		),
	) );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$ok   = $code >= 200 && $code < 300;
	hcapi_log_delivery( array(
		'time'    => time(), 'webhook' => $w['name'], 'event' => $pl['event'], 'attempt' => $attempt,
		'code'    => $code, 'ok' => $ok, 'error' => is_wp_error( $res ) ? $res->get_error_message() : '',
	) );
	if ( ! $ok ) {
		$delays = array( 1 => 60, 2 => 300, 3 => 1800 );
		if ( isset( $delays[ $attempt ] ) ) {
			wp_schedule_single_event( time() + $delays[ $attempt ], 'hcapi_deliver', array( $wid, $did, $attempt + 1 ) );
			return;
		}
	}
	delete_transient( 'hcapi_pl_' . $did );
}

/* ---- ইভেন্ট হুক ---- */
function hcapi_order_created( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_hcapi_created_sent' ) ) {
		return;
	}
	$order->update_meta_data( '_hcapi_created_sent', 1 );
	$order->save_meta_data();
	hcapi_dispatch( 'order.created', hcapi_format_order( $order ) );
}
add_action( 'woocommerce_checkout_order_processed', 'hcapi_order_created', 20, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) { hcapi_order_created( $order->get_id() ); } );
add_action( 'woocommerce_new_order', function ( $id ) {
	// REST/Admin থেকে তৈরি — আইটেম যোগ হওয়ার পরে পাঠাতে শাটডাউনে
	add_action( 'shutdown', function () use ( $id ) { hcapi_order_created( $id ); }, 5 );
}, 20, 1 );

add_action( 'woocommerce_order_status_changed', function ( $id, $from, $to, $order ) {
	$d           = hcapi_format_order( $order );
	$d['from']   = $from;
	$d['to']     = $to;
	hcapi_dispatch( 'order.status_changed', $d );
}, 20, 4 );

add_action( 'woocommerce_new_product', function ( $id ) {
	if ( $p = wc_get_product( $id ) ) { hcapi_dispatch( 'product.created', hcapi_format_product( $p ) ); }
}, 20 );
add_action( 'woocommerce_update_product', function ( $id ) {
	static $seen = array();
	if ( isset( $seen[ $id ] ) ) { return; }
	$seen[ $id ] = 1;
	if ( $p = wc_get_product( $id ) ) { hcapi_dispatch( 'product.updated', hcapi_format_product( $p ) ); }
}, 20 );
add_action( 'wp_trash_post', function ( $id ) {
	if ( 'product' === get_post_type( $id ) ) { hcapi_dispatch( 'product.deleted', array( 'id' => (int) $id ) ); }
} );
add_action( 'before_delete_post', function ( $id ) {
	if ( 'product' === get_post_type( $id ) ) { hcapi_dispatch( 'product.deleted', array( 'id' => (int) $id, 'permanent' => true ) ); }
} );
add_action( 'woocommerce_product_set_stock', function ( $p ) {
	hcapi_dispatch( 'stock.changed', array( 'id' => $p->get_id(), 'sku' => $p->get_sku(), 'stock_quantity' => $p->get_stock_quantity(), 'stock_status' => $p->get_stock_status() ) );
} );
add_action( 'woocommerce_variation_set_stock', function ( $p ) {
	hcapi_dispatch( 'stock.changed', array( 'id' => $p->get_id(), 'sku' => $p->get_sku(), 'stock_quantity' => $p->get_stock_quantity(), 'stock_status' => $p->get_stock_status() ) );
} );
add_action( 'woocommerce_created_customer', function ( $id ) {
	if ( $c = new WC_Customer( $id ) ) { hcapi_dispatch( 'customer.created', hcapi_format_customer( $c ) ); }
} );
