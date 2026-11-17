<?php
/** JSON-LD: Knowledge Graph, WebSite, Breadcrumb, Product, Article, LocalBusiness + ব্রেডক্রাম্ব UI */
defined( 'ABSPATH' ) || exit;

function hcseo_breadcrumb_items() {
	$items = array( array( get_bloginfo( 'name' ), home_url( '/' ) ) );
	if ( is_front_page() ) { return $items; }
	if ( is_singular() ) {
		$p = get_queried_object();
		if ( 'product' === $p->post_type ) {
			if ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) { $items[] = array( get_the_title( wc_get_page_id( 'shop' ) ), get_permalink( wc_get_page_id( 'shop' ) ) ); }
			$terms = wp_get_post_terms( $p->ID, 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$t = $terms[0]; $chain = array();
				while ( $t && ! is_wp_error( $t ) ) { array_unshift( $chain, $t ); $t = $t->parent ? get_term( $t->parent, 'product_cat' ) : null; }
				foreach ( $chain as $t ) { $items[] = array( $t->name, get_term_link( $t ) ); }
			}
		} elseif ( 'post' === $p->post_type ) {
			$c = get_the_category( $p->ID ); if ( $c ) { $items[] = array( $c[0]->name, get_category_link( $c[0] ) ); }
		} else {
			foreach ( array_reverse( get_post_ancestors( $p ) ) as $aid ) { $items[] = array( get_the_title( $aid ), get_permalink( $aid ) ); }
		}
		$items[] = array( get_the_title( $p ), get_permalink( $p ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$t = get_queried_object(); $chain = array();
		if ( 'product_cat' === $t->taxonomy && function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) { $items[] = array( get_the_title( wc_get_page_id( 'shop' ) ), get_permalink( wc_get_page_id( 'shop' ) ) ); }
		while ( $t && ! is_wp_error( $t ) ) { array_unshift( $chain, $t ); $t = $t->parent ? get_term( $t->parent, $t->taxonomy ) : null; }
		foreach ( $chain as $t ) { $items[] = array( $t->name, get_term_link( $t ) ); }
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$items[] = array( woocommerce_page_title( false ), '' );
	} elseif ( is_search() ) {
		$items[] = array( sprintf( 'সার্চ: %s', get_search_query() ), '' );
	} elseif ( is_404() ) {
		$items[] = array( '404', '' );
	} else {
		$items[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	}
	return apply_filters( 'hitscam_seo_breadcrumbs', $items );
}

function hcseo_seo_breadcrumbs_html() {
	$it = hcseo_breadcrumb_items(); $n = count( $it ); $sep = esc_html( hcseo_opt( 'bc_sep' ) );
	$h = '<nav class="hcseo-bc" aria-label="Breadcrumb">';
	foreach ( $it as $i => $x ) {
		$h .= ( $i < $n - 1 && $x[1] && ! is_wp_error( $x[1] ) ) ? '<a href="' . esc_url( $x[1] ) . '">' . esc_html( $x[0] ) . '</a> <span aria-hidden="true">' . $sep . '</span> ' : '<span aria-current="page">' . esc_html( $x[0] ) . '</span>';
	}
	return $h . '</nav>';
}
function hitscam_seo_breadcrumbs() { echo hcseo_seo_breadcrumbs_html(); } // phpcs:ignore
add_shortcode( 'hitscam_breadcrumbs', 'hcseo_seo_breadcrumbs_html' );

add_action( 'wp_head', function () {
	if ( ! hcseo_opt( 'schema_enable' ) || is_feed() || is_404() ) { return; }
	$o = hcseo_opts(); $c = hcseo_ctx(); $g = array();
	$name = $o['kg_name'] ?: get_bloginfo( 'name' );
	$org  = array( '@type' => $o['kg_type'], '@id' => home_url( '/#entity' ), 'name' => $name, 'url' => home_url( '/' ) );
	if ( $o['kg_logo'] ) { $org['logo'] = array( '@type' => 'ImageObject', 'url' => $o['kg_logo'] ); $org['image'] = $o['kg_logo']; }
	$same = array_values( array_filter( array_map( 'esc_url_raw', preg_split( '/\R/', (string) $o['kg_same_as'] ) ) ) );
	if ( $same ) { $org['sameAs'] = $same; }
	if ( $o['kg_phone'] ) { $org['telephone'] = $o['kg_phone']; }
	if ( $o['lb_enable'] ) {
		$org['@type'] = array( $o['lb_type'] ?: 'LocalBusiness' ); if ( 'Person' === $o['kg_type'] ) { $org['@type'] = array( 'Person', $o['lb_type'] ); }
		$org['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => $o['lb_street'], 'addressLocality' => $o['lb_city'], 'addressRegion' => $o['lb_region'], 'postalCode' => $o['lb_postal'], 'addressCountry' => $o['lb_country'] );
		if ( $o['lb_hours'] ) { $org['openingHours'] = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $o['lb_hours'] ) ) ) ); }
		if ( $o['lb_price'] ) { $org['priceRange'] = $o['lb_price']; }
	}
	if ( is_front_page() || is_home() ) { $g[] = $org; }
	if ( $o['sitelinks'] && is_front_page() ) {
		$g[] = array( '@type' => 'WebSite', '@id' => home_url( '/#website' ), 'url' => home_url( '/' ), 'name' => get_bloginfo( 'name' ), 'publisher' => array( '@id' => home_url( '/#entity' ) ),
			'potentialAction' => array( '@type' => 'SearchAction', 'target' => array( '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/?s={search_term_string}' ) ), 'query-input' => 'required name=search_term_string' ) );
	}
	if ( $o['breadcrumbs'] && ! is_front_page() ) {
		$list = array();
		foreach ( hcseo_breadcrumb_items() as $i => $x ) {
			$el = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $x[0] );
			if ( $x[1] && ! is_wp_error( $x[1] ) ) { $el['item'] = $x[1]; }
			$list[] = $el;
		}
		$g[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $list );
	}
	if ( is_singular() && $c['post'] ) {
		$p = $c['post'];
		if ( 'product' === $p->post_type && function_exists( 'wc_get_product' ) && ( $pr = wc_get_product( $p->ID ) ) ) {
			$prod = array( '@type' => 'Product', '@id' => get_permalink( $p ) . '#product', 'name' => get_the_title( $p ), 'url' => get_permalink( $p ), 'description' => $c['desc'], 'sku' => $pr->get_sku() ?: (string) $pr->get_id() );
			if ( $c['image'] ) { $prod['image'] = array( $c['image'] ); }
			$prod['offers'] = array( '@type' => 'Offer', 'url' => get_permalink( $p ), 'priceCurrency' => get_woocommerce_currency(), 'price' => wc_format_decimal( wc_get_price_to_display( $pr ), wc_get_price_decimals() ),
				'availability' => $pr->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'itemCondition' => 'https://schema.org/NewCondition', 'seller' => array( '@id' => home_url( '/#entity' ) ) );
			if ( $pr->get_rating_count() > 0 ) { $prod['aggregateRating'] = array( '@type' => 'AggregateRating', 'ratingValue' => $pr->get_average_rating(), 'reviewCount' => $pr->get_rating_count() ); }
			$g[] = $prod;
		} elseif ( 'post' === $p->post_type ) {
			$g[] = array( '@type' => 'Article', 'headline' => get_the_title( $p ), 'description' => $c['desc'], 'datePublished' => get_the_date( 'c', $p ), 'dateModified' => get_the_modified_date( 'c', $p ), 'author' => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $p->post_author ) ), 'publisher' => array( '@id' => home_url( '/#entity' ) ), 'image' => $c['image'] ?: null, 'mainEntityOfPage' => get_permalink( $p ) );
		}
	}
	$g = apply_filters( 'hitscam_seo_schema', $g );
	if ( $g ) { echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => array_values( $g ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n"; }
}, 5 );

// WooCommerce-এর নিজস্ব Product JSON-LD বন্ধ (ডুপ্লিকেট এড়াতে)
add_action( 'wp', function () {
	if ( hcseo_opt( 'schema_enable' ) && function_exists( 'WC' ) && WC()->structured_data ) {
		remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
		remove_action( 'woocommerce_email_order_details', array( WC()->structured_data, 'output_email_structured_data' ), 30 );
	}
}, 20 );
