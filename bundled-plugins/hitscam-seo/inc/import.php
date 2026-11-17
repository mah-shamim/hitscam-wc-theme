<?php
/** এক-ক্লিক ইমপোর্ট: Yoast, Rank Math, SEOPress, AIOSEO (পোস্ট মেটা; Rank Math/SEOPress টার্ম মেটাও) */
defined( 'ABSPATH' ) || exit;

function hcseo_import_sources() {
	return array(
		'yoast' => array( 'Yoast SEO', 'post', array( 'title' => '_yoast_wpseo_title', 'desc' => '_yoast_wpseo_metadesc', 'canonical' => '_yoast_wpseo_canonical', 'noindex' => '_yoast_wpseo_meta-robots-noindex', 'nofollow' => '_yoast_wpseo_meta-robots-nofollow', 'og_title' => '_yoast_wpseo_opengraph-title', 'og_desc' => '_yoast_wpseo_opengraph-description', 'og_image' => '_yoast_wpseo_opengraph-image', 'keywords' => '_yoast_wpseo_focuskw' ) ),
		'rankmath' => array( 'Rank Math', 'both', array( 'title' => 'rank_math_title', 'desc' => 'rank_math_description', 'canonical' => 'rank_math_canonical_url', 'robots' => 'rank_math_robots', 'og_title' => 'rank_math_facebook_title', 'og_desc' => 'rank_math_facebook_description', 'og_image' => 'rank_math_facebook_image', 'keywords' => 'rank_math_focus_keyword' ) ),
		'seopress' => array( 'SEOPress', 'both', array( 'title' => '_seopress_titles_title', 'desc' => '_seopress_titles_desc', 'canonical' => '_seopress_robots_canonical', 'noindex' => '_seopress_robots_index', 'nofollow' => '_seopress_robots_follow', 'og_title' => '_seopress_social_fb_title', 'og_desc' => '_seopress_social_fb_desc', 'og_image' => '_seopress_social_fb_img', 'keywords' => '_seopress_analysis_target_kw' ) ),
		'aioseo' => array( 'All in One SEO', 'post', array( 'title' => '_aioseo_title', 'desc' => '_aioseo_description', 'og_title' => '_aioseo_og_title', 'og_desc' => '_aioseo_og_description' ) ),
	);
}

function hcseo_convert_vars( $s ) {
	$map = array( 'title' => 'post_title', 'post_title' => 'post_title', 'sitename' => 'sitetitle', 'site_title' => 'sitetitle', 'sitedesc' => 'sitedesc', 'tagline' => 'sitedesc', 'excerpt' => 'post_excerpt', 'post_excerpt' => 'post_excerpt',
		'primary_category' => 'post_category', 'category' => 'post_category', 'term_title' => 'term_title', 'term' => 'term_title', 'name' => 'post_author', 'date' => 'post_date', 'currentyear' => 'current_year', 'sep' => 'sep', 'separator_sa' => 'sep', 'page' => 'page' );
	return preg_replace_callback( '/%%([a-z_]+)%%|%([a-z_]+)%|\[#([a-z_]+)\]|#([a-z_]+)/i', function ( $m ) use ( $map ) {
		$k = strtolower( $m[1] ?: ( $m[2] ?? '' ) ?: ( $m[3] ?? '' ) ?: ( $m[4] ?? '' ) );
		return isset( $map[ $k ] ) ? '%%' . $map[ $k ] . '%%' : $m[0];
	}, (string) $s );
}

function hcseo_import_count( $src ) {
	global $wpdb;
	$map = hcseo_import_sources()[ $src ][2];
	$in  = "'" . implode( "','", array_map( 'esc_sql', array_values( $map ) ) ) . "'";
	return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ($in) AND meta_value <> ''" ); // phpcs:ignore
}

function hcseo_import_run( $src, $overwrite = false ) {
	global $wpdb;
	$s = hcseo_import_sources();
	if ( ! isset( $s[ $src ] ) ) { return 0; }
	$map = $s[ $src ][2]; $n = 0;
	$targets = array( array( 'post', $wpdb->postmeta, 'post_id' ) );
	if ( 'both' === $s[ $src ][1] ) { $targets[] = array( 'term', $wpdb->termmeta, 'term_id' ); }
	foreach ( $targets as list( $kind, $tbl, $col ) ) {
		$ids = $wpdb->get_col( "SELECT DISTINCT $col FROM $tbl WHERE meta_key IN ('" . implode( "','", array_map( 'esc_sql', array_values( $map ) ) ) . "') AND meta_value <> ''" ); // phpcs:ignore
		foreach ( $ids as $id ) {
			$get = 'term' === $kind ? 'get_term_meta' : 'get_post_meta'; $upd = 'term' === $kind ? 'update_term_meta' : 'update_post_meta';
			$did = false;
			foreach ( $map as $k => $key ) {
				$v = $get( $id, $key, true );
				if ( '' === $v || array() === $v ) { continue; }
				if ( 'robots' === $k ) { // Rank Math: array
					foreach ( (array) $v as $flag ) { if ( in_array( $flag, array( 'noindex', 'nofollow' ), true ) && ( $overwrite || ! $get( $id, '_hcseo_' . $flag, true ) ) ) { $upd( $id, '_hcseo_' . $flag, '1' ); $did = true; } }
					continue;
				}
				if ( in_array( $k, array( 'noindex', 'nofollow' ), true ) ) { $v = ( '1' === (string) $v || 'yes' === $v ) ? '1' : ''; if ( ! $v ) { continue; } }
				elseif ( in_array( $k, array( 'title', 'desc', 'og_title', 'og_desc' ), true ) ) { $v = hcseo_convert_vars( $v ); }
				if ( $overwrite || '' === (string) $get( $id, '_hcseo_' . $k, true ) ) { $upd( $id, '_hcseo_' . $k, sanitize_text_field( is_array( $v ) ? implode( ',', $v ) : $v ) ); $did = true; }
			}
			$n += $did ? 1 : 0;
		}
	}
	return $n;
}
