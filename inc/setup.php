<?php
defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'hitscam', HITSCAM_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'custom-logo', array( 'height' => 48, 'width' => 160, 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 600,
		'single_image_width'    => 900,
		'product_grid'          => array( 'default_columns' => 4, 'min_columns' => 2, 'max_columns' => 4 ),
	) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	register_nav_menus( array(
		'primary' => __( 'প্রধান মেনু', 'hitscam' ),
		'footer1' => __( 'ফুটার — কোম্পানি', 'hitscam' ),
		'footer2' => __( 'ফুটার — পলিসি', 'hitscam' ),
	) );
	add_image_size( 'hitscam-card', 600, 600, true );
} );

add_action( 'widgets_init', function () {
	register_sidebar( array(
		'name'          => __( 'ব্লগ সাইডবার', 'hitscam' ),
		'id'            => 'sidebar-blog',
		'before_widget' => '<section class="panel mb-3 %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="h6 fw-bold mb-2">',
		'after_title'   => '</h2>',
	) );
} );

/** body ক্লাস ও ডার্ক থিমের ফ্ল্যাশ এড়াতে হেডে ছোট স্ক্রিপ্ট */
add_action( 'wp_head', function () {
	echo "<script>try{var t=localStorage.getItem('hc_theme');if(t){document.documentElement.setAttribute('data-theme',t);document.documentElement.setAttribute('data-bs-theme',t)}}catch(e){}</script>\n";
}, 1 );

/** মেনু ফলব্যাক ও কনটেন্ট চওড়া নিয়ম */
function hitscam_is_wc() {
	return class_exists( 'WooCommerce' );
}

function hitscam_opt( $key, $default = '' ) {
	return get_theme_mod( 'hitscam_' . $key, $default );
}

function hitscam_money_plain( $amount ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
}

/** বাংলা সংখ্যা (ঐচ্ছিক ফরম্যাট) */
function hitscam_bn_digits( $s ) {
	return strtr( (string) $s, array( '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯' ) );
}

/** প্রধান মেনু: মেনু অ্যাসাইন থাকলে তা, নইলে ডিফল্ট; শপ আইটেমে ক্যাটেগরি সাবমেনু বাটন */
function hitscam_primary_nav( $shop_url = '' ) {
	$locs  = get_nav_menu_locations();
	$items = ! empty( $locs['primary'] ) ? wp_get_nav_menu_items( $locs['primary'] ) : array();
	if ( ! $items ) {
		$defaults = array( array( 'হোম', home_url( '/' ) ), array( 'সব প্রোডাক্ট', $shop_url ) );
		$items    = array_map( fn( $d ) => (object) array( 'title' => $d[0], 'url' => $d[1], 'menu_item_parent' => 0 ), array_filter( $defaults, fn( $d ) => $d[1] ) );
	}
	$here = untrailingslashit( set_url_scheme( home_url( add_query_arg( array() ) ) ) );
	foreach ( $items as $it ) {
		if ( (int) $it->menu_item_parent ) {
			continue;
		}
		$active = untrailingslashit( $it->url ) === untrailingslashit( $here ) || ( $shop_url && untrailingslashit( $it->url ) === untrailingslashit( $shop_url ) && function_exists( 'is_woocommerce' ) && is_woocommerce() );
		$cls    = 'nav-link text-nowrap' . ( $active ? ' active' : '' );
		if ( $shop_url && untrailingslashit( $it->url ) === untrailingslashit( $shop_url ) ) {
			echo '<li class="nav-item has-sub d-flex align-items-center"><a class="' . esc_attr( $cls ) . ' pe-1" href="' . esc_url( $it->url ) . '">' . esc_html( $it->title ) . '</a><button class="sub-toggle" type="button" aria-label="সাব মেনু" aria-expanded="false"><i class="bi bi-chevron-down"></i></button></li>';
		} else {
			echo '<li class="nav-item"><a class="' . esc_attr( $cls ) . '" href="' . esc_url( $it->url ) . '">' . esc_html( $it->title ) . '</a></li>';
		}
	}
}

/** ফুটার মেনুর সরল ওয়াকার: শুধু <a> */
class Hitscam_Plain_Walker extends Walker_Nav_Menu {
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$output .= '<a href="' . esc_url( $item->url ) . '">' . esc_html( $item->title ) . '</a>';
	}
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}
