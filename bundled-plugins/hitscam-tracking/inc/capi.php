<?php
/**
 * Meta Conversions API (সার্ভার-সাইড)। ব্রাউজার পিক্সেলের সাথে একই event_id → Meta ডিডুপ্লিকেট করে।
 */
defined( 'ABSPATH' ) || exit;

function hctrk_hash( $v ) {
	$v = strtolower( trim( (string) $v ) );
	return '' === $v ? '' : hash( 'sha256', $v );
}

/** পিক্সেলের জন্য প্লেইন (পিক্সেল নিজে হ্যাশ করে), লগইন ইউজার/WC কাস্টমার থেকে */
function hctrk_user_data_raw( $order = null ) {
	$d = array();
	if ( $order instanceof WC_Order ) {
		$d = array( 'em' => $order->get_billing_email(), 'ph' => preg_replace( '/\D/', '', $order->get_billing_phone() ), 'fn' => $order->get_billing_first_name(), 'ln' => $order->get_billing_last_name(), 'ct' => $order->get_billing_city(), 'country' => strtolower( $order->get_billing_country() ) );
	} elseif ( is_user_logged_in() ) {
		$u = wp_get_current_user();
		$d = array( 'em' => $u->user_email, 'fn' => $u->first_name, 'ln' => $u->last_name );
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$d['ph'] = preg_replace( '/\D/', '', WC()->customer->get_billing_phone() );
		}
	}
	if ( ! empty( $d['ph'] ) ) {
		$d['ph'] = preg_match( '/^01[3-9]\d{8}$/', $d['ph'] ) ? '88' . $d['ph'] : $d['ph']; // বাংলাদেশি নম্বর → দেশের কোডসহ
		$d['ph'] = apply_filters( 'hitscam_tracking_phone', $d['ph'] );
	}
	return array_filter( array_map( fn( $v ) => is_string( $v ) ? strtolower( trim( $v ) ) : $v, $d ) );
}

function hctrk_client_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$ip = trim( explode( ',', wp_unslash( $_SERVER[ $k ] ) )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { return $ip; }
		}
	}
	return '';
}

function hctrk_capi_user_data( $order_id = 0 ) {
	$raw = hctrk_user_data_raw( $order_id ? wc_get_order( $order_id ) : null );
	$ud  = array();
	$map = array( 'em' => 'em', 'ph' => 'ph', 'fn' => 'fn', 'ln' => 'ln', 'ct' => 'ct', 'country' => 'country' );
	foreach ( $map as $k => $to ) {
		if ( ! empty( $raw[ $k ] ) ) { $ud[ $to ] = array( hctrk_hash( $raw[ $k ] ) ); }
	}
	if ( is_user_logged_in() ) { $ud['external_id'] = array( hctrk_hash( get_current_user_id() ) ); }
	$ud['client_ip_address'] = hctrk_client_ip();
	$ud['client_user_agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
	$fbp = sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ?? '' ) );
	$fbc = sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ?? '' ) );
	if ( ! $fbc && ! empty( $_GET['fbclid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$fbc = 'fb.1.' . time() . '000.' . sanitize_text_field( wp_unslash( $_GET['fbclid'] ) );
	}
	if ( $fbp ) { $ud['fbp'] = $fbp; }
	if ( $fbc ) { $ud['fbc'] = $fbc; }
	return $ud;
}

function hctrk_capi_queue( $name, $ev ) {
	global $hctrk_capi_buf;
	$hctrk_capi_buf = $hctrk_capi_buf ?: array();
	$hctrk_capi_buf[] = array(
		'event_name'       => $name,
		'event_time'       => time(),
		'event_id'         => $ev['id'],
		'event_source_url' => ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ),
		'action_source'    => 'website',
		'user_data'        => hctrk_capi_user_data( (int) ( $ev['order_id'] ?? 0 ) ),
		'custom_data'      => (object) ( $ev['meta']['params'] ?? array() ),
	);
}

add_action( 'shutdown', function () {
	global $hctrk_capi_buf;
	if ( empty( $hctrk_capi_buf ) ) { return; }
	$m = hctrk_opt( 'meta' );
	if ( function_exists( 'fastcgi_finish_request' ) ) { fastcgi_finish_request(); }
	foreach ( array_filter( explode( ',', $m['pixel_id'] ) ) as $pid ) {
		$body = array( 'data' => apply_filters( 'hitscam_tracking_capi_payload', $hctrk_capi_buf ) );
		if ( $m['test_code'] ) { $body['test_event_code'] = $m['test_code']; }
		$url = 'https://graph.facebook.com/v19.0/' . rawurlencode( $pid ) . '/events?access_token=' . rawurlencode( $m['token'] );
		$r   = wp_remote_post( $url, array( 'timeout' => 4, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
		foreach ( $hctrk_capi_buf as $e ) {
			if ( is_wp_error( $r ) ) {
				hctrk_log( 'capi', 'meta', $e['event_name'], 'error', $r->get_error_message() );
			} else {
				$code = wp_remote_retrieve_response_code( $r );
				$msg  = 200 === $code ? 'event_id ' . $e['event_id'] : wp_remote_retrieve_body( $r );
				// টোকেন লগে যেন না যায়
				hctrk_log( 'capi', 'meta', $e['event_name'], 200 === $code ? 'ok' : 'error', str_replace( $m['token'], '***', $msg ) );
			}
		}
	}
	$hctrk_capi_buf = array();
} );
