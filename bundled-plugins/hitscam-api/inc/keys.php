<?php
/**
 * API key ম্যানেজমেন্ট: key হ্যাশ করে রাখা হয় (শুধু তৈরির সময় একবার দেখানো হয়)।
 */
defined( 'ABSPATH' ) || exit;

function hcapi_scopes() {
	return array(
		'products:read'  => 'পণ্য পড়া',
		'products:write' => 'পণ্য তৈরি/এডিট/ডিলিট',
		'stock:write'    => 'স্টক আপডেট',
		'orders:read'    => 'অর্ডার পড়া',
		'orders:write'   => 'অর্ডার তৈরি/স্ট্যাটাস আপডেট',
		'customers:read' => 'কাস্টমার পড়া',
		'coupons:read'   => 'কুপন পড়া',
		'reports:read'   => 'সেলস রিপোর্ট',
		'webhooks:manage' => 'ওয়েবহুক ম্যানেজ',
	);
}

function hcapi_get_keys() {
	$k = get_option( 'hcapi_keys', array() );
	return is_array( $k ) ? $k : array();
}

/** @return array{id:string,key:string} — প্লেইন key শুধু এখানেই ফেরত আসে */
function hcapi_create_key( $name, $scopes, $rate = 120 ) {
	$id    = 'k' . wp_generate_password( 8, false, false );
	$plain = 'hc_' . bin2hex( random_bytes( 20 ) );
	$keys  = hcapi_get_keys();
	$keys[ $id ] = array(
		'id'      => $id,
		'name'    => sanitize_text_field( $name ),
		'hash'    => hash( 'sha256', $plain ),
		'prefix'  => substr( $plain, 0, 9 ),
		'scopes'  => array_values( array_intersect( (array) $scopes, array_keys( hcapi_scopes() ) ) ),
		'rate'    => max( 10, (int) $rate ),
		'created' => time(),
		'used'    => 0,
		'revoked' => false,
	);
	update_option( 'hcapi_keys', $keys, false );
	return array( 'id' => $id, 'key' => $plain );
}

function hcapi_revoke_key( $id ) {
	$keys = hcapi_get_keys();
	if ( isset( $keys[ $id ] ) ) {
		unset( $keys[ $id ] );
		update_option( 'hcapi_keys', $keys, false );
	}
}

function hcapi_find_key( $plain ) {
	$h = hash( 'sha256', (string) $plain );
	foreach ( hcapi_get_keys() as $k ) {
		if ( hash_equals( $k['hash'], $h ) && empty( $k['revoked'] ) ) {
			return $k;
		}
	}
	return null;
}

function hcapi_extract_key( WP_REST_Request $req ) {
	$h = $req->get_header( 'x_hitscam_key' );
	if ( $h ) {
		return trim( $h );
	}
	$a = $req->get_header( 'authorization' );
	if ( $a && preg_match( '/^Bearer\s+(\S+)/i', $a, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * পারমিশন চেক: স্কোপ লাগবে। WP-এ লগইন করা manage_woocommerce ইউজার (cookie+nonce) সব স্কোপ পায়।
 *
 * @return true|WP_Error
 */
function hcapi_authorize( WP_REST_Request $req, $scope ) {
	$plain = hcapi_extract_key( $req );
	if ( ! $plain ) {
		if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		return new WP_Error( 'hcapi_no_key', 'API key দিন (Authorization: Bearer <key> অথবা X-HitsCam-Key)।', array( 'status' => 401 ) );
	}
	$key = hcapi_find_key( $plain );
	if ( ! $key ) {
		return new WP_Error( 'hcapi_bad_key', 'API key সঠিক নয় বা বাতিল।', array( 'status' => 401 ) );
	}
	// রেট-লিমিট (প্রতি মিনিট)
	$bucket = 'hcapi_rl_' . $key['id'] . '_' . gmdate( 'YmdHi' );
	$n      = (int) get_transient( $bucket ) + 1;
	set_transient( $bucket, $n, 90 );
	if ( $n > $key['rate'] ) {
		return new WP_Error( 'hcapi_rate', 'রেট-লিমিট পার হয়েছে; এক মিনিট পরে চেষ্টা করুন।', array( 'status' => 429 ) );
	}
	if ( ! in_array( $scope, $key['scopes'], true ) ) {
		return new WP_Error( 'hcapi_scope', 'এই key-তে স্কোপ নেই: ' . $scope, array( 'status' => 403 ) );
	}
	$GLOBALS['hcapi_current_key'] = $key['id'];
	// last-used (কম লেখার জন্য ৬০সে অন্তর)
	$keys = hcapi_get_keys();
	if ( isset( $keys[ $key['id'] ] ) && time() - (int) $keys[ $key['id'] ]['used'] > 60 ) {
		$keys[ $key['id'] ]['used'] = time();
		update_option( 'hcapi_keys', $keys, false );
	}
	return true;
}
