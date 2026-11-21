<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	$u = HITSCAM_URI . '/assets/';
	wp_enqueue_style( 'hitscam-font', 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'hitscam-bootstrap', $u . 'vendor/bootstrap/bootstrap.min.css', array(), '5.3.3' );
	wp_enqueue_style( 'hitscam-icons', $u . 'vendor/icons/bootstrap-icons.min.css', array(), '1.11.3' );
	wp_enqueue_style( 'hitscam-owl', $u . 'vendor/owl/owl.carousel.min.css', array(), '2.3.4' );
	wp_enqueue_style( 'hitscam-owl-theme', $u . 'vendor/owl/owl.theme.default.min.css', array( 'hitscam-owl' ), '2.3.4' );
	wp_enqueue_style( 'hitscam-template', $u . 'css/template.css', array( 'hitscam-bootstrap' ), HITSCAM_VERSION );
	wp_enqueue_style( 'hitscam-wc', $u . 'css/wc.css', array( 'hitscam-template' ), HITSCAM_VERSION );

	wp_enqueue_script( 'hitscam-bootstrap', $u . 'vendor/bootstrap/bootstrap.bundle.min.js', array(), '5.3.3', true );
	wp_enqueue_script( 'hitscam-owl', $u . 'vendor/owl/owl.carousel.min.js', array( 'jquery' ), '2.3.4', true );
	wp_enqueue_script( 'hitscam-theme', $u . 'js/theme.js', array( 'jquery', 'hitscam-bootstrap', 'hitscam-owl' ), HITSCAM_VERSION, true );
	wp_enqueue_script( 'hitscam-shop', $u . 'js/shop.js', array( 'hitscam-theme' ), HITSCAM_VERSION, true );
	if ( function_exists( 'is_product' ) && is_product() ) {
		wp_enqueue_script( 'hitscam-pdp', $u . 'js/pdp.js', array( 'hitscam-shop' ), HITSCAM_VERSION, true );
	}
	wp_localize_script( 'hitscam-theme', 'HCT', array(
		'freeShip' => (float) hitscam_opt( 'freeship', 5000 ),
		'ajax'     => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'hitscam' ),
		'rest'     => esc_url_raw( rest_url( 'hitscam-theme/v1/' ) ),
		'shop'     => hitscam_is_wc() ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
		'cmpUrl'   => ( $ids = (array) get_option( 'hitscam_page_ids', array() ) ) && ! empty( $ids['compare'] ) ? get_permalink( $ids['compare'] ) : '',
		'cartUrl'  => hitscam_is_wc() ? wc_get_cart_url() : '',
		'checkout' => hitscam_is_wc() ? wc_get_checkout_url() : '',
		'currency' => hitscam_is_wc() ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '৳',
		'wa'       => preg_replace( '/\D/', '', (string) hitscam_opt( 'whatsapp', '8801953073445' ) ),
		'i18n'     => array(
			'added'    => __( 'কার্টে যোগ হয়েছে ✓', 'hitscam' ),
			'wishAdd'  => __( 'উইশলিস্টে যোগ হয়েছে', 'hitscam' ),
			'wishDel'  => __( 'উইশলিস্ট থেকে সরানো হয়েছে', 'hitscam' ),
			'cmpMax'   => __( 'সর্বোচ্চ ৩টি পণ্য তুলনা করা যায়', 'hitscam' ),
			'cmpAdd'   => __( 'তুলনায় যোগ হয়েছে', 'hitscam' ),
			'empty'    => __( 'কিছু নেই', 'hitscam' ),
			'noresult' => __( 'কিছু পাওয়া যায়নি', 'hitscam' ),
		),
	) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
} );

/** ক্লাসিক হেডার ফিক্সের জন্য body class */
add_filter( 'body_class', function ( $c ) {
	$c[] = 'hitscam';
	return $c;
} );
