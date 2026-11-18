<?php
/** টাইটেল, মেটা ডেসক্রিপশন, canonical, robots, OG/X, Dublin Core, WooCommerce মেটা */
defined( 'ABSPATH' ) || exit;

function hcseo_ctx() {
	static $c = null;
	if ( null !== $c ) { return $c; }
	$o   = hcseo_opts();
	$ctx = array();
	$tpl_t = $tpl_d = '';
	$m = array();
	if ( is_front_page() || is_home() && ! is_paged() && 'posts' === get_option( 'show_on_front' ) ) {
		$tpl_t = $o['home_title']; $tpl_d = $o['home_desc'];
		if ( is_front_page() && ( $pid = (int) get_option( 'page_on_front' ) ) ) { $ctx['post'] = get_post( $pid ); $m = hcseo_get_meta( $pid ); }
		$url = home_url( '/' ); $type = 'website';
	} elseif ( is_singular() ) {
		$p = get_queried_object(); $ctx['post'] = $p; $m = hcseo_get_meta( $p->ID );
		$k = 'product' === $p->post_type ? 'product' : ( 'page' === $p->post_type ? 'page' : 'post' );
		$tpl_t = $o[ $k . '_title' ]; $tpl_d = $o[ $k . '_desc' ];
		$url = get_permalink( $p ); $type = 'product' === $p->post_type ? 'product' : ( 'post' === $p->post_type ? 'article' : 'website' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$t = get_queried_object(); $ctx['term'] = $t; $m = hcseo_get_meta( $t->term_id, 'term' );
		$tpl_t = $o['term_title']; $tpl_d = $o['term_desc']; $url = get_term_link( $t ); $type = 'website';
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$sp = (int) wc_get_page_id( 'shop' ); if ( $sp > 0 ) { $ctx['post'] = get_post( $sp ); $m = hcseo_get_meta( $sp ); }
		$tpl_t = '%%shop_title%% %%sep%% %%sitetitle%%'; $tpl_d = 'আমাদের সব পণ্য দেখুন — %%sitetitle%%'; $url = get_post_type_archive_link( 'product' ); $type = 'website';
	} elseif ( is_search() ) {
		$tpl_t = $o['search_title']; $url = get_search_link(); $type = 'website';
	} elseif ( is_404() ) {
		$tpl_t = $o['notfound_title']; $url = ''; $type = 'website';
	} else {
		$tpl_t = $o['archive_title']; $url = ''; $type = 'website';
	}
	$title = ! empty( $m['title'] ) ? $m['title'] : $tpl_t;
	$desc  = ! empty( $m['desc'] ) ? $m['desc'] : $tpl_d;
	$img   = ! empty( $m['og_image'] ) ? $m['og_image'] : '';
	if ( ! $img && ! empty( $ctx['post'] ) ) { $img = get_the_post_thumbnail_url( $ctx['post'], 'large' ) ?: ''; }
	if ( ! $img && ! empty( $ctx['term'] ) && 'product_cat' === $ctx['term']->taxonomy ) { $tid = (int) get_term_meta( $ctx['term']->term_id, 'thumbnail_id', true ); $img = $tid ? wp_get_attachment_url( $tid ) : ''; }
	$img = $img ?: $o['default_image'];
	$robots = array();
	if ( ! empty( $m['noindex'] ) ) { $robots['noindex'] = true; }
	if ( ! empty( $m['nofollow'] ) ) { $robots['nofollow'] = true; }
	if ( ( is_search() && $o['noindex_search'] ) || ( is_author() && $o['noindex_author'] ) || ( is_date() && $o['noindex_date'] ) || ( function_exists( 'is_product_tag' ) && is_product_tag() && $o['noindex_tags'] ) || is_404() ) { $robots['noindex'] = true; }
	if ( $o['noindex_filtered'] && function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) && array_intersect( array_keys( $_GET ), array( 'orderby', 'min_price', 'max_price', 'rating_filter', 's', 'filter', 'view' ) ) ) { $robots['noindex'] = true; } // phpcs:ignore
	$can = ! empty( $m['canonical'] ) ? $m['canonical'] : $url;
	if ( $can && ! is_wp_error( $can ) && is_paged() && empty( $m['canonical'] ) ) { $can = get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ) ); }
	$c = array(
		'title' => hcseo_replace( $title, $ctx ), 'desc' => wp_strip_all_tags( hcseo_replace( $desc, $ctx ) ),
		'og_title' => ! empty( $m['og_title'] ) ? $m['og_title'] : '', 'og_desc' => ! empty( $m['og_desc'] ) ? $m['og_desc'] : '',
		'image' => $img, 'canonical' => is_wp_error( $can ) ? '' : $can, 'robots' => $robots, 'type' => $type, 'post' => $ctx['post'] ?? null, 'term' => $ctx['term'] ?? null,
	);
	$c['og_title'] = $c['og_title'] ? hcseo_replace( $c['og_title'], $ctx ) : $c['title'];
	$c['og_desc']  = $c['og_desc'] ? hcseo_replace( $c['og_desc'], $ctx ) : $c['desc'];
	return $c = apply_filters( 'hitscam_seo_context', $c );
}

function hcseo_get_meta( $id, $kind = 'post' ) {
	$g = 'term' === $kind ? 'get_term_meta' : 'get_post_meta';
	$out = array();
	foreach ( array( 'title', 'desc', 'canonical', 'noindex', 'nofollow', 'og_title', 'og_desc', 'og_image', 'keywords' ) as $k ) { $out[ $k ] = $g( $id, '_hcseo_' . $k, true ); }
	return $out;
}

add_filter( 'pre_get_document_title', function ( $t ) { return is_feed() ? $t : ( hcseo_ctx()['title'] ?: $t ); }, 20 );
add_filter( 'wp_robots', function ( $r ) {
	$c = hcseo_ctx()['robots'];
	if ( ! empty( $c['noindex'] ) ) { $r['noindex'] = true; unset( $r['max-image-preview'] ); } elseif ( empty( $r['noindex'] ) ) { $r['max-image-preview'] = 'large'; $r['max-snippet'] = '-1'; $r['max-video-preview'] = '-1'; }
	if ( ! empty( $c['nofollow'] ) ) { $r['nofollow'] = true; } elseif ( ! empty( $r['noindex'] ) ) { $r['follow'] = true; }
	return $r;
}, 20 );
remove_action( 'wp_head', 'rel_canonical' );

add_action( 'wp_head', function () {
	if ( is_feed() ) { return; }
	$c = hcseo_ctx(); $o = hcseo_opts();
	echo "\n<!-- HitsCam SEO -->\n";
	if ( $c['desc'] ) { echo '<meta name="description" content="' . esc_attr( $c['desc'] ) . '">' . "\n"; }
	if ( $c['canonical'] && ! is_404() && ! is_search() ) { echo '<link rel="canonical" href="' . esc_url( $c['canonical'] ) . '">' . "\n"; }
	if ( $o['og_enable'] ) {
		$og = array( 'locale' => get_locale(), 'type' => 'product' === $c['type'] ? 'product' : ( 'article' === $c['type'] ? 'article' : 'website' ), 'title' => $c['og_title'], 'description' => $c['og_desc'], 'url' => $c['canonical'], 'site_name' => get_bloginfo( 'name' ), 'image' => $c['image'] );
		foreach ( $og as $k => $v ) { if ( $v ) { echo '<meta property="og:' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n"; } }
		if ( $o['fb_app_id'] ) { echo '<meta property="fb:app_id" content="' . esc_attr( $o['fb_app_id'] ) . '">' . "\n"; }
		if ( 'article' === $c['type'] && $c['post'] ) {
			echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $c['post'] ) ) . '">' . "\n" . '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $c['post'] ) ) . '">' . "\n";
		}
	}
	if ( $o['tw_enable'] ) {
		echo '<meta name="twitter:card" content="' . esc_attr( $c['image'] ? $o['tw_card'] : 'summary' ) . '">' . "\n";
		if ( $o['tw_site'] ) { echo '<meta name="twitter:site" content="' . esc_attr( '@' . ltrim( $o['tw_site'], '@' ) ) . '">' . "\n"; }
		echo '<meta name="twitter:title" content="' . esc_attr( $c['og_title'] ) . '">' . "\n";
		if ( $c['og_desc'] ) { echo '<meta name="twitter:description" content="' . esc_attr( $c['og_desc'] ) . '">' . "\n"; }
		if ( $c['image'] ) { echo '<meta name="twitter:image" content="' . esc_url( $c['image'] ) . '">' . "\n"; }
	}
	if ( $o['dublin_core'] ) {
		echo '<meta name="DC.title" content="' . esc_attr( $c['title'] ) . '">' . "\n";
		if ( $c['desc'] ) { echo '<meta name="DC.description" content="' . esc_attr( $c['desc'] ) . '">' . "\n"; }
		echo '<meta name="DC.language" content="' . esc_attr( get_bloginfo( 'language' ) ) . '">' . "\n" . '<meta name="DC.type" content="Text">' . "\n";
		if ( $c['canonical'] ) { echo '<meta name="DC.identifier" content="' . esc_url( $c['canonical'] ) . '">' . "\n"; }
	}
	if ( $o['wc_meta'] && function_exists( 'is_product' ) && is_product() && ( $pr = wc_get_product( get_queried_object_id() ) ) ) {
		$pm = array( 'product:price:amount' => wc_get_price_to_display( $pr ), 'product:price:currency' => get_woocommerce_currency(), 'product:availability' => $pr->is_in_stock() ? 'in stock' : 'out of stock', 'product:condition' => 'new', 'product:retailer_item_id' => $pr->get_sku() ?: $pr->get_id() );
		if ( $pr->is_on_sale() ) { $pm['product:sale_price:amount'] = wc_get_price_to_display( $pr ); $pm['product:original_price:amount'] = wc_get_price_to_display( $pr, array( 'price' => $pr->get_regular_price() ) ); }
		foreach ( $pm as $k => $v ) { if ( '' !== (string) $v ) { echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n"; } }
	}
	echo "<!-- /HitsCam SEO -->\n";
}, 1 );

// থিম/অন্য প্লাগিনের ডুপ্লিকেট description ঠেকাতে: নিজস্ব ফিল্টার
add_filter( 'hitscam_seo_is_active', '__return_true' );
