<?php
/** Google সার্ভিস-অ্যাকাউন্ট OAuth (JWT), Search Console স্ট্যাটিস্টিক্স, PageSpeed Insights */
defined( 'ABSPATH' ) || exit;

function hcseo_google_token( $scope ) {
	$ck = 'hcseo_gt_' . md5( $scope );
	if ( $t = get_transient( $ck ) ) { return $t; }
	$sa = json_decode( (string) hcseo_opt( 'in_google_sa' ), true );
	if ( empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) { return new WP_Error( 'hcseo_sa', 'সার্ভিস অ্যাকাউন্ট JSON সঠিক নয়।' ); }
	$b64 = fn( $s ) => rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
	$now = time();
	$h   = $b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$c   = $b64( wp_json_encode( array( 'iss' => $sa['client_email'], 'scope' => $scope, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600 ) ) );
	$sig = '';
	if ( ! function_exists( 'openssl_sign' ) || ! openssl_sign( "$h.$c", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256 ) ) { return new WP_Error( 'hcseo_sign', 'JWT সাইন করা যায়নি (private_key/openssl)।' ); }
	$r = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$h.$c." . $b64( $sig ) ) ) );
	if ( is_wp_error( $r ) ) { return $r; }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $j['access_token'] ) ) { return new WP_Error( 'hcseo_oauth', 'OAuth ব্যর্থ: ' . ( $j['error_description'] ?? wp_remote_retrieve_response_code( $r ) ) ); }
	set_transient( $ck, $j['access_token'], 3000 );
	return $j['access_token'];
}

function hcseo_gsc_query( $days = 28 ) {
	$site = hcseo_opt( 'gsc_site' );
	if ( ! $site ) { return new WP_Error( 'hcseo_gsc', 'Search Console সাইট URL সেট করুন (অ্যাডভান্সড ট্যাব)।' ); }
	$tok = hcseo_google_token( 'https://www.googleapis.com/auth/webmasters.readonly' );
	if ( is_wp_error( $tok ) ) { return $tok; }
	$out = array();
	foreach ( array( 'query', 'page' ) as $dim ) {
		$r = wp_remote_post( 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( array( 'startDate' => gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ), 'endDate' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), 'dimensions' => array( $dim ), 'rowLimit' => 20 ) ) ) );
		if ( is_wp_error( $r ) ) { return $r; }
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( isset( $j['error'] ) ) { return new WP_Error( 'hcseo_gsc', $j['error']['message'] ?? 'GSC error' ); }
		$out[ $dim ] = $j['rows'] ?? array();
	}
	return $out;
}

function hcseo_psi( $url, $strategy ) {
	$ck = 'hcseo_psi_' . md5( $url . $strategy );
	if ( $c = get_transient( $ck ) ) { return $c; }
	$q = array( 'url' => $url, 'strategy' => $strategy, 'category' => 'performance' );
	if ( hcseo_opt( 'psi_key' ) ) { $q['key'] = hcseo_opt( 'psi_key' ); }
	$r = wp_remote_get( add_query_arg( $q, 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' ), array( 'timeout' => 60 ) );
	if ( is_wp_error( $r ) ) { return $r; }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( isset( $j['error'] ) ) { return new WP_Error( 'hcseo_psi', $j['error']['message'] ?? 'PSI error' ); }
	$a   = $j['lighthouseResult']['audits'] ?? array();
	$res = array( 'score' => (int) round( ( $j['lighthouseResult']['categories']['performance']['score'] ?? 0 ) * 100 ), 'fcp' => $a['first-contentful-paint']['displayValue'] ?? '-', 'lcp' => $a['largest-contentful-paint']['displayValue'] ?? '-', 'tbt' => $a['total-blocking-time']['displayValue'] ?? '-', 'cls' => $a['cumulative-layout-shift']['displayValue'] ?? '-', 'si' => $a['speed-index']['displayValue'] ?? '-' );
	set_transient( $ck, $res, HOUR_IN_SECONDS );
	return $res;
}
