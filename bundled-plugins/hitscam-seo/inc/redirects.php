<?php
/** 404 মনিটর + রিডাইরেক্ট ম্যানেজার (+ স্লাগ বদলালে অটো রিডাইরেক্ট) */
defined( 'ABSPATH' ) || exit;

function hcseo_norm_path( $u ) {
	$p = wp_parse_url( $u, PHP_URL_PATH ) ?: '/';
	return '/' . trim( rawurldecode( $p ), '/' );
}

add_action( 'template_redirect', function () {
	if ( is_admin() ) { return; }
	global $wpdb;
	$req = hcseo_norm_path( $_SERVER['REQUEST_URI'] ?? '/' );
	$t   = $wpdb->prefix . 'hcseo_redirects';
	$r   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE source = %s", $req ) ); // phpcs:ignore
	if ( $r ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET hits = hits + 1 WHERE id = %d", $r->id ) ); // phpcs:ignore
		if ( 410 === (int) $r->code ) { status_header( 410 ); nocache_headers(); wp_die( 'এই পেজ আর নেই (410)।', '410 Gone', array( 'response' => 410 ) ); }
		wp_redirect( esc_url_raw( $r->target ), (int) $r->code ); // phpcs:ignore
		exit;
	}
	if ( is_404() && hcseo_opt( 'redirect_404' ) && ! preg_match( '~\.(?:css|js|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf)$~i', $req ) ) {
		$tbl = $wpdb->prefix . 'hcseo_404';
		$ref = mb_substr( wp_get_raw_referer() ?: '', 0, 250 );
		$wpdb->query( $wpdb->prepare( "INSERT INTO $tbl (url, hits, referrer, last_seen) VALUES (%s, 1, %s, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen)", mb_substr( $req, 0, 190 ), $ref, current_time( 'mysql' ) ) ); // phpcs:ignore
	}
}, 1 );

function hcseo_add_redirect( $src, $tgt, $code = 301 ) {
	global $wpdb;
	$src = hcseo_norm_path( $src );
	if ( '/' === $src || ( '' === trim( $tgt ) && 410 !== (int) $code ) ) { return false; }
	$tgt = ( 0 === strpos( $tgt, 'http' ) ) ? esc_url_raw( $tgt ) : '/' . ltrim( sanitize_text_field( $tgt ), '/' );
	return (bool) $wpdb->query( $wpdb->prepare( "REPLACE INTO {$wpdb->prefix}hcseo_redirects (source, target, code, hits, created) VALUES (%s, %s, %d, 0, %s)", $src, $tgt, in_array( (int) $code, array( 301, 302, 307, 410 ), true ) ? (int) $code : 301, current_time( 'mysql' ) ) ); // phpcs:ignore
}

add_action( 'post_updated', function ( $id, $after, $before ) {
	if ( ! hcseo_opt( 'redirect_auto' ) || 'publish' !== $after->post_status || 'publish' !== $before->post_status || $after->post_name === $before->post_name ) { return; }
	$old = str_replace( $after->post_name, $before->post_name, get_permalink( $after ) );
	if ( $old !== get_permalink( $after ) ) { hcseo_add_redirect( $old, get_permalink( $after ) ); }
}, 10, 3 );
