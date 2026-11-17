<?php
/** IndexNow + Google Indexing API — পাবলিশ/আপডেট/ডিলিটে অটো নোটিফাই */
defined( 'ABSPATH' ) || exit;

function hcseo_index_log( $svc, $url, $ok, $msg ) {
	$l = (array) get_option( 'hcseo_index_log', array() );
	array_unshift( $l, array( time(), $svc, $url, $ok ? 'ok' : 'error', mb_substr( (string) $msg, 0, 200 ) ) );
	update_option( 'hcseo_index_log', array_slice( $l, 0, 100 ), false );
}

function hcseo_indexnow_submit( array $urls ) {
	$key = hcseo_opt( 'in_key' );
	if ( ! $key || ! $urls ) { return new WP_Error( 'no', 'key/URL নেই' ); }
	$r = wp_remote_post( 'https://api.indexnow.org/indexnow', array( 'timeout' => 10, 'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body' => wp_json_encode( array( 'host' => wp_parse_url( home_url(), PHP_URL_HOST ), 'key' => $key, 'keyLocation' => home_url( "/$key.txt" ), 'urlList' => array_values( $urls ) ) ) ) );
	$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
	foreach ( $urls as $u ) { hcseo_index_log( 'IndexNow', $u, in_array( $code, array( 200, 202 ), true ), is_wp_error( $r ) ? $r->get_error_message() : "HTTP $code" ); }
	return $r;
}

function hcseo_google_notify( $url, $type = 'URL_UPDATED' ) {
	$tok = hcseo_google_token( 'https://www.googleapis.com/auth/indexing' );
	if ( is_wp_error( $tok ) ) { hcseo_index_log( 'Google', $url, false, $tok->get_error_message() ); return $tok; }
	$r = wp_remote_post( 'https://indexing.googleapis.com/v3/urlNotifications:publish', array( 'timeout' => 10, 'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'url' => $url, 'type' => $type ) ) ) );
	$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
	hcseo_index_log( 'Google ' . $type, $url, 200 === $code, is_wp_error( $r ) ? $r->get_error_message() : ( 200 === $code ? 'OK' : wp_remote_retrieve_body( $r ) ) );
	return $r;
}

$GLOBALS['hcseo_pending'] = array( 'now' => array(), 'g' => array(), 'gdel' => array() );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( wp_is_post_revision( $post ) || ! is_post_type_viewable( $post->post_type ) ) { return; }
	if ( 'publish' === $new ) {
		if ( get_post_meta( $post->ID, '_hcseo_noindex', true ) ) { return; }
		$u = get_permalink( $post );
		if ( hcseo_opt( 'in_now' ) ) { $GLOBALS['hcseo_pending']['now'][ $u ] = 1; }
		if ( hcseo_opt( 'in_google' ) ) { $GLOBALS['hcseo_pending']['g'][ $u ] = 1; }
	} elseif ( 'publish' === $old && hcseo_opt( 'in_google' ) ) {
		$GLOBALS['hcseo_pending']['gdel'][ get_permalink( $post ) ] = 1;
	}
}, 20, 3 );
add_action( 'shutdown', function () {
	$p = $GLOBALS['hcseo_pending'];
	if ( $p['now'] ) { hcseo_indexnow_submit( array_keys( $p['now'] ) ); }
	foreach ( array_keys( $p['g'] ) as $u ) { hcseo_google_notify( $u, 'URL_UPDATED' ); }
	foreach ( array_keys( $p['gdel'] ) as $u ) { hcseo_google_notify( $u, 'URL_DELETED' ); }
} );
