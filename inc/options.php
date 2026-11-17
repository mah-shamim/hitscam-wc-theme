<?php
/** Customizer: ব্র্যান্ড, যোগাযোগ, ফ্রি শিপিং, বাল্ক ছাড়। */
defined( 'ABSPATH' ) || exit;

add_action( 'customize_register', function ( WP_Customize_Manager $c ) {
	$c->add_section( 'hitscam', array( 'title' => __( 'HitsCam সেটিংস', 'hitscam' ), 'priority' => 30 ) );
	$fields = array(
		'phone'      => array( 'ফোন', '+880 1614-747054', 'sanitize_text_field' ),
		'whatsapp'   => array( 'WhatsApp নম্বর (দেশ কোডসহ, + ছাড়া)', '8801614747054', 'sanitize_text_field' ),
		'email'      => array( 'সাপোর্ট ইমেইল', 'support@hitscam.com', 'sanitize_email' ),
		'topbar'     => array( 'টপবার বার্তা', '', 'sanitize_text_field' ),
		'freeship'   => array( 'ফ্রি ডেলিভারির ন্যূনতম অর্ডার (৳)', 5000, 'absint' ),
		'brand_a'    => array( 'ব্র্যান্ড নাম — প্রথম অংশ', 'HITS', 'sanitize_text_field' ),
		'brand_b'    => array( 'ব্র্যান্ড নাম — হাইলাইট অংশ', 'Cam', 'sanitize_text_field' ),
		'footer_txt' => array( 'ফুটার বর্ণনা', 'সিসিটিভি, আইপি ক্যামেরা ও স্মার্ট গ্যাজেটের বিশ্বস্ত অনলাইন শপ। অরিজিনাল পণ্য, দ্রুত ডেলিভারি, বিক্রয়োত্তর সাপোর্ট।', 'sanitize_textarea_field' ),
		'pay_txt'    => array( 'ফুটার পেমেন্ট লাইন', 'পেমেন্ট: ক্যাশ অন ডেলিভারি · বিকাশ · নগদ', 'sanitize_text_field' ),
	);
	foreach ( $fields as $k => $f ) {
		$c->add_setting( 'hitscam_' . $k, array( 'default' => $f[1], 'sanitize_callback' => $f[2] ) );
		$c->add_control( 'hitscam_' . $k, array( 'label' => $f[0], 'section' => 'hitscam', 'type' => 'freeship' === $k ? 'number' : ( 'footer_txt' === $k ? 'textarea' : 'text' ) ) );
	}
	$colors = array( 'color_brand' => array( 'প্রধান রং', '#e03131' ), 'color_ink' => array( 'গাঢ় (নেভি) রং', '#0f172a' ), 'color_accent' => array( 'অ্যাকসেন্ট রং', '#f59f00' ) );
	foreach ( $colors as $k => $f ) {
		$c->add_setting( 'hitscam_' . $k, array( 'default' => $f[1], 'sanitize_callback' => 'sanitize_hex_color' ) );
		$c->add_control( new WP_Customize_Color_Control( $c, 'hitscam_' . $k, array( 'label' => $f[0], 'section' => 'hitscam' ) ) );
	}
	// বাল্ক ছাড়: ধাপ (পিস:শতাংশ)
	$c->add_setting( 'hitscam_bulk', array( 'default' => '5:3,10:6,20:10', 'sanitize_callback' => 'sanitize_text_field' ) );
	$c->add_control( 'hitscam_bulk', array( 'label' => __( 'বাল্ক ছাড় (পিস:শতাংশ, কমা দিয়ে; ফাঁকা = বন্ধ)', 'hitscam' ), 'section' => 'hitscam', 'type' => 'text' ) );
} );

/** রং CSS ভ্যারিয়েবল হিসেবে আউটপুট */
add_action( 'wp_head', function () {
	$b = sanitize_hex_color( hitscam_opt( 'color_brand', '#e03131' ) ) ?: '#e03131';
	$i = sanitize_hex_color( hitscam_opt( 'color_ink', '#0f172a' ) ) ?: '#0f172a';
	$a = sanitize_hex_color( hitscam_opt( 'color_accent', '#f59f00' ) ) ?: '#f59f00';
	$dark = hitscam_darken( $b, 0.12 );
	printf( "<style id=\"hitscam-vars\">:root{--brand:%s;--brand-dark:%s;--ink:%s;--navy:%s;--amber:%s}</style>\n", esc_html( $b ), esc_html( $dark ), esc_html( $i ), esc_html( $i ), esc_html( $a ) );
}, 20 );

function hitscam_darken( $hex, $amt ) {
	$hex = ltrim( $hex, '#' );
	$rgb = array_map( 'hexdec', str_split( $hex, 2 ) );
	return sprintf( '#%02x%02x%02x', ...array_map( fn( $v ) => max( 0, (int) round( $v * ( 1 - $amt ) ) ), $rgb ) );
}

/** বাল্ক ছাড়ের ধাপ: [ [min, pct], ... ] */
function hitscam_bulk_tiers() {
	$raw   = trim( (string) hitscam_opt( 'bulk', '5:3,10:6,20:10' ) );
	$tiers = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $t ) {
		$p = explode( ':', $t );
		if ( count( $p ) === 2 && (int) $p[0] > 1 && (float) $p[1] > 0 && (float) $p[1] < 100 ) {
			$tiers[] = array( (int) $p[0], (float) $p[1] );
		}
	}
	usort( $tiers, fn( $a, $b ) => $a[0] <=> $b[0] );
	return $tiers;
}
