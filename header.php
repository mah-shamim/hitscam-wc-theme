<?php
/** হেডার: টপবার, লোগো, সার্চ, আইকন, নেভ + ক্যাটেগরি সাবমেনু */
defined( 'ABSPATH' ) || exit;
$phone   = hitscam_opt( 'phone', '+880 1614-747054' );
$tel     = preg_replace( '/[^\d+]/', '', $phone );
$limit   = (int) hitscam_opt( 'freeship', 5000 );
$topbar  = hitscam_opt( 'topbar', '' ) ?: ( $limit ? '৳' . number_format_i18n( $limit ) . '+ অর্ডারে ফ্রি ডেলিভারি' : '' );
$wc      = hitscam_is_wc();
$cats    = $wc ? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => array( (int) get_option( 'default_product_cat' ) ), 'number' => 12 ) ) : array();
$shop    = $wc ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$account = $wc ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$ids     = (array) get_option( 'hitscam_page_ids', array() );
$track   = ! empty( $ids['track-order'] ) ? get_permalink( $ids['track-order'] ) : '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'মূল কনটেন্টে যান', 'hitscam' ); ?></a>
<div class="topbar"><div class="container d-flex justify-content-between flex-wrap gap-1">
	<span><?php if ( $topbar ) : ?><i class="bi bi-truck"></i> <?php echo esc_html( $topbar ); ?><?php endif; ?></span>
	<span><a href="tel:<?php echo esc_attr( $tel ); ?>"><i class="bi bi-telephone"></i> <?php echo esc_html( $phone ); ?></a></span></div></div>
<header class="site-header sticky-top"><div class="container">
	<div class="header-main d-flex align-items-center gap-3">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php bloginfo( 'name' ); ?>">
			<?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
			<span class="brand-mark"><i class="bi bi-camera-video-fill"></i></span><span class="brand-text"><?php echo esc_html( hitscam_opt( 'brand_a', 'HITS' ) ); ?><b><?php echo esc_html( hitscam_opt( 'brand_b', 'Cam' ) ); ?></b></span>
			<?php endif; ?></a>
		<form class="search-box flex-grow-1 d-none d-md-flex" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<input type="hidden" name="post_type" value="product">
			<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
			<select name="product_cat" aria-label="ক্যাটাগরি"><option value="">সকল ক্যাটাগরি</option><?php foreach ( $cats as $c ) : ?><option value="<?php echo esc_attr( $c->slug ); ?>"><?php echo esc_html( $c->name ); ?></option><?php endforeach; ?></select>
			<?php endif; ?>
			<input class="form-control" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="পণ্য খুঁজুন… (Banglish এও চলে)" autocomplete="off" aria-label="পণ্য খুঁজুন"><button aria-label="সার্চ"><i class="bi bi-search"></i></button></form>
		<div class="header-icons">
			<button class="icon-btn" id="themeBtn" type="button" aria-label="থিম বদলান" title="ডার্ক / লাইট মোড"><i class="bi bi-sun sun"></i><i class="bi bi-moon-stars-fill moon"></i></button>
			<button class="icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#miniWish" aria-label="উইশলিস্ট"><i class="bi bi-heart"></i><span class="badge-count wish-count">0</span></button>
			<?php if ( $wc ) : ?><button class="icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#miniCart" aria-label="কার্ট"><i class="bi bi-cart3"></i><span class="badge-count cart-count"><?php echo (int) ( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span></button><?php endif; ?>
			<?php if ( $track ) : ?><a class="icon-btn" href="<?php echo esc_url( $track ); ?>" aria-label="অর্ডার ট্র্যাক" title="অর্ডার ট্র্যাক"><i class="bi bi-truck"></i></a><?php endif; ?>
			<a class="icon-btn" href="<?php echo esc_url( $account ); ?>" aria-label="লগইন / অ্যাকাউন্ট" title="লগইন / অ্যাকাউন্ট"><i class="bi bi-person"></i></a>
		</div></div>
	<form class="search-box d-flex d-md-none mobile-search mb-2" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get"><input type="hidden" name="post_type" value="product"><input class="form-control" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="প্রোডাক্ট খুঁজুন..." autocomplete="off" aria-label="প্রোডাক্ট খুঁজুন"><button aria-label="সার্চ"><i class="bi bi-search"></i></button></form>
</div>
<nav class="main-nav" aria-label="প্রধান মেনু"><div class="container nav-wrap"><ul class="nav flex-nowrap overflow-auto"><?php hitscam_primary_nav( $shop ); ?></ul>
	<?php if ( $cats && ! is_wp_error( $cats ) ) : ?><div class="submenu" id="navSub"><?php foreach ( $cats as $c ) :
		$tid = get_term_meta( $c->term_id, 'thumbnail_id', true );
		$img = $tid ? wp_get_attachment_image_url( $tid, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ); ?>
		<a class="sub-item" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><img src="<?php echo esc_url( $img ); ?>" alt="" loading="lazy" width="40" height="40"><span><?php echo esc_html( $c->name ); ?><small><?php echo esc_html( hitscam_bn_digits( $c->count ) ); ?>টি পণ্য</small></span></a>
	<?php endforeach; ?><a class="sub-item sub-all" href="<?php echo esc_url( $shop ); ?>"><i class="bi bi-grid-fill"></i><span>সব প্রোডাক্ট দেখুন</span></a></div><?php endif; ?></div></nav></header>
